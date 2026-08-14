<?php

declare(strict_types=1);

namespace App\Support\WhiteCompanies;

use App\Models\Fee;
use App\Models\WhiteCompany;
use App\Support\AffiliationAffiliateFeeCalculator;
use Illuminate\Support\Collection;

final class WhiteCompanyCatalogFeeOptions
{
    /**
     * @return array<int, string>
     */
    public static function forCompany(WhiteCompany $company, ?int $keepFeeId = null): array
    {
        $alreadyNegotiated = $company->negotiatedFees()
            ->pluck('fee_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->reject(fn (int $id): bool => $keepFeeId !== null && $id === $keepFeeId)
            ->values()
            ->all();

        return self::labels(self::catalogFees(), $alreadyNegotiated);
    }

    /**
     * @param  iterable<int, Fee>  $fees
     * @param  list<int>  $excludeFeeIds
     * @return array<int, string>
     */
    public static function labels(iterable $fees, array $excludeFeeIds = []): array
    {
        $excluded = array_flip(array_map('intval', $excludeFeeIds));

        return collect($fees)
            ->filter(fn (mixed $fee): bool => $fee instanceof Fee)
            ->reject(fn (Fee $fee): bool => isset($excluded[(int) $fee->id]))
            ->sort(fn (Fee $left, Fee $right): int => self::sortKey($left) <=> self::sortKey($right))
            ->mapWithKeys(fn (Fee $fee): array => [
                (int) $fee->id => self::label($fee),
            ])
            ->all();
    }

    /**
     * @param  array<int, string>  $options
     * @param  iterable<int, Fee>  $fees
     * @return array<int, string>
     */
    public static function matching(array $options, string $search, iterable $fees = []): array
    {
        $normalizedSearch = self::normalizeSearch($search);
        $searchDigits = self::digitsOnly($search);

        if ($normalizedSearch === '' && $searchDigits === '') {
            return $options;
        }

        $feesById = collect($fees)
            ->filter(fn (mixed $fee): bool => $fee instanceof Fee)
            ->keyBy(fn (Fee $fee): int => (int) $fee->id);

        return collect($options)
            ->filter(function (string $label, int $feeId) use ($normalizedSearch, $searchDigits, $feesById): bool {
                if ($normalizedSearch !== '' && str_contains(self::normalizeSearch($label), $normalizedSearch)) {
                    return true;
                }

                if ($searchDigits === '' || strlen($searchDigits) < 3) {
                    return false;
                }

                $labelDigits = self::digitsOnly($label);
                if ($labelDigits !== '' && str_contains($labelDigits, $searchDigits)) {
                    return true;
                }

                $fee = $feesById->get($feeId);
                if (! $fee instanceof Fee) {
                    return false;
                }

                $coverageDigits = self::digitsOnly((string) (self::coverageAmount($fee) ?? ''));

                return $coverageDigits !== '' && str_contains($coverageDigits, $searchDigits);
            })
            ->all();
    }

    public static function label(Fee $fee): string
    {
        $plan = $fee->ageRange?->plan?->description ?: 'Plan';
        $range = filled($fee->ageRange?->range) ? $fee->ageRange->range.' años' : 'sin rango';
        $amount = self::coverageAmount($fee);
        $coverage = $amount !== null
            ? number_format($amount, 0, ',', '.').' UD$'
            : 'sin cobertura';

        return $plan.' · '.$coverage.' · '.$range;
    }

    /**
     * @return Collection<int, Fee>
     */
    public static function catalogFees(): Collection
    {
        return Fee::query()
            ->with(['ageRange.plan', 'coverageRecord'])
            ->where(function ($query): void {
                $query->whereNull('status')
                    ->orWhere('status', 'ACTIVO');
            })
            ->get();
    }

    /**
     * @return array{0: int, 1: string, 2: int, 3: float, 4: int}
     */
    private static function sortKey(Fee $fee): array
    {
        $planId = (int) ($fee->ageRange?->plan_id ?? $fee->ageRange?->plan?->id ?? 0);

        $planPriority = match ($planId) {
            AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID => 1,
            AffiliationAffiliateFeeCalculator::IDEAL_PLAN_ID => 2,
            AffiliationAffiliateFeeCalculator::SPECIAL_PLAN_ID => 3,
            default => 100,
        };

        $planName = mb_strtolower((string) ($fee->ageRange?->plan?->description ?? 'zzz'));

        return [
            $planPriority,
            $planName,
            (int) ($fee->ageRange?->age_init ?? 999),
            self::coverageAmount($fee) ?? -1.0,
            (int) $fee->id,
        ];
    }

    public static function coverageAmount(Fee $fee): ?float
    {
        if (filled($fee->coverageRecord?->price)) {
            return (float) $fee->coverageRecord->price;
        }

        if (filled($fee->coverage)) {
            return (float) $fee->coverage;
        }

        return null;
    }

    public static function normalizeSearch(string $search): string
    {
        $value = mb_strtolower(trim($search));
        $value = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ä', 'ë', 'ï', 'ö', 'ü'],
            ['a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u'],
            $value,
        );

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    public static function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
