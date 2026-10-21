<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\Population;

use App\Models\AffiliateCorporate;
use App\Models\CorporatePopulationUpdateItem as Item;
use Illuminate\Support\Collection;

/**
 * Cruza la población del archivo (modo población completa) con los afiliados
 * de la afiliación y propone qué hacer con cada persona. No escribe nada.
 *
 * Pasadas, de la más segura a la menos segura:
 *  1. Cédula real (5+ dígitos), confirmada con nombre parecido (≥60 %) o misma
 *     fecha de nacimiento. Misma cédula con otro nombre → Revisar.
 *  2. Misma fecha de nacimiento y nombre parecido (≥80 %), único y con ventaja
 *     clara sobre el segundo (menores: la cédula del archivo es la del
 *     representante con sufijo y la base usa números de relleno).
 *  3. Mismo nombre exacto, sin fecha que lo confirme → Revisar (nunca se decide solo).
 *  4. Restos: un «nuevo» y un «sobrante» con la misma fecha, la cédula con el
 *     mismo prefijo y un apellido en común son la misma persona → Revisar.
 *
 * Luego: quien sobra en la base y es copia de alguien ya emparejado → Duplicado
 * en la base; misma persona dos veces en el archivo → Duplicado en el archivo.
 */
final class PopulationMatcher
{
    /** Estados que cuentan como población vigente. */
    public const COUNTABLE_STATUSES = ['ACTIVO', 'PRE-APROBADA'];

