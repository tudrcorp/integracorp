<?php

declare(strict_types=1);

namespace App\Support\Affiliations\PersonIdentity;

use App\Enums\PersonIdentitySource;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\CompanyAssociate;
use App\Models\PersonIdentityFinding as Finding;
use App\Models\PersonIdentityFindingEvent as Event;
use App\Models\User;
use App\Services\CorporateAffiliateRemovalService;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\Companies\CompanyAssociateStatusManager;
use App\Support\Filament\UserNavigationAccess;
use App\Support\Operations\AffiliatePersonalDataUpdater;
use App\Support\SecurityAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Lo que el analista hace con un caso del detector de personas duplicadas. Todo exige el permiso
 * de Negocios `personas-duplicadas` y una nota; todo queda en la bitácora del caso y en la auditoría.
 *
 * - Corregir los datos de uno de los registros (nombre, cédula, nacimiento). Los afiliados pasan por
 *   {@see AffiliatePersonalDataUpdater}: el paciente de telemedicina vinculado se actualiza igual y
 *   Afiliaciones recibe el aviso. Se puede revertir mientras nadie haya vuelto a tocar esos datos.
 * - Dar de baja uno de los registros, por la misma vía que usa Negocios (recalcula montos y población).
 *   No se revierte desde aquí.
 * - Marcar «no es la misma persona», reabrir.
 *
 * Una corrección que dejaría a la persona duplicada con un tercero la bloquea el mismo candado.
 */
final class PersonIdentityResolver
{
    public const PERMISSION_SLUG = 'personas-duplicadas';

    public const MODULE = 'NEGOCIOS';

    public const NOTE_MIN_LENGTH = 15;

    /**
     * Campos que se corrigen desde el detector, por población.
     *
     * @return array<string, string>
     */
    public static function correctableFields(PersonIdentitySource $source): array
    {
        return match ($source) {
            PersonIdentitySource::Corporate => ['first_name' => 'Nombres', 'last_name' => 'Apellidos', 'nro_identificacion' => 'Cédula', 'birth_date' => 'Fecha de nacimiento'],
            PersonIdentitySource::Individual => ['full_name' => 'Nombre completo', 'nro_identificacion' => 'Cédula', 'birth_date' => 'Fecha de nacimiento'],
            PersonIdentitySource::Associate => ['full_name' => 'Nombre completo', 'identity_card' => 'Cédula o pasaporte', 'birth_date' => 'Fecha de nacimiento'],
        };
    }

    public static function userCan(?User $user): bool
    {
        return $user instanceof User
            && UserNavigationAccess::canAccessMenuItem($user, self::MODULE, [self::PERMISSION_SLUG]);
    }

