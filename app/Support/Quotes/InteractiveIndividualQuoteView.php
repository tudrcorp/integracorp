<?php

declare(strict_types=1);

namespace App\Support\Quotes;

use App\Models\AgeRange;
use App\Models\Coverage;
use App\Models\DetailIndividualQuote;
use App\Models\IndividualQuote;
use App\Models\Plan;
use App\Support\Storefront\StorefrontPlanNarrative;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Vista de costos de una cotización individual interactiva.
 * El cliente ve tarifas por rango de edad y, si aplica, por cobertura.
 *
 * @phpstan-type Frequency array{key: string, label: string, hint: string, amount: float, amount_label: string}
 * @phpstan-type Cell array{coverage_key: string, coverage_label: string, persons: int, unit: float, unit_label: string, annual: float, annual_label: string, biannual: float, biannual_label: string, quarterly: float, quarterly_label: string}
 * @phpstan-type RangeBlock array{key: string, age_label: string, persons: int, persons_label: string, cells: list<Cell>}
 * @phpstan-type CoverageOption array{key: string, label: string, annual: float, annual_label: string, biannual: float, biannual_label: string, quarterly: float, quarterly_label: string, frequencies: list<Frequency>}
 * @phpstan-type QuoteCosts array{
 *     code: string,
 *     plan_title: string,
 *     client_name: string,
 *     date_label: string,
 *     agent_label: string,
 *     mode: string,
 *     persons: int,
 *     persons_label: string,
 *     headline: string,
 *     headline_hint: string,
 *     ranges: list<RangeBlock>,
 *     coverages: list<CoverageOption>,
 *     options: list<CoverageOption>,
 *     frequencies: list<Frequency>,
 *     default_coverage_key: string|null
 * }
 */
final class InteractiveIndividualQuoteView
{
    /**
     * @param  Collection<int, DetailIndividualQuote>  $details
     * @return QuoteCosts
     */
    public static function from(IndividualQuote $quote, Plan $plan, Collection $details): array
    {
        $lines = $details
            ->filter(static fn (mixed $row): bool => $row instanceof DetailIndividualQuote)
            ->values();

        $isPackage = $plan->isBenefitPackage()
            || $lines->every(static fn (DetailIndividualQuote $line): bool => $line->coverage_id === null);

        $ranges = $isPackage
            ? self::packageRanges($lines)
            : self::coverageRanges($lines);

        $coverages = $isPackage ? [] : self::coverageOptions($lines);
        $persons = self::totalPersons($ranges);
        $annualTotal = $isPackage
            ? self::sumRangeAnnual($ranges)
            : (float) ($coverages[0]['annual'] ?? 0);
        $maxAnnual = $isPackage
            ? $annualTotal
            : (float) collect($coverages)->max('annual');

        $frequencies = $isPackage
            ? self::frequenciesFromRanges($ranges)
            : (array) ($coverages[0]['frequencies'] ?? []);

        $headline = $annualTotal <= 0
            ? 'Sin tarifas para mostrar'
            : ($isPackage || count($coverages) <= 1 || abs($maxAnnual - $annualTotal) < 0.01
                ? StorefrontPlanNarrative::formatMoney($annualTotal).' al año'
                : 'Desde '.StorefrontPlanNarrative::formatMoney($annualTotal).' al año');

        $options = $isPackage
            ? [[
                'key' => 'package',
                'label' => 'Tarifa del plan',
                'annual' => $annualTotal,
                'annual_label' => $annualTotal <= 0 ? '—' : StorefrontPlanNarrative::formatMoney($annualTotal),
                'biannual' => 0.0,
                'biannual_label' => '',
                'quarterly' => 0.0,
                'quarterly_label' => '',
                'frequencies' => $frequencies,
            ]]
            : $coverages;

        return [
            'code' => (string) $quote->code,
            'plan_title' => StorefrontPlanNarrative::planLabel(
                (string) (StorefrontPlanNarrative::for($plan)['title'] ?? $plan->description ?? 'Plan'),
            ),
            'client_name' => StorefrontPlanNarrative::personName((string) $quote->full_name),
            'date_label' => self::dateLabel($quote),
            'agent_label' => self::agentLabel($quote->created_by),
            'mode' => $isPackage ? 'package' : 'coverages',
            'persons' => $persons,
            'persons_label' => self::personsLabel($persons),
            'headline' => $headline,
            'headline_hint' => $isPackage
                ? 'Total del grupo familiar según los rangos cotizados.'
                : 'Cada cobertura tiene su propio total. Elige una para ver el desglose.',
            'ranges' => $ranges,
            'coverages' => $coverages,
            'options' => $options,
            'frequencies' => $frequencies,
            'default_coverage_key' => $options[0]['key'] ?? null,
        ];
    }

