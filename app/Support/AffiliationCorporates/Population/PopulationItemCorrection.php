<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\Population;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\CorporatePopulationUpdate;
use App\Models\CorporatePopulationUpdateItem as Item;
use App\Models\User;
use App\Support\AffiliationCorporates\CorporateAffiliatePlanSynchronizer;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Correcciones que el analista hace desde la tabla de hallazgos de una carga.
 *
 * Reglas comunes:
 *  - Solo con la carga en «Analizada» y con la carga y la ficha bloqueadas.
 *  - Si la ficha cambió desde que el analista abrió la ventana, no se guarda.
 *  - Se guarda con Eloquent: pasa por el candado de personas duplicadas y por
 *    la auditoría de cambios (antes y después, reversible).
 *  - Tarifa, edad de tarifa y plan NO se tocan: la renovación los recalcula
 *    con la fecha de nacimiento corregida.
 *  - Al terminar, la fila se vuelve a cruzar con el archivo y el resumen se
 *    recalcula, para que el analista vea en el acto si ya cuadra.
 */
final class PopulationItemCorrection
{
    public const NOTE_MIN_LENGTH = 10;

    /** Filas por tanda de «Sincronizar con el archivo» (también el tamaño de bloque del trabajo en cola). */
    public const BULK_LIMIT = 100;

    /** Hasta aquí la sincronización se hace en el acto (~90 ms por ficha); más, va a la cola. */
    public const INLINE_LIMIT = 50;

    /** Grupos de campos de «Sincronizar con el archivo». */
    public const SYNC_GROUPS = [
        'document' => [PopulationFieldComparison::FIELD_DOCUMENT],
        'names' => [PopulationFieldComparison::FIELD_FIRST_NAME, PopulationFieldComparison::FIELD_LAST_NAME],
        'birth_date' => [PopulationFieldComparison::FIELD_BIRTH_DATE],
        'sex' => [PopulationFieldComparison::FIELD_SEX],
        'relationship' => [PopulationFieldComparison::FIELD_RELATIONSHIP],
    ];

    private const FEMALE_RELATIONSHIPS = ['ESPOSA', 'HIJA', 'MADRE'];

    private const MALE_RELATIONSHIPS = ['ESPOSO', 'HIJO', 'PADRE'];

    /** Columna de la ficha por campo. */
    private const COLUMNS = [
        PopulationFieldComparison::FIELD_DOCUMENT => 'nro_identificacion',
        PopulationFieldComparison::FIELD_FIRST_NAME => 'first_name',
        PopulationFieldComparison::FIELD_LAST_NAME => 'last_name',
        PopulationFieldComparison::FIELD_BIRTH_DATE => 'birth_date',
        PopulationFieldComparison::FIELD_SEX => 'sex',
        PopulationFieldComparison::FIELD_RELATIONSHIP => 'relationship',
    ];

    /**
     * Huella de los datos editables de la ficha: viaja en la ventana y se
     * compara al guardar.
     */
    public static function fingerprint(AffiliateCorporate $affiliate): string
    {
        return sha1(json_encode([...PopulationFieldComparison::baseValues($affiliate), 'status' => strtoupper(trim((string) $affiliate->status))], JSON_UNESCAPED_UNICODE) ?: '');
    }

