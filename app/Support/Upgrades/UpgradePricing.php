<?php

declare(strict_types=1);

namespace App\Support\Upgrades;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Upgrade;
use App\Models\UpgradeCoverage;
use App\Models\UpgradeCoverageAgeRange;
use App\Support\PatientAge;
use App\Support\PlanGenerators\PlanGeneratorUpgrades;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Precio de un upgrade para una persona: la cobertura elegida y el rango de edad
 * en el que cae el afiliado. Es la única regla de precio de upgrades, compartida
 * por afiliados corporativos e individuales y por las renovaciones.
 */
final class UpgradePricing
{
    public const MAX_COVERAGES_PER_ASSIGNMENT = 10;

    public const SKIP_NOT_IN_AFFILIATION = 'not_in_affiliation';

    public const SKIP_AFFILIATE_INACTIVE = 'affiliate_inactive';

    public const SKIP_ALREADY_ACTIVE = 'already_active';

    public const SKIP_NO_AGE = 'no_age';

    public const SKIP_NO_RANGE = 'no_range';

    /**
     * Edad en años cumplidos: de la fecha de nacimiento si existe; si no, la edad guardada.
     */
    public static function ageOf(Affiliate|AffiliateCorporate $affiliate, ?Carbon $asOf = null): ?int
    {
        return PatientAge::years($affiliate->birth_date, $affiliate->age, $asOf);
    }

    /**
     * Rango activo de la cobertura que contiene la edad. Usa los rangos ya cargados si los hay.
     */
    public static function rangeFor(UpgradeCoverage $coverage, int $age): ?UpgradeCoverageAgeRange
    {
        $ranges = $coverage->relationLoaded('activeAgeRanges')
            ? $coverage->activeAgeRanges
            : $coverage->activeAgeRanges()->get();

        return $ranges->first(fn (UpgradeCoverageAgeRange $range): bool => $range->age_from <= $age && $age <= $range->age_to);
    }

