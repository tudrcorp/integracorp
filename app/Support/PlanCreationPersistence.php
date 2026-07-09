<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AgeRange;
use App\Models\Benefit;
use App\Models\BenefitPlan;
use App\Models\Coverage;
use App\Models\Fee;
use App\Models\Plan;
use Illuminate\Support\Facades\Auth;

class PlanCreationPersistence
{
    private const PACKAGE_QUOTE_AGE_RANGE = '1 a 50';

    private const PACKAGE_QUOTE_AGE_INIT = 1;

    private const PACKAGE_QUOTE_AGE_END = 50;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function preparePlanAttributes(array $data): array
    {
        if (isset($data['category']) && ! isset($data['type'])) {
            $data['type'] = self::normalizePlanType((string) $data['category']);
            unset($data['category']);
        }

        if (blank($data['code'] ?? null)) {
            $data['code'] = self::generatePlanCode();
        }

        if (blank($data['business_unit_id'] ?? null)) {
            $data['business_unit_id'] = 1;
        }

        if (blank($data['status'] ?? null)) {
            $data['status'] = 'ACTIVO';
        }

        unset(
            $data['is_package'],
            $data['package_benefit_ids'],
            $data['general_coverages'],
            $data['benefits'],
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    public static function persistRelations(Plan $plan, array $formData): void
    {
        self::syncRelations($plan, $formData);
    }

    /**
     * @return array<string, mixed>
     */
    public static function hydrateFormData(Plan $plan): array
    {
        $plan->loadMissing(['benefitPlans']);

        $benefitIds = $plan->benefitPlans
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $coverages = Coverage::query()
            ->where('plan_id', $plan->id)
            ->orderBy('id')
            ->get();

        $generalCoverages = $coverages
            ->map(static fn (Coverage $coverage): array => [
                'coverage_id' => $coverage->id,
                'age_rates' => self::buildAgeRatesForCoverage($coverage, (int) $plan->id),
            ])
            ->values()
            ->all();

        if (self::shouldUsePackageMode($benefitIds, $coverages)) {
            return [
                'is_package' => true,
                'package_benefit_ids' => $benefitIds,
                'general_coverages' => $generalCoverages,
            ];
        }

        $benefits = [];

        foreach ($plan->benefitPlans as $benefit) {
            $benefits[] = [
                'benefit_id' => $benefit->id,
                'benefit_pvp' => $benefit->limit_id,
                'coverages' => $generalCoverages,
            ];
        }

        return [
            'is_package' => false,
            'benefits' => $benefits,
        ];
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    public static function syncRelations(Plan $plan, array $formData): void
    {
        if ((bool) ($formData['is_package'] ?? false)) {
            self::syncPackageMode($plan, $formData);

            return;
        }

        self::syncDetailedMode($plan, $formData);
    }

    public static function generatePlanCode(): string
    {
        $nextId = (int) (Plan::query()->max('id') ?? 0) + 1;

        return 'TDEC-PL-'.str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
    }

    public static function normalizePlanType(string $category): string
    {
        return match (strtoupper($category)) {
            'DRESS-TYLOR', 'DRESS-TAILOR' => 'DRESS-TAILOR',
            default => 'BASICO',
        };
    }

    /**
     * @param  list<int>  $benefitIds
     * @param  \Illuminate\Support\Collection<int, Coverage>  $coverages
     */
    private static function shouldUsePackageMode(array $benefitIds, $coverages): bool
    {
        if ($benefitIds === []) {
            return false;
        }

        return count($benefitIds) > 1 || $coverages->isNotEmpty();
    }

    /**
     * @return list<array{age_range_id: int, rate: float|string|null}>
     */
    private static function buildAgeRatesForCoverage(Coverage $coverage, int $planId): array
    {
        $fees = Fee::query()
            ->where('coverage_id', $coverage->id)
            ->orderBy('age_range_id')
            ->get();

        if ($fees->isNotEmpty()) {
            return $fees
                ->map(static fn (Fee $fee): array => [
                    'age_range_id' => (int) $fee->age_range_id,
                    'rate' => $fee->price,
                ])
                ->values()
                ->all();
        }

        $ageRanges = AgeRange::query()
            ->where('plan_id', $planId)
            ->where(function ($query) use ($coverage): void {
                $query->where('coverage_id', $coverage->id)
                    ->orWhereNull('coverage_id')
                    ->orWhere('coverage_id', 0);
            })
            ->orderBy('age_init')
            ->orderBy('id')
            ->get();

        return $ageRanges
            ->map(static fn (AgeRange $ageRange): array => [
                'age_range_id' => (int) $ageRange->id,
                'rate' => $ageRange->fee,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int|string>  $benefitIds
     */
    private static function syncBenefitPlans(Plan $plan, array $benefitIds): void
    {
        $benefitIds = array_values(array_filter(array_map(
            static fn (mixed $benefitId): ?int => filled($benefitId) ? (int) $benefitId : null,
            $benefitIds,
        )));

        BenefitPlan::query()
            ->where('plan_id', $plan->id)
            ->when(
                $benefitIds !== [],
                static fn ($query) => $query->whereNotIn('benefit_id', $benefitIds),
                static fn ($query) => $query,
            )
            ->delete();

        foreach ($benefitIds as $benefitId) {
            BenefitPlan::query()->firstOrCreate(
                [
                    'plan_id' => $plan->id,
                    'benefit_id' => $benefitId,
                ],
                [
                    'description' => Benefit::query()->find($benefitId)?->description,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $coverageRow
     */
    private static function syncCoverageAgeRates(Plan $plan, array $coverageRow, bool $createFees = true): ?Coverage
    {
        $coverageId = $coverageRow['coverage_id'] ?? null;

        if (blank($coverageId)) {
            return null;
        }

        $coverage = Coverage::query()->find($coverageId);

        if ($coverage === null) {
            return null;
        }

        if ((int) $coverage->plan_id !== (int) $plan->id) {
            $coverage->plan_id = $plan->id;
            $coverage->status = 'ACTIVO';

            if (blank($coverage->created_by)) {
                $coverage->created_by = Auth::user()?->name;
            }

            $coverage->save();
        }

        $desiredAgeRangeIds = [];

        foreach ($coverageRow['age_rates'] ?? [] as $ageRateRow) {
            if (! is_array($ageRateRow)) {
                continue;
            }

            $ageRangeId = $ageRateRow['age_range_id'] ?? null;
            $rate = $ageRateRow['rate'] ?? null;

            if (blank($ageRangeId) || ! is_numeric($rate)) {
                continue;
            }

            $ageRange = AgeRange::query()->find($ageRangeId);

            if ($ageRange === null) {
                continue;
            }

            $desiredAgeRangeIds[] = (int) $ageRange->id;

            if ((int) $ageRange->plan_id === 0 || blank($ageRange->plan_id) || (int) $ageRange->plan_id === (int) $plan->id) {
                $ageRange->plan_id = $plan->id;
                $ageRange->coverage_id = $coverage->id;
                $ageRange->fee = (string) $rate;
                $ageRange->status = 'ACTIVO';

                if (blank($ageRange->created_by)) {
                    $ageRange->created_by = Auth::user()?->name;
                }

                $ageRange->save();
            }

            if ($createFees) {
                $fee = Fee::query()->firstOrNew([
                    'age_range_id' => $ageRange->id,
                    'coverage_id' => $coverage->id,
                ]);

                $fee->price = $rate;
                $fee->range = $ageRange->range;
                $fee->coverage = $coverage->price;
                $fee->status = 'ACTIVO';

                if (blank($fee->code)) {
                    $fee->code = self::generateFeeCode();
                }

                if (blank($fee->created_by)) {
                    $fee->created_by = Auth::user()?->name;
                }

                $fee->save();
            }
        }

        if ($createFees) {
            Fee::query()
                ->where('coverage_id', $coverage->id)
                ->when(
                    $desiredAgeRangeIds !== [],
                    static fn ($query) => $query->whereNotIn('age_range_id', $desiredAgeRangeIds),
                    static fn ($query) => $query,
                )
                ->delete();
        }

        return $coverage;
    }

    /**
     * @param  list<array<string, mixed>>  $coverageRows
     */
    private static function syncPlanCoverages(Plan $plan, array $coverageRows, bool $createFees = true): array
    {
        $syncedCoverageIds = [];

        foreach ($coverageRows as $coverageRow) {
            if (! is_array($coverageRow)) {
                continue;
            }

            $coverage = self::syncCoverageAgeRates($plan, $coverageRow, $createFees);

            if ($coverage !== null) {
                $syncedCoverageIds[] = (int) $coverage->id;
            }
        }

        $staleCoverages = Coverage::query()
            ->where('plan_id', $plan->id)
            ->when(
                $syncedCoverageIds !== [],
                static fn ($query) => $query->whereNotIn('id', $syncedCoverageIds),
                static fn ($query) => $query,
            )
            ->get();

        foreach ($staleCoverages as $staleCoverage) {
            Fee::query()->where('coverage_id', $staleCoverage->id)->delete();
            $staleCoverage->plan_id = null;
            $staleCoverage->save();
        }

        return $syncedCoverageIds;
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private static function syncPackageMode(Plan $plan, array $formData): void
    {
        self::syncBenefitPlans($plan, $formData['package_benefit_ids'] ?? []);
        self::syncPlanCoverages($plan, $formData['general_coverages'] ?? [], createFees: false);
        self::syncPackageQuoteFees($plan, $formData['general_coverages'] ?? []);
    }

    /**
     * @param  list<array<string, mixed>>  $coverageRows
     */
    private static function syncPackageQuoteFees(Plan $plan, array $coverageRows): void
    {
        $rate = self::resolvePackageQuoteRate($coverageRows);

        if ($rate === null) {
            return;
        }

        $ageRange = self::ensurePackageAgeRange($plan);

        $fee = Fee::query()
            ->where('age_range_id', $ageRange->id)
            ->whereNull('coverage_id')
            ->first();

        if ($fee === null) {
            $fee = new Fee;
            $fee->age_range_id = $ageRange->id;
            $fee->coverage_id = null;
            $fee->code = self::generateFeeCode();
        }

        $fee->price = $rate;
        $fee->range = self::PACKAGE_QUOTE_AGE_RANGE;
        $fee->coverage = null;
        $fee->status = 'ACTIVO';

        if (blank($fee->created_by)) {
            $fee->created_by = Auth::user()?->name;
        }

        $fee->save();

        $ageRange->fee = (string) $rate;
        $ageRange->save();
    }

    /**
     * @param  list<array<string, mixed>>  $coverageRows
     */
    private static function resolvePackageQuoteRate(array $coverageRows): ?float
    {
        foreach ($coverageRows as $coverageRow) {
            if (! is_array($coverageRow)) {
                continue;
            }

            foreach ($coverageRow['age_rates'] ?? [] as $ageRateRow) {
                if (! is_array($ageRateRow)) {
                    continue;
                }

                $rate = $ageRateRow['rate'] ?? null;

                if (is_numeric($rate)) {
                    return (float) $rate;
                }
            }
        }

        return null;
    }

    private static function ensurePackageAgeRange(Plan $plan): AgeRange
    {
        $ageRange = AgeRange::query()->firstOrNew([
            'plan_id' => $plan->id,
            'range' => self::PACKAGE_QUOTE_AGE_RANGE,
        ]);

        if (blank($ageRange->code)) {
            $ageRange->code = self::generateAgeRangeCode();
        }

        $ageRange->age_init = self::PACKAGE_QUOTE_AGE_INIT;
        $ageRange->age_end = self::PACKAGE_QUOTE_AGE_END;
        $ageRange->coverage_id = null;
        $ageRange->status = 'ACTIVO';

        if (blank($ageRange->created_by)) {
            $ageRange->created_by = Auth::user()?->name;
        }

        $ageRange->save();

        return $ageRange;
    }

    private static function generateFeeCode(): string
    {
        $nextId = (int) (Fee::query()->max('id') ?? 0) + 1;

        return 'TDEC-FA-000'.$nextId;
    }

    private static function generateAgeRangeCode(): string
    {
        $nextId = (int) (AgeRange::query()->max('id') ?? 0) + 1;

        return 'TDEC-RE-000'.$nextId;
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private static function syncDetailedMode(Plan $plan, array $formData): void
    {
        $benefits = $formData['benefits'] ?? [];
        $benefitIds = [];
        $coverageRows = [];

        foreach ($benefits as $benefitRow) {
            if (! is_array($benefitRow)) {
                continue;
            }

            $benefitId = $benefitRow['benefit_id'] ?? null;

            if (filled($benefitId)) {
                $benefitIds[] = (int) $benefitId;
            }

            foreach ($benefitRow['coverages'] ?? [] as $coverageRow) {
                if (is_array($coverageRow)) {
                    $coverageRows[] = $coverageRow;
                }
            }
        }

        self::syncBenefitPlans($plan, $benefitIds);
        self::syncPlanCoverages($plan, $coverageRows);
    }
}