    /**
     * Corrige la ficha emparejada con una fila del archivo.
     *
     * @param  array<string, mixed>  $values  valores finales (formato de la ventana)
     * @param  list<string>|null  $decidedFields  campos que el analista decidió: solo esos pueden quedar como diferencia
     *                                            aceptada. null = todos (la ventana muestra todos los campos).
     * @return array{changed: list<string>, accepted: list<string>, item: Item, patients: list<array<string, mixed>>, warnings: list<string>}
     */
    public static function correct(Item $item, array $values, string $note, User $user, string $expectedFingerprint, bool $confirmSamePerson = false, ?array $decidedFields = null): array
    {
        $note = self::assertNote($note);

        return self::inLockedUpdate($item, function (Item $item, CorporatePopulationUpdate $update) use ($values, $note, $user, $expectedFingerprint, $confirmSamePerson, $decidedFields): array {
            $person = is_array($item->file_data) ? $item->file_data : null;
            if ($person === null || $item->affiliate_corporate_id === null) {
                throw new RuntimeException('Esta fila no tiene una ficha de la base emparejada con el archivo.');
            }

            if (! in_array($item->action, [Item::ACTION_REVIEW, Item::ACTION_MATCH, Item::ACTION_FIX_DOCUMENT, Item::ACTION_REACTIVATE], true)) {
                throw new RuntimeException('En «'.Item::actionLabel($item->action).'» no se corrigen datos desde aquí.');
            }

            if ($item->action === Item::ACTION_REVIEW && ! $confirmSamePerson && ! ($item->details['confirmed_same_person'] ?? false)) {
                throw new RuntimeException('Confirme primero que la fila del archivo y la ficha son la misma persona.');
            }

            $affiliate = self::lockedAffiliate($item, $update);
            self::assertUnchanged($affiliate, $expectedFingerprint);

            $owner = AffiliationCorporate::query()->findOrFail($update->affiliation_corporate_id);
            $clean = self::normalize($values);
            $warnings = self::assertValid($clean, $affiliate, $owner);

            $written = self::write($affiliate, $owner, $clean);
            $file = PopulationFieldComparison::fileValues($person);
            $comparison = PopulationFieldComparison::compare($file, PopulationFieldComparison::baseValues($affiliate->refresh()));

            $details = (array) $item->details;
            $accepted = (array) ($details['accepted'] ?? []);
            foreach ($comparison as $field => $row) {
                if (! $row['counted']) {
                    unset($accepted[$field]);
                } elseif ($decidedFields === null || in_array($field, $decidedFields, true)) {
                    $accepted[$field] = ['note' => $note, 'by' => (int) $user->getKey(), 'by_name' => (string) $user->name, 'at' => now()->toIso8601String(), 'file' => $row['file'], 'base' => $row['base']];
                }
            }

            if ($confirmSamePerson) {
                $details['confirmed_same_person'] = ['by' => (int) $user->getKey(), 'by_name' => (string) $user->name, 'at' => now()->toIso8601String(), 'note' => $note];
            }

            $details['accepted'] = $accepted;
            if ($written['before'] !== []) {
                $details['corrections'] = [...(array) ($details['corrections'] ?? []), [
                    'id' => (string) Str::uuid(),
                    'affiliate_id' => (int) $affiliate->getKey(),
                    'at' => now()->toIso8601String(),
                    'by' => (int) $user->getKey(),
                    'by_name' => (string) $user->name,
                    'note' => $note,
                    'before' => $written['before'],
                    'after' => $written['after'],
                    'patients' => $written['patients'],
                    'undone_at' => null,
                ]];
            }

            $item->details = $details;
            PopulationItemReevaluator::reevaluate($item, $update);
            self::refreshItemsOf($update, (int) $affiliate->getKey(), (int) $item->getKey());
            PopulationItemReevaluator::refreshSummary($update);

            SecurityAudit::log('AUDIT_CORPORATE_POPULATION_ITEM_CORRECTED', 'business.corporate-population.correct', [
                'update_id' => $update->getKey(),
                'item_id' => $item->getKey(),
                'affiliate_id' => $affiliate->getKey(),
                'before' => $written['before'],
                'after' => $written['after'],
                'accepted' => array_keys($accepted),
                'result' => $item->action,
                'note' => $note,
            ], $user);

            return [
                'changed' => array_keys($written['after']),
                'accepted' => array_keys($accepted),
                'item' => $item,
                'patients' => $written['patients'],
                'warnings' => $warnings,
            ];
        });
    }

    /**
     * Deshace la última corrección de la fila, solo si la ficha sigue exactamente
     * como quedó después de esa corrección.
     */
    public static function undoLast(Item $item, string $note, User $user): Item
    {
        $note = self::assertNote($note);

        return self::inLockedUpdate($item, function (Item $item, CorporatePopulationUpdate $update) use ($note, $user): Item {
            $details = (array) $item->details;
            $corrections = (array) ($details['corrections'] ?? []);
            $index = self::lastOpenCorrection($corrections);
            if ($index === null) {
                throw new RuntimeException('Esta fila no tiene correcciones para deshacer.');
            }

            $correction = $corrections[$index];
            $affiliate = AffiliateCorporate::query()->lockForUpdate()->find((int) $correction['affiliate_id']);
            if (! $affiliate instanceof AffiliateCorporate) {
                throw new RuntimeException('La ficha corregida ya no existe.');
            }

            foreach ((array) $correction['after'] as $column => $value) {
                if ((string) $affiliate->getAttribute($column) !== (string) $value) {
                    throw new RuntimeException('La ficha cambió después de la corrección («'.$column.'» ahora es «'.$affiliate->getAttribute($column).'»). No se deshace para no pisar ese cambio: revíselo en «Auditoría de cambios».');
                }
            }

            $owner = AffiliationCorporate::query()->findOrFail($update->affiliation_corporate_id);
            $linked = PopulationActivityInspector::linkedPatients($owner, [$affiliate]);
            $affiliate->forceFill((array) $correction['before'])->save();

            if (array_key_exists('nro_identificacion', (array) $correction['before'])) {
                PopulationUpdateApplier::syncPatients($linked[(int) $affiliate->getKey()] ?? [], null, (string) $correction['before']['nro_identificacion']);
            }

            $corrections[$index]['undone_at'] = now()->toIso8601String();
            $corrections[$index]['undone_by_name'] = (string) $user->name;
            $corrections[$index]['undo_note'] = $note;
            $details['corrections'] = $corrections;
            $item->details = $details;

            PopulationItemReevaluator::reevaluate($item, $update);
            self::refreshItemsOf($update, (int) $affiliate->getKey(), (int) $item->getKey());
            PopulationItemReevaluator::refreshSummary($update);

            SecurityAudit::log('AUDIT_CORPORATE_POPULATION_ITEM_CORRECTION_UNDONE', 'business.corporate-population.undo', [
                'update_id' => $update->getKey(),
                'item_id' => $item->getKey(),
                'affiliate_id' => $affiliate->getKey(),
                'restored' => $correction['before'],
                'note' => $note,
            ], $user);

            return $item;
        });
    }