    /**
     * Coberturas elegidas por el analista, validadas: existen, están activas, su
     * upgrade está activo y no hay dos coberturas del mismo upgrade.
     *
     * @param  array<int, mixed>  $coverageIds
     * @return Collection<int, UpgradeCoverage>
     */
    public static function resolveCoverages(array $coverageIds): Collection
    {
        $ids = array_values(array_unique(array_filter(
            array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $coverageIds),
            fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            throw new InvalidArgumentException('Elija al menos un upgrade con su cobertura.');
        }

        if (count($ids) > self::MAX_COVERAGES_PER_ASSIGNMENT) {
            throw new InvalidArgumentException('Puede agregar hasta '.self::MAX_COVERAGES_PER_ASSIGNMENT.' upgrades a la vez.');
        }

        $coverages = UpgradeCoverage::query()
            ->whereIn('id', $ids)
            ->with(['upgrade', 'activeAgeRanges'])
            ->get();

        if ($coverages->count() !== count($ids)) {
            throw new InvalidArgumentException('Una de las coberturas elegidas ya no existe. Actualice la página e intente de nuevo.');
        }

        foreach ($coverages as $coverage) {
            if ($coverage->status !== Upgrade::STATUS_ACTIVE || $coverage->upgrade === null || ! $coverage->upgrade->isActive()) {
                throw new InvalidArgumentException('«'.self::optionLabel($coverage).'» ya no está activo en el catálogo. Elija otro.');
            }
        }

        $repeated = $coverages->groupBy('upgrade_id')->first(fn (Collection $group): bool => $group->count() > 1);

        if ($repeated !== null) {
            throw new InvalidArgumentException('Eligió dos coberturas del upgrade «'.$repeated->first()->upgrade->name.'». Elija solo una por upgrade.');
        }

        return $coverages->sortBy(fn (UpgradeCoverage $coverage): string => $coverage->upgrade->name)->values();
    }

    /**
     * Opciones del selector, agrupadas por upgrade: solo upgrades y coberturas activos con precio.
     *
     * @return array<string, array<int, string>>
     */
    public static function coverageOptions(): array
    {
        $upgrades = Upgrade::query()
            ->active()
            ->with(['activeCoverages.activeAgeRanges'])
            ->orderBy('name')
            ->get();

        $options = [];

        foreach ($upgrades as $upgrade) {
            $group = [];

            foreach ($upgrade->activeCoverages as $coverage) {
                if ($coverage->activeAgeRanges->isEmpty()) {
                    continue;
                }

                $min = (float) $coverage->activeAgeRanges->min('price');
                $max = (float) $coverage->activeAgeRanges->max('price');

                // El nombre va también en la opción: el campo elegido solo muestra la opción, no el grupo.
                $group[(int) $coverage->getKey()] = $upgrade->name.' · '.$coverage->label().' · '
                    .($min === $max ? self::money($min) : 'de '.self::money($min).' a '.self::money($max)).' al año';
            }

            if ($group !== []) {
                $options[$upgrade->code.' · '.$upgrade->name] = $group;
            }
        }

        return $options;
    }

    /**
     * Qué pagaría una persona de esa edad por cada cobertura elegida, para la vista previa del modal.
     *
     * @param  array<int, mixed>  $coverageIds
     * @return list<string>
     */
    public static function previewLines(array $coverageIds, ?int $age): array
    {
        $ids = array_values(array_filter(array_map('intval', $coverageIds), fn (int $id): bool => $id > 0));

        if ($ids === []) {
            return [];
        }

        $coverages = UpgradeCoverage::query()
            ->whereIn('id', $ids)
            ->with(['upgrade', 'activeAgeRanges'])
            ->get();

        $lines = [];

        foreach ($coverages as $coverage) {
            $label = self::optionLabel($coverage);

            if ($age === null) {
                $lines[] = $label.': '.$coverage->activeAgeRanges
                    ->map(fn (UpgradeCoverageAgeRange $range): string => $range->label().' '.self::money((float) $range->price))
                    ->implode(' · ');

                continue;
            }

            $range = self::rangeFor($coverage, $age);

            $lines[] = $range !== null
                ? $label.': '.self::money((float) $range->price).' al año (rango '.$range->label().')'
                : $label.': no tiene precio para '.$age.' años; no se agregará.';
        }

        return $lines;
    }

    /**
     * Edades que cubre la cobertura: «18 a 64 años».
     */
    public static function coversLabel(UpgradeCoverage $coverage): string
    {
        $ranges = $coverage->relationLoaded('activeAgeRanges') ? $coverage->activeAgeRanges : $coverage->activeAgeRanges()->get();

        return PlanGeneratorUpgrades::coverageSpansLabel(
            $ranges->map(fn (UpgradeCoverageAgeRange $range): array => ['age_from' => $range->age_from, 'age_to' => $range->age_to])->all(),
        );
    }

    public static function optionLabel(UpgradeCoverage $coverage): string
    {
        return (string) ($coverage->upgrade?->name ?? 'Upgrade').' · '.$coverage->label();
    }

    public static function skipReasonLabel(string $reason): string
    {
        return match ($reason) {
            self::SKIP_NOT_IN_AFFILIATION => 'no pertenece a esta afiliación',
            self::SKIP_AFFILIATE_INACTIVE => 'está dado de baja',
            self::SKIP_ALREADY_ACTIVE => 'ya tenía ese upgrade activo',
            self::SKIP_NO_AGE => 'no tiene fecha de nacimiento ni edad registrada',
            self::SKIP_NO_RANGE => 'su edad no está en ningún rango de esa cobertura',
            default => 'no se pudo procesar',
        };
    }

    /**
     * Columnas de una asignación nueva (corporativa o individual).
     *
     * @return array<string, mixed>
     */
    public static function assignmentAttributes(UpgradeCoverage $coverage, UpgradeCoverageAgeRange $range, int $age, string $actor): array
    {
        return [
            'upgrade_id' => (int) $coverage->upgrade_id,
            'upgrade_coverage_id' => (int) $coverage->getKey(),
            'upgrade_coverage_age_range_id' => (int) $range->getKey(),
            'name' => self::normalizeName((string) $coverage->upgrade?->name),
            'coverage_amount' => $coverage->amount,
            'age_at_assignment' => max(0, min(255, $age)),
            'amount' => round((float) $range->price, 2),
            'status' => Upgrade::STATUS_ACTIVE,
            'created_by' => $actor,
            'updated_by' => $actor,
        ];
    }

    public static function normalizeName(?string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) $name)) ?? '';

        return mb_strtoupper($name, 'UTF-8');
    }

    public static function money(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' US$';
    }
}