    /**
     * @param  list<array<string, mixed>>  $persons  salida de PopulationPerson::fromRow
     * @param  Collection<int, AffiliateCorporate>  $affiliates  todos los afiliados de la afiliación
     * @return list<array<string, mixed>>
     */
    public static function match(array $persons, Collection $affiliates): array
    {
        $base = [];
        foreach ($affiliates as $affiliate) {
            $base[(int) $affiliate->id] = [
                'id' => (int) $affiliate->id,
                'name' => trim($affiliate->first_name.' '.$affiliate->last_name),
                'document' => (string) $affiliate->nro_identificacion,
                'doc_key' => PopulationPerson::documentKey((string) $affiliate->nro_identificacion),
                'birth_date' => PopulationPerson::storedDate((string) $affiliate->birth_date),
                'status' => strtoupper(trim((string) $affiliate->status)),
            ];
        }

        $items = [];
        $valid = [];

        foreach ($persons as $index => $person) {
            if ($person['problem'] !== null) {
                $items[] = self::item(Item::ACTION_INVALID, $person, null, null, (string) $person['problem']);

                continue;
            }

            $valid[$index] = $person;
        }

        /* Duplicados dentro del archivo: misma cédula real y mismo nombre. */
        $seen = [];
        foreach ($valid as $index => $person) {
            if (! PopulationPerson::isIdentifyingDocument($person['doc_key'])) {
                continue;
            }

            $key = $person['doc_key'].'|'.implode(' ', PopulationPerson::tokens($person['full_name']));
            if (isset($seen[$key])) {
                $items[] = self::item(Item::ACTION_DUPLICATE_FILE, $person, null, null, 'La misma persona aparece de nuevo en la fila '.$seen[$key].' del archivo. Solo se toma una vez.', ['first_row' => $seen[$key]]);
                unset($valid[$index]);

                continue;
            }

            $seen[$key] = $person['row'];
        }

        $matched = [];   // índice persona => [id, via]
        $usedBase = [];

        /* Pasada 1: cédula. */
        $byDoc = [];
        foreach ($base as $id => $row) {
            if (PopulationPerson::isIdentifyingDocument($row['doc_key'])) {
                $byDoc[$row['doc_key']][] = $id;
            }
        }

        foreach ($valid as $index => $person) {
            if (! PopulationPerson::isIdentifyingDocument($person['doc_key']) || ! isset($byDoc[$person['doc_key']])) {
                continue;
            }

            $best = null;
            foreach ($byDoc[$person['doc_key']] as $id) {
                if (isset($usedBase[$id])) {
                    continue;
                }

                $similarity = PopulationPerson::similarity($person['first_name'], $person['last_name'], $base[$id]['name']);
                $sameBirth = $person['birth_date'] !== null && $person['birth_date'] === $base[$id]['birth_date'];
                $score = $similarity + ($sameBirth ? 0.5 : 0) + (in_array($base[$id]['status'], self::COUNTABLE_STATUSES, true) ? 0.01 : 0);

                if ($best === null || $score > $best['score']) {
                    $best = ['id' => $id, 'score' => $score, 'similarity' => $similarity, 'same_birth' => $sameBirth];
                }
            }

            if ($best === null) {
                continue;
            }

            if ($best['similarity'] >= 0.6 || $best['same_birth']) {
                $matched[$index] = ['id' => $best['id'], 'via' => 'cedula'];
            } else {
                $items[] = self::item(Item::ACTION_REVIEW, $person, $best['id'], 'cedula', 'Misma cédula que «'.$base[$best['id']]['name'].'», pero el nombre no coincide. Revise si es la misma persona.');
                unset($valid[$index]);
            }

            $usedBase[$best['id']] = true;
        }

        /* Pasada 2: fecha de nacimiento + nombre parecido. */
        $byBirth = [];
        foreach ($base as $id => $row) {
            if ($row['birth_date'] !== null && ! isset($usedBase[$id])) {
                $byBirth[$row['birth_date']][] = $id;
            }
        }

        foreach ($valid as $index => $person) {
            if (isset($matched[$index]) || $person['birth_date'] === null || ! isset($byBirth[$person['birth_date']])) {
                continue;
            }

            $candidates = [];
            foreach ($byBirth[$person['birth_date']] as $id) {
                if (isset($usedBase[$id])) {
                    continue;
                }

                $similarity = PopulationPerson::similarity($person['first_name'], $person['last_name'], $base[$id]['name']);
                if ($similarity >= 0.8) {
                    $candidates[] = [$id, $similarity];
                }
            }

            usort($candidates, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

            if ($candidates !== [] && (count($candidates) === 1 || $candidates[0][1] - $candidates[1][1] >= 0.1)) {
                $matched[$index] = ['id' => $candidates[0][0], 'via' => 'nombre_y_fecha'];
                $usedBase[$candidates[0][0]] = true;
            }
        }

        /* Pasada 3: mismo nombre exacto (único en ambos lados) → Revisar. */
        $baseByName = [];
        foreach ($base as $id => $row) {
            if (! isset($usedBase[$id])) {
                $baseByName[implode(' ', PopulationPerson::tokens($row['name']))][] = $id;
            }
        }

        $fileByName = [];
        foreach ($valid as $index => $person) {
            if (! isset($matched[$index])) {
                $fileByName[implode(' ', PopulationPerson::tokens($person['full_name']))][] = $index;
            }
        }

        foreach ($fileByName as $key => $indexes) {
            if ($key === '' || count($indexes) !== 1 || count($baseByName[$key] ?? []) !== 1) {
                continue;
            }

            $index = $indexes[0];
            $id = $baseByName[$key][0];
            $items[] = self::item(Item::ACTION_REVIEW, $valid[$index], $id, 'solo_nombre', 'El nombre coincide con «'.$base[$id]['name'].'», pero ni la cédula ni la fecha de nacimiento lo confirman. Revise si es la misma persona y corrija sus datos.');
            $usedBase[$id] = true;
            unset($valid[$index]);
        }

        /* Pasada 4: «nuevo» y «sobrante» que son la misma persona → Revisar. */
        foreach ($valid as $index => $person) {
            if (isset($matched[$index]) || $person['birth_date'] === null) {
                continue;
            }

            foreach ($base as $id => $row) {
                if (isset($usedBase[$id]) || $row['birth_date'] !== $person['birth_date']) {
                    continue;
                }

                if (self::samePrefix($person['doc_key'], $row['doc_key']) && PopulationPerson::tokenOverlap($person['full_name'], $row['name']) >= 1) {
                    $items[] = self::item(Item::ACTION_REVIEW, $person, $id, 'prefijo_y_fecha', 'Parece la misma persona que «'.$row['name'].'» (misma fecha y cédula parecida). No se agrega: corrija nombre y cédula en la ficha existente.');
                    $usedBase[$id] = true;
                    unset($valid[$index]);

                    break;
                }
            }
        }

        /* Emparejados: coincide, reactivar o corregir cédula. */
        $docOwners = [];
        foreach ($base as $id => $row) {
            if (PopulationPerson::isIdentifyingDocument($row['doc_key'])) {
                $docOwners[$row['doc_key']][] = $id;
            }
        }

        foreach ($matched as $index => $match) {
            $person = $valid[$index];
            $row = $base[$match['id']];
            $countable = in_array($row['status'], self::COUNTABLE_STATUSES, true);
            $fixTo = self::documentFix($person, $row, $docOwners);

            if ($person['excluded']) {
                $items[] = $countable
                    ? self::item(Item::ACTION_DEACTIVATE, $person, $row['id'], $match['via'], 'El archivo lo marca de baja.', [], true)
                    : self::item(Item::ACTION_MATCH, $person, $row['id'], $match['via'], 'Marcado de baja en el archivo y ya inactivo en la base.');

                continue;
            }

            if (! $countable) {
                $items[] = self::item(Item::ACTION_REACTIVATE, $person, $row['id'], $match['via'], 'Está en el archivo y en la base figura '.$row['status'].'.', $fixTo !== null ? ['fix_document_to' => $fixTo] : [], true);

                continue;
            }

            if ($fixTo !== null) {
                $items[] = self::item(Item::ACTION_FIX_DOCUMENT, $person, $row['id'], $match['via'], 'Cédula en la base: «'.$row['document'].'». En el archivo: «'.$person['document'].'».', ['fix_document_to' => $fixTo], true);

                continue;
            }

            $items[] = self::item(Item::ACTION_MATCH, $person, $row['id'], $match['via'], null);
        }

        /* Nuevos (el plan se resuelve en el analizador). */
        foreach ($valid as $index => $person) {
            if (isset($matched[$index])) {
                continue;
            }

            $items[] = $person['excluded']
                ? self::item(Item::ACTION_INVALID, $person, null, null, 'El archivo lo marca de baja y no existe en la base: no se hace nada.')
                : self::item(Item::ACTION_ADD, $person, null, null, 'No existe en la afiliación.', [], true);
        }

        /* Sobrantes en la base: duplicado de alguien emparejado o baja. */
        $matchedBase = [];
        foreach ($matched as $match) {
            $matchedBase[$match['id']] = $base[$match['id']];
        }

        foreach ($base as $id => $row) {
            if (isset($usedBase[$id]) || ! in_array($row['status'], self::COUNTABLE_STATUSES, true)) {
                continue;
            }

            $original = self::duplicateOf($row, $matchedBase);
            if ($original !== null) {
                $sameDocument = $row['doc_key'] !== '' && $row['doc_key'] === $original['doc_key'];
                $items[] = self::baseItem(Item::ACTION_DUPLICATE_BASE, $row, 'Copia de «'.$original['name'].'» (#'.$original['id'].'), que sí está en el archivo. '.($sameDocument ? 'Misma cédula.' : 'Cédula distinta: revise cuál conservar.'), ['original_id' => $original['id']], $sameDocument);

                continue;
            }

            $items[] = self::baseItem(Item::ACTION_DEACTIVATE, $row, 'No aparece en el archivo de población.', [], true);
        }

        return $items;
    }

    /**
     * Cédula a corregir: solo si el archivo trae una cédula de adulto real,
     * distinta de la de la base y que nadie más usa en la afiliación.
     *
     * @param  array<string, mixed>  $person
     * @param  array<string, mixed>  $row
     * @param  array<string, list<int>>  $docOwners
     */
    private static function documentFix(array $person, array $row, array $docOwners): ?string
    {
        if ($person['minor_document'] || ! PopulationPerson::isIdentifyingDocument($person['doc_key']) || $person['doc_key'] === $row['doc_key']) {
            return null;
        }

        $owners = array_diff($docOwners[$person['doc_key']] ?? [], [$row['id']]);

        return $owners === [] ? PopulationPerson::storedDocument($person['document']) : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, array<string, mixed>>  $matchedBase
     * @return array<string, mixed>|null
     */
    private static function duplicateOf(array $row, array $matchedBase): ?array
    {
        foreach ($matchedBase as $original) {
            if ($row['doc_key'] !== '' && PopulationPerson::isIdentifyingDocument($row['doc_key']) && $row['doc_key'] === $original['doc_key']
                && PopulationPerson::tokenOverlap($row['name'], $original['name']) >= 2) {
                return $original;
            }

            if (self::samePrefix($row['doc_key'], $original['doc_key']) && PopulationPerson::tokenOverlap($row['name'], $original['name']) >= 2) {
                return $original;
            }
        }

        return null;
    }

    private static function samePrefix(string $a, string $b): bool
    {
        return strlen($a) >= 7 && strlen($b) >= 7 && substr($a, 0, 7) === substr($b, 0, 7);
    }

    /**
     * @param  array<string, mixed>  $person
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private static function item(string $action, array $person, ?int $affiliateId, ?string $via, ?string $reason, array $details = [], bool $selectable = false): array
    {
        return [
            'action' => $action,
            'selectable' => $selectable,
            'selected' => $selectable,
            'file_row' => $person['row'],
            'file_data' => $person,
            'affiliate_corporate_id' => $affiliateId,
            'match_via' => $via,
            'reason' => $reason,
            'details' => $details,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private static function baseItem(string $action, array $row, string $reason, array $details, bool $selected): array
    {
        return [
            'action' => $action,
            'selectable' => true,
            'selected' => $selected,
            'file_row' => null,
            'file_data' => null,
            'affiliate_corporate_id' => $row['id'],
            'match_via' => null,
            'reason' => $reason,
            'details' => $details,
        ];
    }
}