    /**
     * «No es la misma persona»: la fila pasa a tratarse como alta nueva y la ficha
     * que se le había emparejado, como no está en el archivo, se propone de baja.
     */
    public static function notSamePerson(Item $item, string $note, User $user): Item
    {
        $note = self::assertNote($note);

        return self::inLockedUpdate($item, function (Item $item, CorporatePopulationUpdate $update) use ($note, $user): Item {
            if ($item->action !== Item::ACTION_REVIEW || $item->affiliate_corporate_id === null || ! is_array($item->file_data)) {
                throw new RuntimeException('Solo se puede separar una fila «Revisar» que tenga una ficha emparejada.');
            }

            $affiliate = self::lockedAffiliate($item, $update);
            $details = (array) $item->details;
            $details['not_same_as'] = ['affiliate_id' => (int) $affiliate->getKey(), 'name' => trim($affiliate->first_name.' '.$affiliate->last_name), 'by' => (int) $user->getKey(), 'by_name' => (string) $user->name, 'at' => now()->toIso8601String(), 'note' => $note];
            unset($details['confirmed_same_person'], $details['accepted'], $details['differences'], $details['observed'], $details['base_name'], $details['base_document'], $details['base_status'], $details['base_birth_date']);

            $item->forceFill(['affiliate_corporate_id' => null, 'match_via' => null, 'details' => $details]);
            self::freeAffiliate($update, $affiliate, $item);
            PopulationItemReevaluator::reevaluate($item, $update);
            PopulationItemReevaluator::refreshSummary($update);

            SecurityAudit::log('AUDIT_CORPORATE_POPULATION_ITEM_SPLIT', 'business.corporate-population.not-same', [
                'update_id' => $update->getKey(),
                'item_id' => $item->getKey(),
                'affiliate_id' => $affiliate->getKey(),
                'result' => $item->action,
                'note' => $note,
            ], $user);

            return $item;
        });
    }