    /**
     * @param  Collection<int, DetailIndividualQuote>  $lines
     * @return list<RangeBlock>
     */
    private static function packageRanges(Collection $lines): array
    {
        $grouped = $lines
            ->groupBy(static fn (DetailIndividualQuote $line): string => self::rangeKey($line))
            ->sortBy(static fn (Collection $group): int => self::rangeSort($group->first()));

        $ranges = [];

        foreach ($grouped as $group) {
            /** @var Collection<int, DetailIndividualQuote> $group */
            $first = $group->first();
            $persons = (int) ($first?->total_persons ?? 0);
            $annual = (float) $group->sum(static fn (DetailIndividualQuote $line): float => (float) $line->subtotal_anual);
            $biannual = (float) $group->sum(static fn (DetailIndividualQuote $line): float => (float) $line->subtotal_biannual);
            $quarterly = (float) $group->sum(static fn (DetailIndividualQuote $line): float => (float) $line->subtotal_quarterly);
            $unit = (float) ($first?->fee ?? 0);

            $ranges[] = [
                'key' => self::rangeKey($first),
                'age_label' => self::ageLabel($first?->ageRange),
                'persons' => $persons,
                'persons_label' => self::personsLabel($persons),
                'cells' => [
                    self::cell('package', 'Tarifa del plan', $persons, $unit, $annual, $biannual, $quarterly),
                ],
            ];
        }

        return array_values($ranges);
    }

    /**
     * @param  Collection<int, DetailIndividualQuote>  $lines
     * @return list<RangeBlock>
     */
    private static function coverageRanges(Collection $lines): array
    {
        $grouped = $lines
            ->groupBy(static fn (DetailIndividualQuote $line): string => self::rangeKey($line))
            ->sortBy(static fn (Collection $group): int => self::rangeSort($group->first()));

        $ranges = [];

        foreach ($grouped as $group) {
            /** @var Collection<int, DetailIndividualQuote> $group */
            $first = $group->first();
            $persons = (int) ($first?->total_persons ?? 0);
            $cells = [];

            $ordered = $group->sortBy(static function (DetailIndividualQuote $line): float {
                return (float) ($line->coverage?->price ?? $line->fee ?? 0);
            })->values();

            foreach ($ordered as $line) {
                $cells[] = self::cell(
                    self::coverageKey($line),
                    self::coverageLabel($line),
                    (int) $line->total_persons,
                    (float) $line->fee,
                    (float) $line->subtotal_anual,
                    (float) $line->subtotal_biannual,
                    (float) $line->subtotal_quarterly,
                );
            }

            $ranges[] = [
                'key' => self::rangeKey($first),
                'age_label' => self::ageLabel($first?->ageRange),
                'persons' => $persons,
                'persons_label' => self::personsLabel($persons),
                'cells' => $cells,
            ];
        }

        return array_values($ranges);
    }

    /**
     * @param  Collection<int, DetailIndividualQuote>  $lines
     * @return list<CoverageOption>
     */
    private static function coverageOptions(Collection $lines): array
    {
        $grouped = $lines->groupBy(static fn (DetailIndividualQuote $line): string => self::coverageKey($line));

        $options = [];

        foreach ($grouped as $key => $group) {
            /** @var Collection<int, DetailIndividualQuote> $group */
            $first = $group->first();
            $annual = (float) $group->sum(static fn (DetailIndividualQuote $line): float => (float) $line->subtotal_anual);
            $biannual = (float) $group->sum(static fn (DetailIndividualQuote $line): float => (float) $line->subtotal_biannual);
            $quarterly = (float) $group->sum(static fn (DetailIndividualQuote $line): float => (float) $line->subtotal_quarterly);

            $options[] = [
                'key' => (string) $key,
                'label' => self::coverageLabel($first),
                'sort' => (float) ($first?->coverage?->price ?? $first?->fee ?? 0),
                'annual' => $annual,
                'annual_label' => StorefrontPlanNarrative::formatMoney($annual),
                'biannual' => $biannual,
                'biannual_label' => StorefrontPlanNarrative::formatMoney($biannual),
                'quarterly' => $quarterly,
                'quarterly_label' => StorefrontPlanNarrative::formatMoney($quarterly),
                'frequencies' => self::frequencySet($annual, $biannual, $quarterly),
            ];
        }

        usort($options, static fn (array $left, array $right): int => $left['sort'] <=> $right['sort']);

        return array_map(static function (array $option): array {
            unset($option['sort']);

            return $option;
        }, $options);
    }

    /**
     * @param  list<RangeBlock>  $ranges
     * @return list<Frequency>
     */
    private static function frequenciesFromRanges(array $ranges): array
    {
        $annual = 0.0;
        $biannual = 0.0;
        $quarterly = 0.0;

        foreach ($ranges as $range) {
            foreach ($range['cells'] as $cell) {
                $annual += $cell['annual'];
                $biannual += $cell['biannual'];
                $quarterly += $cell['quarterly'];
            }
        }

        return self::frequencySet($annual, $biannual, $quarterly);
    }