    /**
     * El registro vivo de un lado del caso (`a` o `b`).
     */
    public static function modelOf(Finding $finding, string $side): ?Model
    {
        $source = $side === 'b' ? $finding->sourceB() : $finding->sourceA();
        $id = $side === 'b' ? $finding->record_b_id : $finding->record_a_id;

        return $source !== null && $id !== null ? $source->modelClass()::query()->find($id) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array{label: string, before: mixed, after: mixed}>
     */
    public static function correct(Finding $finding, string $side, array $data, string $note, User $actor): array
    {
        self::assertCan($actor);
        $note = self::assertNote($note);
        $side = self::assertSide($finding, $side);

        return DB::transaction(function () use ($finding, $side, $data, $note, $actor): array {
            $locked = self::lockFinding($finding);
            $model = self::modelOf($locked, $side) ?? throw new RuntimeException('El registro ya no existe.');
            $source = PersonIdentitySource::fromModel($model);
            $data = array_intersect_key($data, self::correctableFields($source));

            $changes = self::applyPersonalData($model, $data, $actor);

            if ($changes === []) {
                throw new RuntimeException('Los datos que escribió son iguales a los que ya tiene el registro. No hay nada que corregir.');
            }

            $locked->forceFill(self::resolvedValues(Finding::STATUS_CORRECTED, $note, $actor))->save();
            PersonIdentityScanner::event($locked, Event::CORRECTED, $note, [
                'side' => $side,
                'source' => $source->value,
                'record_id' => (int) $model->getKey(),
                'changes' => $changes,
                'lines' => self::changeLines($changes),
            ], $actor);

            SecurityAudit::log('AUDIT_BUSINESS_PERSON_IDENTITY_CORRECTED', 'business.person-identity-findings.correct', [
                'finding_id' => $locked->getKey(),
                'source' => $source->value,
                'record_id' => $model->getKey(),
                'changes' => $changes,
                'note' => $note,
            ], $actor);

            PersonIdentityScanner::rescanFinding($locked, $actor);

            return $changes;
        });
    }

    /**
     * La última corrección de datos del caso que todavía se puede deshacer, o null.
     */
    public static function revertibleCorrection(Finding $finding): ?Event
    {
        $last = $finding->events()
            ->whereIn('action', [Event::CORRECTED, Event::CORRECTION_REVERTED])
            ->first();

        return $last?->action === Event::CORRECTED ? $last : null;
    }

    /**
     * Motivos por los que no se puede revertir la corrección (alguien volvió a tocar esos datos).
     *
     * @return list<string>
     */
    public static function revertBlockers(Event $correction): array
    {
        $payload = (array) $correction->payload;
        $source = PersonIdentitySource::tryFrom((string) ($payload['source'] ?? ''));
        $model = $source?->modelClass()::query()->find((int) ($payload['record_id'] ?? 0));

        if ($model === null) {
            return ['El registro corregido ya no existe.'];
        }

        $blockers = [];

        foreach ((array) ($payload['changes'] ?? []) as $field => $change) {
            if (self::comparable($field, $model->getAttribute($field)) !== self::comparable($field, $change['after'] ?? null)) {
                $blockers[] = ($change['label'] ?? $field).' cambió después de la corrección (hoy: «'.trim((string) $model->getAttribute($field)).'»).';
            }
        }

        return $blockers;
    }

    public static function revertCorrection(Finding $finding, string $note, User $actor): void
    {
        self::assertCan($actor);
        $note = self::assertNote($note);

        DB::transaction(function () use ($finding, $note, $actor): void {
            $locked = self::lockFinding($finding);
            $correction = self::revertibleCorrection($locked) ?? throw new RuntimeException('Este caso no tiene una corrección de datos para revertir.');
            $blockers = self::revertBlockers($correction);

            if ($blockers !== []) {
                throw new RuntimeException('No se puede revertir: '.implode(' ', $blockers));
            }

            $payload = (array) $correction->payload;
            $source = PersonIdentitySource::from((string) $payload['source']);
            $model = $source->modelClass()::query()->findOrFail((int) $payload['record_id']);
            $previous = [];

            foreach ((array) $payload['changes'] as $field => $change) {
                $previous[$field] = $change['before'];
            }

            $changes = PersonIdentityGuard::allowingRepeatedName(fn (): array => self::applyPersonalData($model, $previous, $actor));

            $locked->forceFill(self::resolvedValues(Finding::STATUS_OPEN, null, null))->save();
            PersonIdentityScanner::event($locked, Event::CORRECTION_REVERTED, $note, [
                'reverted_event_id' => $correction->getKey(),
                'source' => $source->value,
                'record_id' => (int) $model->getKey(),
                'changes' => $changes,
                'lines' => self::changeLines($changes),
            ], $actor);

            SecurityAudit::log('AUDIT_BUSINESS_PERSON_IDENTITY_CORRECTION_REVERTED', 'business.person-identity-findings.revert', [
                'finding_id' => $locked->getKey(),
                'reverted_event_id' => $correction->getKey(),
                'changes' => $changes,
                'note' => $note,
            ], $actor);

            PersonIdentityScanner::rescanFinding($locked, $actor);
        });
    }

    /**
     * Da de baja uno de los dos registros por la vía normal de su población.
     *
     * @return string qué se hizo, para mostrarlo
     */
    public static function deactivate(Finding $finding, string $side, string $note, User $actor): string
    {
        self::assertCan($actor);
        $note = self::assertNote($note);
        $side = self::assertSide($finding, $side);

        if (! ($finding->kindEnum()?->isDuplicate() ?? false)) {
            throw new RuntimeException('Un nombre cargado dos veces se corrige, no se da de baja.');
        }

        return DB::transaction(function () use ($finding, $side, $note, $actor): string {
            $locked = self::lockFinding($finding);
            $model = self::modelOf($locked, $side) ?? throw new RuntimeException('El registro ya no existe.');
            $record = PersonIdentityRecord::fromModel($model);

            if (! $record->isActive()) {
                throw new RuntimeException('Ese registro ya no está vigente.');
            }

            $summary = match (true) {
                $model instanceof Affiliate => self::deactivateIndividual($model),
                $model instanceof AffiliateCorporate => self::deactivateCorporate($model),
                $model instanceof CompanyAssociate => self::annulAssociate($model, $note),
                default => throw new RuntimeException('Tipo de registro desconocido.'),
            };

            $locked->forceFill(self::resolvedValues(Finding::STATUS_CORRECTED, $note, $actor))->save();
            PersonIdentityScanner::event($locked, Event::DEACTIVATED, $note, [
                'side' => $side,
                'source' => $record->source->value,
                'record_id' => $record->id,
                'where' => $record->whereDescription(),
                'lines' => [$summary],
            ], $actor);

            SecurityAudit::log('AUDIT_BUSINESS_PERSON_IDENTITY_DEACTIVATED', 'business.person-identity-findings.deactivate', [
                'finding_id' => $locked->getKey(),
                'source' => $record->source->value,
                'record_id' => $record->id,
                'where' => $record->whereDescription(),
                'summary' => $summary,
                'note' => $note,
            ], $actor);

            PersonIdentityScanner::rescanFinding($locked, $actor);

            return $summary;
        });
    }

    public static function dismiss(Finding $finding, string $note, User $actor): void
    {
        self::assertCan($actor);
        $note = self::assertNote($note);

        DB::transaction(function () use ($finding, $note, $actor): void {
            $locked = self::lockFinding($finding);

            if ($locked->status !== Finding::STATUS_OPEN) {
                throw new RuntimeException('Solo se marca un caso abierto.');
            }

            $kind = $locked->kindEnum() ?? throw new RuntimeException('Tipo de caso desconocido.');
            $a = PersonIdentityScanner::currentRecord($locked->sourceA(), (int) $locked->record_a_id) ?? throw new RuntimeException('Uno de los registros ya no existe. Vuelva a revisar.');
            $b = $locked->record_b_id !== null
                ? (PersonIdentityScanner::currentRecord($locked->sourceB(), (int) $locked->record_b_id) ?? throw new RuntimeException('Uno de los registros ya no existe. Vuelva a revisar.'))
                : null;

            $locked->forceFill([
                ...self::resolvedValues(Finding::STATUS_DISMISSED, $note, $actor),
                'dismissed_fingerprint' => Finding::fingerprintFor($kind, $a, $b),
            ])->save();
            PersonIdentityScanner::event($locked, Event::DISMISSED, $note, null, $actor);

            SecurityAudit::log('AUDIT_BUSINESS_PERSON_IDENTITY_DISMISSED', 'business.person-identity-findings.dismiss', [
                'finding_id' => $locked->getKey(),
                'pair_key' => $locked->pair_key,
                'note' => $note,
            ], $actor);
        });
    }

    public static function reopen(Finding $finding, string $note, User $actor): void
    {
        self::assertCan($actor);
        $note = self::assertNote($note);

        DB::transaction(function () use ($finding, $note, $actor): void {
            $locked = self::lockFinding($finding);

            if ($locked->status !== Finding::STATUS_DISMISSED) {
                throw new RuntimeException('Solo se reabre un caso marcado como «no es duplicado».');
            }

            $locked->forceFill([
                ...self::resolvedValues(Finding::STATUS_OPEN, null, null),
                'dismissed_fingerprint' => null,
            ])->save();
            PersonIdentityScanner::event($locked, Event::REOPENED, $note, null, $actor);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array{label: string, before: mixed, after: mixed}>
     */
    private static function applyPersonalData(Model $model, array $data, User $actor): array
    {
        if ($model instanceof Affiliate || $model instanceof AffiliateCorporate) {
            return AffiliatePersonalDataUpdater::update($model, $data, $actor, AffiliatePersonalDataUpdater::SOURCE_PERSON_IDENTITY_DETECTOR)->changes;
        }

        if (! $model instanceof CompanyAssociate) {
            throw new RuntimeException('Tipo de registro desconocido.');
        }

        /** @var CompanyAssociate $locked */
        $locked = CompanyAssociate::query()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
        $labels = self::correctableFields(PersonIdentitySource::Associate);
        $changes = [];
        $attributes = [];

        foreach (array_intersect_key($data, $labels) as $field => $after) {
            $after = match ($field) {
                'full_name' => Str::squish((string) $after),
                'identity_card' => mb_strtoupper(trim((string) $after)),
                'birth_date' => PersonIdentityNormalizer::birthDate($after),
                default => $after,
            };
            $before = $locked->getAttribute($field);

            if (self::comparable($field, $before) === self::comparable($field, $after)) {
                continue;
            }

            $attributes[$field] = $after;
            $changes[$field] = [
                'label' => $labels[$field],
                'before' => $before instanceof \DateTimeInterface ? $before->format('d/m/Y') : $before,
                'after' => $after instanceof \DateTimeInterface ? $after->format('d/m/Y') : $after,
            ];
        }

        if ($attributes === []) {
            return [];
        }

        if (array_key_exists('birth_date', $attributes) && $attributes['birth_date'] !== null) {
            $attributes['age'] = $attributes['birth_date']->age;
        }

        $locked->forceFill($attributes)->save();

        SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_PERSONAL_DATA_UPDATED', 'business.person-identity-findings.correct', [
            'associate_id' => $locked->getKey(),
            'changes' => $changes,
        ], $actor);

        return $changes;
    }

    private static function deactivateIndividual(Affiliate $affiliate): string
    {
        /** @var Affiliate $locked */
        $locked = Affiliate::query()->whereKey($affiliate->getKey())->lockForUpdate()->firstOrFail();
        $locked->forceFill(['status' => 'INACTIVO'])->save();

        $owner = $locked->affiliation()->first();

        if ($owner !== null) {
            app(AffiliationAffiliateFeeCalculator::class)->recalculateAffiliationTotalsFromAffiliates($owner);
        }

        return 'Afiliado individual «'.$locked->full_name.'» de '.($owner?->code ?? 'la afiliación').' quedó INACTIVO y se recalcularon los totales de la afiliación.';
    }

    private static function deactivateCorporate(AffiliateCorporate $affiliate): string
    {
        $owner = AffiliationCorporate::query()->find($affiliate->affiliation_corporate_id) ?? throw new RuntimeException('La afiliación corporativa del registro ya no existe.');
        $name = PersonIdentityRecord::nameOf($affiliate)[0];

        if ((int) $affiliate->plan_id <= 0 && (float) $affiliate->fee <= 0) {
            /** @var AffiliationCorporate $lockedOwner */
            $lockedOwner = AffiliationCorporate::query()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail();
            $lockedOwner->forceFill(['poblation' => max(0, (int) $lockedOwner->poblation - 1)])->save();
            AffiliateCorporate::query()->whereKey($affiliate->getKey())->lockForUpdate()->firstOrFail()->forceFill(['status' => 'INACTIVO'])->save();

            return 'Afiliado corporativo «'.$name.'» de '.$owner->code.' quedó INACTIVO. No tenía plan ni tarifa asignados, así que solo se descontó de la población (los montos no cambian).';
        }

        CorporateAffiliateRemovalService::deactivate($affiliate, $owner);

        return 'Afiliado corporativo «'.$name.'» de '.$owner->code.' quedó INACTIVO; se descontaron su tarifa, la población del plan y los totales de la afiliación.';
    }

    private static function annulAssociate(CompanyAssociate $associate, string $note): string
    {
        CompanyAssociateStatusManager::annul($associate, 'Persona duplicada: '.$note);

        return 'Asociado de Nuevos Negocios «'.$associate->full_name.'» quedó ANULADO (se devuelve el día al responsable).';
    }

    private static function comparable(string $field, mixed $value): string
    {
        return match ($field) {
            'birth_date' => (string) (PersonIdentityNormalizer::birthDateKey($value) ?? trim($value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value)),
            'nro_identificacion', 'identity_card' => (string) (PersonIdentityNormalizer::documentKey($value) ?? mb_strtoupper(trim((string) $value))),
            default => mb_strtoupper(Str::squish((string) $value)),
        };
    }

    /**
     * @param  array<string, array{label: string, before: mixed, after: mixed}>  $changes
     * @return list<string>
     */
    private static function changeLines(array $changes): array
    {
        $lines = [];

        foreach ($changes as $change) {
            $lines[] = $change['label'].': «'.trim((string) ($change['before'] ?? '')).'» → «'.trim((string) ($change['after'] ?? '')).'»';
        }

        return $lines;
    }

    /**
     * @return array{status: string, resolved_at: mixed, resolved_by: ?int, resolved_by_name: ?string, resolution_note: ?string}
     */
    private static function resolvedValues(string $status, ?string $note, ?User $actor): array
    {
        $resolved = $status !== Finding::STATUS_OPEN;

        return [
            'status' => $status,
            'resolved_at' => $resolved ? now() : null,
            'resolved_by' => $resolved ? $actor?->getKey() : null,
            'resolved_by_name' => $resolved ? $actor?->name : null,
            'resolution_note' => $resolved ? $note : null,
        ];
    }

    private static function lockFinding(Finding $finding): Finding
    {
        return Finding::query()->whereKey($finding->getKey())->lockForUpdate()->firstOrFail();
    }

    private static function assertCan(User $actor): void
    {
        if (! self::userCan($actor)) {
            throw new RuntimeException('No tiene el permiso «Personas duplicadas» de Negocios.');
        }
    }

    private static function assertNote(string $note): string
    {
        $note = trim($note);

        if (mb_strlen($note) < self::NOTE_MIN_LENGTH) {
            throw new RuntimeException('La nota debe explicar lo que hizo (mínimo '.self::NOTE_MIN_LENGTH.' caracteres).');
        }

        return $note;
    }

    private static function assertSide(Finding $finding, string $side): string
    {
        if ($side !== 'a' && $side !== 'b') {
            throw new RuntimeException('Registro no válido.');
        }

        if ($side === 'b' && $finding->record_b_id === null) {
            throw new RuntimeException('Este caso tiene un solo registro.');
        }

        return $side;
    }
}