    /**
     * Duplicado en la base: se confirma la baja de la copia al aplicar y, si el
     * analista lo pide, la ficha que queda recibe los datos que le faltan.
     *
     * @param  list<string>  $copyFields  campos vacíos en la ficha que queda, a completar con la copia
     * @return array{item: Item, copied: list<string>}
     */
    public static function resolveDuplicate(Item $item, array $copyFields, string $note, User $user): array
    {
        $note = self::assertNote($note);

        return self::inLockedUpdate($item, function (Item $item, CorporatePopulationUpdate $update) use ($copyFields, $note, $user): array {
            if ($item->action !== Item::ACTION_DUPLICATE_BASE) {
                throw new RuntimeException('Esta fila ya no es un duplicado en la base.');
            }

            $copy = self::lockedAffiliate($item, $update);
            $originalId = (int) ($item->details['original_id'] ?? 0);
            $original = AffiliateCorporate::query()->where('affiliation_corporate_id', $update->affiliation_corporate_id)->lockForUpdate()->find($originalId);
            if (! $original instanceof AffiliateCorporate) {
                throw new RuntimeException('La ficha que se conserva (#'.$originalId.') ya no existe. Vuelva a analizar el archivo.');
            }

            $copied = [];
            if ($copyFields !== []) {
                $owner = AffiliationCorporate::query()->findOrFail($update->affiliation_corporate_id);
                $values = PopulationFieldComparison::baseValues($original);
                $fromCopy = PopulationFieldComparison::baseValues($copy);

                foreach ($copyFields as $field) {
                    if (! array_key_exists($field, $values) || filled($values[$field]) || blank($fromCopy[$field] ?? null)) {
                        continue;
                    }

                    $values[$field] = $fromCopy[$field];
                    $copied[] = $field;
                }

                if ($copied !== []) {
                    $clean = self::normalize($values);
                    self::assertValid($clean, $original, $owner);
                    $written = self::write($original, $owner, $clean);

                    $originalItem = self::itemOf($update, $originalId);
                    if ($originalItem instanceof Item) {
                        $originalDetails = (array) $originalItem->details;
                        $originalDetails['corrections'] = [...(array) ($originalDetails['corrections'] ?? []), [
                            'id' => (string) Str::uuid(),
                            'affiliate_id' => $originalId,
                            'at' => now()->toIso8601String(),
                            'by' => (int) $user->getKey(),
                            'by_name' => (string) $user->name,
                            'note' => 'Datos tomados del duplicado #'.$copy->getKey().'. '.$note,
                            'before' => $written['before'],
                            'after' => $written['after'],
                            'patients' => $written['patients'],
                            'undone_at' => null,
                        ]];
                        $originalItem->details = $originalDetails;
                        PopulationItemReevaluator::reevaluate($originalItem, $update);
                    }
                }
            }

            $details = (array) $item->details;
            $details['duplicate_resolution'] = ['kept_id' => $originalId, 'copied' => $copied, 'by' => (int) $user->getKey(), 'by_name' => (string) $user->name, 'at' => now()->toIso8601String(), 'note' => $note];
            $item->forceFill(['selected' => true, 'details' => $details])->save();
            PopulationItemReevaluator::refreshSummary($update);

            SecurityAudit::log('AUDIT_CORPORATE_POPULATION_DUPLICATE_RESOLVED', 'business.corporate-population.duplicate', [
                'update_id' => $update->getKey(),
                'item_id' => $item->getKey(),
                'copy_id' => $copy->getKey(),
                'kept_id' => $originalId,
                'copied' => $copied,
                'note' => $note,
            ], $user);

            return ['item' => $item, 'copied' => $copied];
        });
    }

    /**
     * «No es un duplicado»: son personas distintas. Como la ficha no está en el
     * archivo, queda como cualquier otra baja propuesta.
     */
    public static function notDuplicate(Item $item, string $note, User $user): Item
    {
        $note = self::assertNote($note);

        return self::inLockedUpdate($item, function (Item $item, CorporatePopulationUpdate $update) use ($note, $user): Item {
            if ($item->action !== Item::ACTION_DUPLICATE_BASE) {
                throw new RuntimeException('Esta fila ya no es un duplicado en la base.');
            }

            $affiliate = self::lockedAffiliate($item, $update);
            $details = (array) $item->details;
            $details['duplicate_resolution'] = ['not_duplicate' => true, 'by' => (int) $user->getKey(), 'by_name' => (string) $user->name, 'at' => now()->toIso8601String(), 'note' => $note];

            $item->forceFill([
                'action' => Item::ACTION_DEACTIVATE,
                'selectable' => true,
                'selected' => true,
                'reason' => 'No aparece en el archivo de población.',
                'details' => PopulationUpdateAnalyzer::observedDetails($details, $affiliate, null),
            ])->save();
            PopulationItemReevaluator::refreshSummary($update);

            SecurityAudit::log('AUDIT_CORPORATE_POPULATION_NOT_DUPLICATE', 'business.corporate-population.not-duplicate', [
                'update_id' => $update->getKey(),
                'item_id' => $item->getKey(),
                'affiliate_id' => $affiliate->getKey(),
                'note' => $note,
            ], $user);

            return $item;
        });
    }

    /**
     * Marca como revisada una fila que no cambia datos (duplicado en el archivo).
     */
    public static function markReviewed(Item $item, string $note, User $user): Item
    {
        $note = self::assertNote($note);

        return self::inLockedUpdate($item, function (Item $item, CorporatePopulationUpdate $update) use ($note, $user): Item {
            $details = (array) $item->details;
            $details['reviewed'] = ['by' => (int) $user->getKey(), 'by_name' => (string) $user->name, 'at' => now()->toIso8601String(), 'note' => $note];
            $item->forceFill(['details' => $details])->save();

            SecurityAudit::log('AUDIT_CORPORATE_POPULATION_ITEM_REVIEWED', 'business.corporate-population.review', [
                'update_id' => $update->getKey(),
                'item_id' => $item->getKey(),
                'note' => $note,
            ], $user);

            return $item;
        });
    }