    /**
     * @return list<Frequency>
     */
    private static function frequencySet(float $annual, float $biannual, float $quarterly): array
    {
        if ($biannual <= 0 && $annual > 0) {
            $biannual = $annual / 2;
        }

        if ($quarterly <= 0 && $annual > 0) {
            $quarterly = $annual / 4;
        }

        return [
            ['key' => 'annual', 'label' => 'Anual', 'hint' => 'Un pago', 'amount' => $annual, 'amount_label' => StorefrontPlanNarrative::formatMoney($annual)],
            ['key' => 'biannual', 'label' => 'Semestral', 'hint' => '2 pagos', 'amount' => $biannual, 'amount_label' => StorefrontPlanNarrative::formatMoney($biannual)],
            ['key' => 'quarterly', 'label' => 'Trimestral', 'hint' => '4 pagos', 'amount' => $quarterly, 'amount_label' => StorefrontPlanNarrative::formatMoney($quarterly)],
        ];
    }

    /**
     * @return Cell
     */
    private static function cell(
        string $key,
        string $label,
        int $persons,
        float $unit,
        float $annual,
        float $biannual,
        float $quarterly,
    ): array {
        return [
            'coverage_key' => $key,
            'coverage_label' => $label,
            'persons' => $persons,
            'unit' => $unit,
            'unit_label' => StorefrontPlanNarrative::formatMoney($unit),
            'annual' => $annual,
            'annual_label' => StorefrontPlanNarrative::formatMoney($annual),
            'biannual' => $biannual,
            'biannual_label' => StorefrontPlanNarrative::formatMoney($biannual),
            'quarterly' => $quarterly,
            'quarterly_label' => StorefrontPlanNarrative::formatMoney($quarterly),
        ];
    }

    /**
     * @param  list<RangeBlock>  $ranges
     */
    private static function totalPersons(array $ranges): int
    {
        $total = 0;

        foreach ($ranges as $range) {
            $total += (int) $range['persons'];
        }

        return $total;
    }

    /**
     * @param  list<RangeBlock>  $ranges
     */
    private static function sumRangeAnnual(array $ranges): float
    {
        $total = 0.0;

        foreach ($ranges as $range) {
            foreach ($range['cells'] as $cell) {
                $total += $cell['annual'];
            }
        }

        return $total;
    }

    private static function rangeKey(?DetailIndividualQuote $line): string
    {
        if (! $line instanceof DetailIndividualQuote) {
            return 'rango-0';
        }

        $id = (int) ($line->age_range_id ?? $line->ageRange?->getKey() ?? 0);

        return 'rango-'.$id;
    }

    private static function rangeSort(?DetailIndividualQuote $line): int
    {
        if (! $line instanceof DetailIndividualQuote) {
            return 0;
        }

        return (int) ($line->ageRange?->age_init ?? $line->age_range_id ?? 0);
    }

    private static function coverageKey(DetailIndividualQuote $line): string
    {
        $id = (int) ($line->coverage_id ?? $line->coverage?->getKey() ?? 0);

        return $id > 0 ? 'cov-'.$id : 'cov-'.md5(self::coverageLabel($line));
    }

    private static function coverageLabel(?DetailIndividualQuote $line): string
    {
        if (! $line instanceof DetailIndividualQuote) {
            return 'Cobertura';
        }

        $price = $line->coverage instanceof Coverage
            ? (float) $line->coverage->price
            : 0.0;

        if ($price > 0) {
            return 'Cobertura '.StorefrontPlanNarrative::formatMoney($price);
        }

        return 'Cobertura';
    }

    private static function ageLabel(?AgeRange $range): string
    {
        $raw = trim((string) ($range?->range ?? ''));

        if ($raw === '') {
            return 'Rango de edad';
        }

        if (preg_match('/años/iu', $raw) === 1) {
            return $raw;
        }

        return str_replace('-', ' a ', $raw).' años';
    }

    private static function personsLabel(int $persons): string
    {
        if ($persons === 1) {
            return '1 persona';
        }

        return $persons.' personas';
    }

    private static function dateLabel(IndividualQuote $quote): string
    {
        $raw = $quote->getAttributes()['created_at'] ?? null;

        if ($raw === null || $raw === '') {
            return '';
        }

        try {
            return Carbon::parse($raw)->format('d/m/Y');
        } catch (Throwable) {
            return '';
        }
    }

    private static function agentLabel(mixed $createdBy): string
    {
        $raw = trim((string) $createdBy);

        if ($raw === '' || str_contains(mb_strtoupper($raw, 'UTF-8'), 'PWA')) {
            return 'Tu Dr En Casa';
        }

        return StorefrontPlanNarrative::personName($raw);
    }
}
