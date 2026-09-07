<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Models\Plan;
use Illuminate\Support\Collection;

/**
 * Frecuencia de pago de una cotización en la PWA.
 *
 * @phpstan-type PaymentAmount array{
 *     has_amount: bool,
 *     amount: float,
 *     amount_label: string,
 *     amount_prefix: string,
 *     amount_period: string,
 *     frequency: string,
 *     frequency_label: string,
 *     installments: int,
 *     annual_total: float,
 *     annual_label: string,
 *     breakdown: string
 * }
 * @phpstan-type FrequencyChoice array{
 *     key: string,
 *     label: string,
 *     hint: string
 * }
 */
final class StorefrontQuoteFrequency
{
    public const Annual = 'anual';

    public const Biannual = 'semestral';

    public const Quarterly = 'trimestral';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [self::Annual, self::Biannual, self::Quarterly];
    }

    public static function isKnown(string $raw): bool
    {
        return in_array(self::normalize($raw), self::keys(), true)
            && trim($raw) !== '';
    }

    public static function normalize(string $raw): string
    {
        return match (strtolower(trim($raw))) {
            'anual', 'annual' => self::Annual,
            'semestral', 'biannual', 'semetral' => self::Biannual,
            'trimestral', 'quarterly' => self::Quarterly,
            default => '',
        };
    }

    public static function label(string $frequency): string
    {
        return match (self::normalize($frequency)) {
            self::Biannual => 'Semestral',
            self::Quarterly => 'Trimestral',
            default => 'Anual',
        };
    }

    public static function hint(string $frequency): string
    {
        return match (self::normalize($frequency)) {
            self::Biannual => '2 pagos al año',
            self::Quarterly => '4 pagos al año',
            default => 'Un solo pago al año',
        };
    }

    public static function period(string $frequency): string
    {
        return match (self::normalize($frequency)) {
            self::Biannual => 'al semestre',
            self::Quarterly => 'al trimestre',
            default => 'al año',
        };
    }

    public static function installments(string $frequency): int
    {
        return match (self::normalize($frequency)) {
            self::Biannual => 2,
            self::Quarterly => 4,
            default => 1,
        };
    }

    /**
     * @return list<FrequencyChoice>
     */
    public static function choices(): array
    {
        return array_map(static fn (string $key): array => [
            'key' => $key,
            'label' => self::label($key),
            'hint' => self::hint($key),
        ], self::keys());
    }

    /**
     * @param  Collection<int, mixed>  $details
     * @param  list<int>|null  $coverageIds
     * @return PaymentAmount
     */
    public static function paymentFromDetails(
        Collection $details,
        ?Plan $plan,
        string $frequency = self::Annual,
        ?array $coverageIds = null,
    ): array {
        $frequency = self::normalize($frequency);
        if ($frequency === '') {
            $frequency = self::Annual;
        }

        $lines = $details
            ->filter(static fn (mixed $row): bool => is_object($row))
            ->values();

        if ($coverageIds !== null) {
            $wanted = StorefrontQuoteCoverages::sanitize(
                $coverageIds,
                StorefrontQuoteCoverages::availableIds($lines),
            );
            $selected = $lines
                ->filter(static fn (mixed $line): bool => in_array((int) ($line->coverage_id ?? 0), $wanted, true))
                ->values();
            $annual = self::sumLines($selected, self::Annual);
            $installment = self::sumLines($selected, $frequency);

            return self::payload($installment, $annual, false, $frequency);
        }

        $isPackage = ($plan instanceof Plan && $plan->isBenefitPackage())
            || $lines->every(static fn (mixed $line): bool => (int) ($line->coverage_id ?? 0) === 0);

        if ($isPackage) {
            $annual = self::sumLines($lines, self::Annual);
            $installment = self::sumLines($lines, $frequency);

            return self::payload($installment, $annual, false, $frequency);
        }

        $annualByCoverage = self::totalsByCoverage($lines, self::Annual);
        $installmentByCoverage = self::totalsByCoverage($lines, $frequency);

        $annualMin = (float) ($annualByCoverage->min() ?? 0.0);
        $annualMax = (float) ($annualByCoverage->max() ?? 0.0);
        $min = (float) ($installmentByCoverage->min() ?? 0.0);
        $max = (float) ($installmentByCoverage->max() ?? 0.0);
        $isRange = $installmentByCoverage->count() > 1 && abs($max - $min) >= 0.01;

        return self::payload($min, $annualMin > 0 ? $annualMin : $annualMax, $isRange, $frequency);
    }

    /**
     * @param  Collection<int, mixed>  $lines
     */
    private static function sumLines(Collection $lines, string $frequency): float
    {
        return round((float) $lines->sum(
            static fn (mixed $line): float => self::lineAmount($line, $frequency)
        ), 2);
    }

    /**
     * @param  Collection<int, mixed>  $lines
     * @return Collection<int, float>
     */
    private static function totalsByCoverage(Collection $lines, string $frequency): Collection
    {
        return $lines
            ->groupBy(static fn (mixed $line): int => (int) ($line->coverage_id ?? 0))
            ->map(static fn (Collection $group): float => round((float) $group->sum(
                static fn (mixed $line): float => self::lineAmount($line, $frequency)
            ), 2))
            ->filter(static fn (float $total): bool => $total > 0);
    }

    private static function lineAmount(mixed $line, string $frequency): float
    {
        $annual = (float) ($line->subtotal_anual ?? 0);
        $biannual = (float) ($line->subtotal_biannual ?? 0);
        $quarterly = (float) ($line->subtotal_quarterly ?? 0);

        return match (self::normalize($frequency)) {
            self::Biannual => $biannual > 0 ? $biannual : round($annual / 2, 2),
            self::Quarterly => $quarterly > 0 ? $quarterly : round($annual / 4, 2),
            default => $annual,
        };
    }

    /**
     * @return PaymentAmount
     */
    private static function payload(float $amount, float $annualTotal, bool $isRange, string $frequency): array
    {
        $hasAmount = $amount > 0;
        $prefix = $hasAmount && $isRange ? 'Desde' : '';
        $amountLabel = $hasAmount
            ? StorefrontPlanNarrative::formatMoney($amount)
            : 'Monto por confirmar';
        $annualLabel = $annualTotal > 0
            ? StorefrontPlanNarrative::formatMoney($annualTotal)
            : '';
        $installments = self::installments($frequency);

        return [
            'has_amount' => $hasAmount,
            'amount' => $amount,
            'amount_label' => $amountLabel,
            'amount_prefix' => $prefix,
            'amount_period' => $hasAmount ? self::period($frequency) : '',
            'frequency' => $frequency,
            'frequency_label' => self::label($frequency),
            'installments' => $installments,
            'annual_total' => $annualTotal,
            'annual_label' => $annualLabel,
            'breakdown' => self::breakdown($hasAmount, $prefix, $amountLabel, $annualLabel, $installments),
        ];
    }

    private static function breakdown(
        bool $hasAmount,
        string $prefix,
        string $amountLabel,
        string $annualLabel,
        int $installments,
    ): string {
        if (! $hasAmount) {
            return 'Cuando haya tarifas, aquí verás el monto de cada pago.';
        }

        $desde = $prefix !== '' ? 'Desde ' : '';

        if ($installments === 1) {
            return $desde.'Un pago de '.$amountLabel;
        }

        $total = $annualLabel !== '' ? ' · Total '.$desde.$annualLabel.' al año' : '';

        return $desde.$installments.' pagos de '.$amountLabel.$total;
    }
}