    /**
     * Vuelve a cruzar la fila sin tocar datos (p. ej. después de agregar el
     * plan que faltaba para una edad).
     */
    public static function reevaluateOnly(Item $item): Item
    {
        return self::inLockedUpdate($item, function (Item $item, CorporatePopulationUpdate $update): Item {
            PopulationItemReevaluator::reevaluate($item, $update);
            PopulationItemReevaluator::refreshSummary($update);

            return $item;
        });
    }

    /**
     * Valores para «Sincronizar con el archivo»: los de la ficha, con los grupos
     * elegidos tomados del archivo solo donde de verdad no cuadran (una eñe
     * dañada en el archivo no pisa la buena de la ficha).
     *
     * @param  list<string>  $groups
     * @return array<string, string|null>
     */
    public static function syncedValues(Item $item, AffiliateCorporate $affiliate, array $groups): array
    {
        $values = PopulationFieldComparison::baseValues($affiliate);
        $comparison = PopulationFieldComparison::forPerson((array) $item->file_data, $affiliate);

        foreach (self::fieldsOf($groups) as $field) {
            if ($comparison[$field]['counted'] && filled($comparison[$field]['file'])) {
                $values[$field] = $comparison[$field]['file'];
            }
        }

        return $values;
    }

    /**
     * @param  list<string>  $groups
     * @return list<string>
     */
    public static function fieldsOf(array $groups): array
    {
        $fields = [];
        foreach ($groups as $group) {
            array_push($fields, ...(self::SYNC_GROUPS[$group] ?? []));
        }

        return $fields;
    }

    /**
     * «Sincronizar con el archivo» para varias filas «Coincide». Cada fila va en
     * su propia transacción: una que falla no arrastra a las demás.
     *
     * @param  iterable<Item>  $items
     * @param  list<string>  $groups
     * @return array{updated: int, unchanged: int, failed: list<array{name: string, error: string}>}
     */
    public static function syncWithFile(iterable $items, array $groups, string $note, User $user): array
    {
        $note = self::assertNote($note);
        $groups = array_values(array_intersect($groups, array_keys(self::SYNC_GROUPS)));
        if ($groups === []) {
            throw new RuntimeException('Elija al menos un dato a tomar del archivo.');
        }

        $out = ['updated' => 0, 'unchanged' => 0, 'failed' => []];
        $count = 0;

        foreach ($items as $item) {
            if (++$count > self::BULK_LIMIT) {
                throw new RuntimeException('Se pueden sincronizar hasta '.self::BULK_LIMIT.' filas por vez.');
            }

            $name = (string) ($item->file_data['full_name'] ?? $item->details['base_name'] ?? '#'.$item->getKey());

            try {
                if ($item->action !== Item::ACTION_MATCH || $item->affiliate_corporate_id === null) {
                    throw new RuntimeException('Ya no está en «Coincide».');
                }

                $affiliate = AffiliateCorporate::query()->find((int) $item->affiliate_corporate_id);
                if (! $affiliate instanceof AffiliateCorporate) {
                    throw new RuntimeException('La ficha ya no existe.');
                }

                $result = self::correct($item, self::syncedValues($item, $affiliate, $groups), $note, $user, self::fingerprint($affiliate), false, self::fieldsOf($groups));
                $result['changed'] === [] ? $out['unchanged']++ : $out['updated']++;
            } catch (\Throwable $exception) {
                $out['failed'][] = ['name' => $name, 'error' => $exception->getMessage()];
            }
        }

        return $out;
    }

