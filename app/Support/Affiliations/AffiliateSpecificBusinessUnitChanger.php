<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use App\Models\Affiliate;
use App\Models\AffiliateBusinessUnitChange;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\AffiliationAffiliateBusinessContextSynchronizer;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\TelemedicinePatientPlanBridge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Cambio a mano de la unidad de negocio específica de afiliados (Negocios).
 *
 * Un valor fijado aquí queda marcado (`specific_business_unit_locked_at`) y las
 * sincronizaciones con la afiliación lo respetan. «Heredar de la afiliación»
 * quita la marca y vuelve al valor de la afiliación.
 *
 * El cambio llega también al paciente de telemedicina ligado al afiliado en esa
 * misma afiliación: Órdenes de servicio y Cuentas/Cotizaciones por pagar leen
 * el valor del paciente. Un paciente asociado hoy a otra afiliación no se toca.
 *
 * Todo en una transacción, con la nota obligatoria y una fila de traza por
 * afiliado en `affiliate_business_unit_changes` más SecurityAudit.
 */
final class AffiliateSpecificBusinessUnitChanger
{
    public const TYPE_INDIVIDUAL = 'INDIVIDUAL';

    public const TYPE_CORPORATE = 'CORPORATIVO';

    public const NOTE_MIN_LENGTH = 15;

    public const NOTE_MAX_LENGTH = 1000;

    public const VALUE_MAX_LENGTH = 255;

    /** @var array<string, bool> */
    private static array $lockColumnExists = [];

    /**
     * Mismo criterio de siempre (sin espacios sobrantes), en mayúsculas para que
     * «pqq » y «PQQ» no terminen como dos unidades distintas.
     */
    public static function normalize(mixed $value): ?string
    {
        $trimmed = AffiliationAffiliateBusinessContextSynchronizer::normalizeSpecificBusinessUnit(
            is_string($value) ? preg_replace('/\s+/u', ' ', $value) : $value,
        );

        return $trimmed === null ? null : mb_strtoupper($trimmed);
    }

    /**
     * Valores ya usados en afiliaciones y afiliados, para elegir sin reescribir.
     *
     * @return list<string>
     */
    public static function knownValues(): array
    {
        $values = collect();

        foreach (['affiliations', 'affiliation_corporates', 'affiliates', 'affiliate_corporates'] as $table) {
            $values = $values->merge(
                DB::table($table)
                    ->whereNotNull('specific_business_unit')
                    ->where('specific_business_unit', '!=', '')
                    ->distinct()
                    ->limit(500)
                    ->pluck('specific_business_unit'),
            );
        }

        return $values
            ->map(static fn (mixed $value): ?string => self::normalize($value))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public static function isLocked(Affiliate|AffiliateCorporate $affiliate): bool
    {
        return filled($affiliate->getAttribute('specific_business_unit_locked_at'));
    }

    /**
     * El valor que debe llevar el afiliado (y su paciente): el suyo si se fijó a
     * mano, aunque esté vacío; si no, el suyo o, en su defecto, el de la afiliación.
     */
    public static function effectiveValueFor(Affiliate|AffiliateCorporate $affiliate, Affiliation|AffiliationCorporate|null $owner): ?string
    {
        if (self::isLocked($affiliate)) {
            return AffiliationAffiliateBusinessContextSynchronizer::normalizeSpecificBusinessUnit($affiliate->specific_business_unit);
        }

        return AffiliationAffiliateBusinessContextSynchronizer::normalizeSpecificBusinessUnit(
            $affiliate->specific_business_unit ?? $owner?->specific_business_unit,
        );
    }

    public static function lockColumnExists(string $table): bool
    {
        return self::$lockColumnExists[$table] ??= Schema::hasColumn($table, 'specific_business_unit_locked_at');
    }

    /**
     * @param  iterable<int, Affiliate|AffiliateCorporate>  $affiliates
     * @return array{changed: int, unchanged: int, patients: int, batch_id: string}
     */
    public static function change(iterable $affiliates, mixed $value, bool $inherit, string $note, User $actor): array
    {
        $note = trim((string) preg_replace('/\s+/u', ' ', $note));
        if (mb_strlen($note) < self::NOTE_MIN_LENGTH) {
            throw new InvalidArgumentException('La nota debe explicar el cambio en al menos '.self::NOTE_MIN_LENGTH.' caracteres.');
        }

        if (mb_strlen($note) > self::NOTE_MAX_LENGTH) {
            throw new InvalidArgumentException('La nota no puede pasar de '.self::NOTE_MAX_LENGTH.' caracteres.');
        }

        $newValue = $inherit ? null : self::normalize($value);
        if ($newValue !== null && mb_strlen($newValue) > self::VALUE_MAX_LENGTH) {
            throw new InvalidArgumentException('La unidad de negocio específica no puede pasar de '.self::VALUE_MAX_LENGTH.' caracteres.');
        }

        $items = collect($affiliates)->filter(static fn (mixed $item): bool => $item instanceof Affiliate || $item instanceof AffiliateCorporate)->values();
        if ($items->isEmpty()) {
            throw new InvalidArgumentException('No hay afiliados seleccionados.');
        }

        $class = get_class($items->first());
        if ($items->contains(static fn (mixed $item): bool => get_class($item) !== $class)) {
            throw new InvalidArgumentException('No se pueden mezclar afiliados individuales y corporativos en un mismo cambio.');
        }

        $corporate = $class === AffiliateCorporate::class;
        $table = $corporate ? 'affiliate_corporates' : 'affiliates';
        if (! self::lockColumnExists($table) || ! Schema::hasTable('affiliate_business_unit_changes')) {
            throw new InvalidArgumentException('Falta aplicar la actualización de base de datos de este módulo. Avise a soporte.');
        }

        $batchId = (string) Str::uuid();
        $ids = $items->map(static fn (Affiliate|AffiliateCorporate $item): int => (int) $item->getKey())->unique()->values()->all();

        $result = DB::transaction(static function () use ($class, $corporate, $ids, $inherit, $newValue, $note, $actor, $batchId): array {
            /** @var Collection<int, Affiliate|AffiliateCorporate> $locked */
            $locked = $class::query()
                ->with($corporate ? 'affiliationCorporate' : 'affiliation')
                ->whereKey($ids)
                ->lockForUpdate()
                ->get();

            $changed = 0;
            $unchanged = 0;
            $patientsTouched = 0;
            $log = [];

            foreach ($locked as $affiliate) {
                $owner = $corporate ? $affiliate->affiliationCorporate : $affiliate->affiliation;
                $before = AffiliationAffiliateBusinessContextSynchronizer::normalizeSpecificBusinessUnit($affiliate->specific_business_unit);
                $target = $inherit
                    ? AffiliationAffiliateBusinessContextSynchronizer::normalizeSpecificBusinessUnit($owner?->specific_business_unit)
                    : $newValue;
                $wasLocked = self::isLocked($affiliate);

                if ($before === $target && $wasLocked === ! $inherit) {
                    $unchanged++;

                    continue;
                }

                $affiliate->forceFill([
                    'specific_business_unit' => $target,
                    'specific_business_unit_locked_at' => $inherit ? null : now(),
                    'specific_business_unit_locked_by' => $inherit ? null : (string) $actor->name,
                ])->save();

                $patientIds = [];
                foreach (TelemedicinePatientPlanBridge::linkedPatientsFor($affiliate) as $patient) {
                    if (! $patient instanceof TelemedicinePatient) {
                        continue;
                    }

                    if (AffiliationAffiliateBusinessContextSynchronizer::normalizeSpecificBusinessUnit($patient->specific_business_unit) !== $target) {
                        $patient->forceFill(['specific_business_unit' => $target])->save();
                        TelemedicinePatientPlanBridge::flushCachesFor((int) $patient->id);
                        $patientsTouched++;
                    }

                    $patientIds[] = (int) $patient->id;
                }

                AffiliateBusinessUnitChange::query()->create([
                    'affiliate_type' => $corporate ? self::TYPE_CORPORATE : self::TYPE_INDIVIDUAL,
                    'affiliate_id' => (int) $affiliate->getKey(),
                    'affiliation_id' => $owner?->getKey(),
                    'batch_id' => $batchId,
                    'before_value' => $before,
                    'after_value' => $target,
                    'inherited' => $inherit,
                    'note' => $note,
                    'telemedicine_patient_ids' => $patientIds === [] ? null : $patientIds,
                    'actor_id' => $actor->getKey(),
                    'actor_name' => (string) $actor->name,
                    'ip' => request()?->ip(),
                ]);

                $log[] = [
                    'affiliate_id' => (int) $affiliate->getKey(),
                    'before' => $before,
                    'after' => $target,
                    'patients' => $patientIds,
                ];

                $changed++;
            }

            if ($changed > 0) {
                SecurityAudit::log('AUDIT_BUSINESS_AFFILIATE_SPECIFIC_BUSINESS_UNIT_CHANGED', 'business.affiliates.specific-business-unit', [
                    'panel' => 'business',
                    'affiliate_type' => $corporate ? self::TYPE_CORPORATE : self::TYPE_INDIVIDUAL,
                    'batch_id' => $batchId,
                    'inherited' => $inherit,
                    'value' => $newValue,
                    'note' => $note,
                    'changes' => $log,
                ], $actor);
            }

            return ['changed' => $changed, 'unchanged' => $unchanged, 'patients' => $patientsTouched];
        });

        return $result + ['batch_id' => $batchId];
    }

    /**
     * @return Collection<int, AffiliateBusinessUnitChange>
     */
    public static function history(Affiliate|AffiliateCorporate $affiliate, int $limit = 50): Collection
    {
        if (! Schema::hasTable('affiliate_business_unit_changes')) {
            return collect();
        }

        return AffiliateBusinessUnitChange::query()
            ->where('affiliate_type', $affiliate instanceof AffiliateCorporate ? self::TYPE_CORPORATE : self::TYPE_INDIVIDUAL)
            ->where('affiliate_id', $affiliate->getKey())
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