    /**
     * Avisos que no bloquean (se muestran en la ventana y en el resultado).
     *
     * @param  array<string, string|null>  $clean  salida de normalize()
     * @return list<string>
     */
    public static function warnings(array $clean, AffiliateCorporate $affiliate, AffiliationCorporate $owner): array
    {
        $warnings = [];
        $birth = $clean[PopulationFieldComparison::FIELD_BIRTH_DATE] ?? null;
        $relationship = $clean[PopulationFieldComparison::FIELD_RELATIONSHIP] ?? null;
        $age = PopulationPerson::ageAt($birth);

        if ($age !== null && in_array($relationship, ['TITULAR', 'ESPOSA', 'ESPOSO'], true) && $age < 18) {
            $warnings[] = 'Figura como '.$relationship.' con '.$age.' años. Verifique la fecha o el parentesco.';
        }

        if ($age !== null && in_array($relationship, ['MADRE', 'PADRE'], true) && $age < 30) {
            $warnings[] = 'Figura como '.$relationship.' con '.$age.' años. Verifique la fecha o el parentesco.';
        }

        if (strlen(PopulationPerson::documentKey((string) ($clean[PopulationFieldComparison::FIELD_DOCUMENT] ?? ''))) < 5) {
            $warnings[] = 'La cédula «'.$clean[PopulationFieldComparison::FIELD_DOCUMENT].'» tiene menos de 5 dígitos: es un número de relleno y no identifica a la persona.';
        }

        $currentBirth = PopulationPerson::storedDate((string) $affiliate->birth_date);
        if ($birth !== null && $birth !== $currentBirth) {
            $tariffNote = self::tariffNote($owner, $affiliate, $age);
            if ($tariffNote !== null) {
                $warnings[] = $tariffNote;
            }
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string|null>
     */
    public static function normalize(array $values): array
    {
        $birth = $values[PopulationFieldComparison::FIELD_BIRTH_DATE] ?? null;

        return [
            PopulationFieldComparison::FIELD_DOCUMENT => PopulationPerson::storedDocument((string) ($values[PopulationFieldComparison::FIELD_DOCUMENT] ?? '')),
            PopulationFieldComparison::FIELD_FIRST_NAME => PopulationPerson::cleanName((string) ($values[PopulationFieldComparison::FIELD_FIRST_NAME] ?? '')),
            PopulationFieldComparison::FIELD_LAST_NAME => PopulationPerson::cleanName((string) ($values[PopulationFieldComparison::FIELD_LAST_NAME] ?? '')),
            PopulationFieldComparison::FIELD_BIRTH_DATE => blank($birth) ? null : PopulationPerson::date(substr((string) $birth, 0, 10)),
            PopulationFieldComparison::FIELD_SEX => PopulationFieldComparison::normalizeSex($values[PopulationFieldComparison::FIELD_SEX] ?? null),
            PopulationFieldComparison::FIELD_RELATIONSHIP => PopulationFieldComparison::normalizeRelationship($values[PopulationFieldComparison::FIELD_RELATIONSHIP] ?? null),
        ];
    }

    /**
     * Reglas que bloquean. Devuelve los avisos que no bloquean.
     *
     * @param  array<string, string|null>  $clean
     * @return list<string>
     */
    public static function assertValid(array $clean, AffiliateCorporate $affiliate, AffiliationCorporate $owner): array
    {
        foreach ([PopulationFieldComparison::FIELD_FIRST_NAME, PopulationFieldComparison::FIELD_LAST_NAME] as $field) {
            $value = (string) $clean[$field];
            if ($value === '') {
                throw new RuntimeException('Los '.mb_strtolower(PopulationFieldComparison::label($field)).' no pueden quedar vacíos.');
            }

            if (mb_strlen($value) > 100) {
                throw new RuntimeException('Los '.mb_strtolower(PopulationFieldComparison::label($field)).' tienen más de 100 caracteres.');
            }

            if (str_contains($value, '?')) {
                throw new RuntimeException('Los '.mb_strtolower(PopulationFieldComparison::label($field)).' traen un carácter dañado («'.$value.'»): casi siempre es una Ñ o una tilde que el archivo perdió. Escríbalo bien con «Corregir datos».');
            }

            if (preg_match("/^[\\p{L} .'\\-]+$/u", $value) !== 1) {
                throw new RuntimeException('Los '.mb_strtolower(PopulationFieldComparison::label($field)).' solo pueden tener letras, espacios, puntos, apóstrofos o guiones («'.$value.'»).');
            }
        }

        $document = (string) $clean[PopulationFieldComparison::FIELD_DOCUMENT];
        if ($document === '' || preg_match('/^\d{1,10}(-\d{1,2}){0,2}$/', $document) !== 1) {
            throw new RuntimeException('La cédula «'.$document.'» no es válida. Use solo números (adultos) o el formato de menor 012345678-12.');
        }

        $birth = $clean[PopulationFieldComparison::FIELD_BIRTH_DATE];
        if ($birth === null && filled($affiliate->birth_date)) {
            throw new RuntimeException('La fecha de nacimiento no puede quedar vacía.');
        }

        if ($birth !== null && ($birth > now()->toDateString() || $birth < '1900-01-01')) {
            throw new RuntimeException('La fecha de nacimiento '.date('d/m/Y', (int) strtotime($birth)).' no es posible.');
        }

        $sex = $clean[PopulationFieldComparison::FIELD_SEX];
        $relationship = $clean[PopulationFieldComparison::FIELD_RELATIONSHIP];
        if ($sex === 'MASCULINO' && in_array($relationship, self::FEMALE_RELATIONSHIPS, true)
            || $sex === 'FEMENINO' && in_array($relationship, self::MALE_RELATIONSHIPS, true)) {
            throw new RuntimeException('El parentesco '.$relationship.' no corresponde al sexo '.$sex.'.');
        }

        $docKey = PopulationPerson::documentKey($document);
        if (PopulationPerson::isIdentifyingDocument($docKey) && ! PopulationPerson::isMinorDocument($document)) {
            $taken = AffiliateCorporate::query()
                ->where('affiliation_corporate_id', $owner->getKey())
                ->whereKeyNot($affiliate->getKey())
                ->get(['id', 'first_name', 'last_name', 'nro_identificacion', 'status'])
                ->first(static fn (AffiliateCorporate $other): bool => PopulationPerson::documentKey((string) $other->nro_identificacion) === $docKey
                    && in_array(strtoupper(trim((string) $other->status)), PopulationMatcher::COUNTABLE_STATUSES, true));

            if ($taken instanceof AffiliateCorporate) {
                throw new RuntimeException('La cédula '.$document.' ya la tiene «'.trim($taken->first_name.' '.$taken->last_name).'» (#'.$taken->getKey().', '.$taken->status.') en esta misma afiliación. Si es un duplicado, resuélvalo primero en la pestaña «Duplicado en la base».');
            }
        }

        return self::warnings($clean, $affiliate, $owner);
    }

    /**
     * @param  array<string, mixed>  $corrections
     */
    public static function lastOpenCorrection(array $corrections): ?int
    {
        for ($i = count($corrections) - 1; $i >= 0; $i--) {
            if (($corrections[$i]['undone_at'] ?? null) === null) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Guarda en la ficha solo lo que cambió de verdad (no reescribe «M» como
     * «MASCULINO» ni una fecha igual con otro formato) y sincroniza la cédula
     * del paciente de telemedicina ligado.
     *
     * @param  array<string, string|null>  $clean
     * @return array{before: array<string, mixed>, after: array<string, mixed>, patients: list<array<string, mixed>>}
     */
    private static function write(AffiliateCorporate $affiliate, AffiliationCorporate $owner, array $clean): array
    {
        $current = PopulationFieldComparison::baseValues($affiliate);
        $changes = [];

        foreach (self::COLUMNS as $field => $column) {
            $new = $clean[$field];
            $same = match ($field) {
                PopulationFieldComparison::FIELD_BIRTH_DATE => $new === $current[$field],
                default => (string) $new === (string) $current[$field],
            };

            if ($same) {
                continue;
            }

            $changes[$column] = $field === PopulationFieldComparison::FIELD_BIRTH_DATE ? PopulationPerson::toStoredBirthDate($new) : $new;
        }

        if ($changes === []) {
            return ['before' => [], 'after' => [], 'patients' => []];
        }

        $before = [];
        foreach (array_keys($changes) as $column) {
            $before[$column] = $affiliate->getRawOriginal($column);
        }

        $linked = isset($changes['nro_identificacion']) ? PopulationActivityInspector::linkedPatients($owner, [$affiliate]) : [];

        $affiliate->forceFill($changes)->save();

        $patients = isset($changes['nro_identificacion'])
            ? PopulationUpdateApplier::syncPatients($linked[(int) $affiliate->getKey()] ?? [], null, (string) $changes['nro_identificacion'])
            : [];

        return ['before' => $before, 'after' => $changes, 'patients' => $patients];
    }

    private static function tariffNote(AffiliationCorporate $owner, AffiliateCorporate $affiliate, ?int $newAge): ?string
    {
        if ($newAge === null) {
            return null;
        }

        $planRows = CorporateAffiliatePlanSynchronizer::planRowsFor($owner);
        $currentRow = CorporateAffiliatePlanSynchronizer::resolvePlanRowForAffiliate($owner, $affiliate, $planRows)['row'];
        $probe = (new AffiliateCorporate)->forceFill(['age' => $newAge, 'plan_id' => $affiliate->plan_id]);
        $newRow = CorporateAffiliatePlanSynchronizer::resolvePlanRowForAffiliate($owner, $probe, $planRows)['row'];

        $range = static fn (?AfilliationCorporatePlan $row): string => $row instanceof AfilliationCorporatePlan
            ? 'rango '.$row->ageRange?->age_init.' a '.$row->ageRange?->age_end.' años, US$ '.number_format((float) $row->fee, 2, ',', '.')
            : 'sin rango contratado';

        if ($newRow?->getKey() === $currentRow?->getKey()) {
            return null;
        }

        return 'Con la fecha nueva tiene '.$newAge.' años y le correspondería: '.$range($newRow).'. Hoy paga US$ '.number_format((float) $affiliate->fee, 2, ',', '.').' ('.$range($currentRow).'). La tarifa no cambia ahora: se recalcula en la renovación.';
    }

    /**
     * Ficha que no está en el archivo después de separar la fila: si nadie más
     * la reclama, se propone de baja como cualquier otra ausente.
     */
    private static function freeAffiliate(CorporatePopulationUpdate $update, AffiliateCorporate $affiliate, Item $from): void
    {
        $claimed = Item::query()
            ->where('corporate_population_update_id', $update->getKey())
            ->whereKeyNot($from->getKey())
            ->where('affiliate_corporate_id', $affiliate->getKey())
            ->exists();

        if ($claimed || ! in_array(strtoupper(trim((string) $affiliate->status)), PopulationMatcher::COUNTABLE_STATUSES, true)) {
            return;
        }

        $owner = AffiliationCorporate::query()->findOrFail($update->affiliation_corporate_id);
        $proposal = PopulationUpdateAnalyzer::blockOpenActivity($owner, [[
            'action' => Item::ACTION_DEACTIVATE,
            'selectable' => true,
            'selected' => true,
            'affiliate_corporate_id' => (int) $affiliate->getKey(),
            'reason' => 'No aparece en el archivo de población.',
            'details' => ['freed_from_item' => (int) $from->getKey()],
        ]], collect([$affiliate]))[0];

        Item::query()->create([
            'corporate_population_update_id' => $update->getKey(),
            'action' => $proposal['action'],
            'selectable' => $proposal['selectable'],
            'selected' => $proposal['selected'],
            'file_row' => null,
            'file_data' => null,
            'affiliate_corporate_id' => (int) $affiliate->getKey(),
            'match_via' => null,
            'reason' => $proposal['reason'],
            'details' => PopulationUpdateAnalyzer::observedDetails((array) $proposal['details'], $affiliate, null),
            'apply_status' => Item::APPLY_PENDING,
        ]);
    }

    /**
     * Otras filas de la carga que muestran la misma ficha: se refresca su foto
     * para que no se apliquen con una huella vieja.
     */
    private static function refreshItemsOf(CorporatePopulationUpdate $update, int $affiliateId, int $exceptItemId): void
    {
        Item::query()
            ->where('corporate_population_update_id', $update->getKey())
            ->where('affiliate_corporate_id', $affiliateId)
            ->whereKeyNot($exceptItemId)
            ->get()
            ->each(static function (Item $other) use ($update): void {
                PopulationItemReevaluator::reevaluate($other, $update);
            });
    }

    private static function itemOf(CorporatePopulationUpdate $update, int $affiliateId): ?Item
    {
        return Item::query()
            ->where('corporate_population_update_id', $update->getKey())
            ->where('affiliate_corporate_id', $affiliateId)
            ->whereNotNull('file_row')
            ->first();
    }

    /**
     * @template T
     *
     * @param  callable(Item, CorporatePopulationUpdate): T  $callback
     * @return T
     */
    private static function inLockedUpdate(Item $item, callable $callback): mixed
    {
        return DB::transaction(static function () use ($item, $callback): mixed {
            $update = CorporatePopulationUpdate::query()->whereKey($item->corporate_population_update_id)->lockForUpdate()->firstOrFail();
            if ($update->status !== CorporatePopulationUpdate::STATUS_ANALYZED) {
                throw new RuntimeException('La carga ya no se puede modificar (estatus: '.$update->statusLabel().').');
            }

            $locked = Item::query()->whereKey($item->getKey())->where('corporate_population_update_id', $update->getKey())->lockForUpdate()->firstOrFail();

            return $callback($locked, $update);
        });
    }

    private static function lockedAffiliate(Item $item, CorporatePopulationUpdate $update): AffiliateCorporate
    {
        $affiliate = AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $update->affiliation_corporate_id)
            ->lockForUpdate()
            ->find((int) $item->affiliate_corporate_id);

        if (! $affiliate instanceof AffiliateCorporate) {
            throw new RuntimeException('La ficha #'.$item->affiliate_corporate_id.' ya no existe en esta afiliación. Vuelva a analizar el archivo.');
        }

        return $affiliate;
    }

    private static function assertUnchanged(AffiliateCorporate $affiliate, string $expected): void
    {
        if (! hash_equals(self::fingerprint($affiliate), $expected)) {
            throw new RuntimeException('Alguien modificó esta ficha mientras usted la revisaba. Cierre la ventana y vuelva a abrirla para ver los datos actuales.');
        }
    }

    private static function assertNote(string $note): string
    {
        $note = trim($note);
        if (mb_strlen($note) < self::NOTE_MIN_LENGTH) {
            throw new RuntimeException('Explique el motivo en al menos '.self::NOTE_MIN_LENGTH.' caracteres.');
        }

        return mb_substr($note, 0, 1000);
    }
}
