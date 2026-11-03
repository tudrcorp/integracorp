<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Jobs\PrepareAffiliationCorporateRenovations;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use Illuminate\Support\Collection;

/**
 * Realinea las filas de `afilliation_corporate_plans` con la población que de verdad
 * se cobra: cantidad de personas, tarifa por persona y subtotales por frecuencia.
 *
 * Los avisos de cobro y de pago imprimen cada fila con su subtotal guardado, mientras
 * que el «Monto Total» sale de la afiliación. Si la renovación sube la tarifa de los
 * afiliados y no toca las filas, el documento muestra dos montos distintos (caso ABP
 * LECHERIA: línea en 450,00 con total en 472,50).
 *
 * La fila lleva solo el plan: los upgrades se imprimen aparte, por eso la tarifa de
 * la fila es la del plan y no la del afiliado (que puede incluir upgrades).
 *
 * Nunca borra filas: una fila que se queda sin personas pasa a cero.
 */
final class CorporatePlanRowSync
{
    /**
     * @param  array<int, array{plan_fee: float, age_range_id: int|null}>  $repriced  Tarifa de plan
     *                                                                                (sin upgrades) y rango de edad recién calculados, por id de afiliado.
     *                                                                                Un afiliado sin entrada se ubica por edad y conserva la tarifa de su fila.
     * @return array{
     *     updated: int,
     *     created: int,
     *     emptied: int,
     *     unmatched: list<int>,
     *     rows: list<array{id: int|null, plan_id: int, coverage_id: int|null, age_range_id: int|null, before: array{fee: float, persons: int, annual: float}, after: array{fee: float, persons: int, annual: float}}>
     * }
     */
    public static function sync(AffiliationCorporate $affiliation, array $repriced = [], ?string $actor = null, bool $persist = true): array
    {
        $frequency = CorporatePaymentFrequency::normalize((string) $affiliation->payment_frequency)
            ?? CorporatePaymentFrequency::ANUAL;

        /** @var Collection<int, AfilliationCorporatePlan> $rows */
        $rows = AfilliationCorporatePlan::query()
            ->where('affiliation_corporate_id', $affiliation->getKey())
            ->with('ageRange')
            ->orderBy('id')
            ->get();

        /** @var Collection<int, AffiliateCorporate> $affiliates */
        $affiliates = AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $affiliation->getKey())
            ->whereIn('status', PrepareAffiliationCorporateRenovations::AFFILIATE_STATUSES_FOR_RENEWAL)
            ->get();

        /** @var array<int, list<float>> $feesByRow */
        $feesByRow = [];
        /** @var array<string, array{plan_id: int, coverage_id: int|null, age_range_id: int, fees: list<float>}> $newRows */
        $newRows = [];
        $unmatched = [];

        foreach ($affiliates as $affiliate) {
            $planId = (int) $affiliate->plan_id;
            $coverageId = self::nullableId($affiliate->coverage_id);
            $info = $repriced[(int) $affiliate->getKey()] ?? null;

            if ($info !== null && $info['age_range_id'] !== null) {
                $row = $rows->first(fn (AfilliationCorporatePlan $row): bool => self::sameLine($row, $planId, $coverageId)
                    && (int) $row->age_range_id === (int) $info['age_range_id']);

                if ($row !== null) {
                    $feesByRow[(int) $row->getKey()][] = round((float) $info['plan_fee'], 2);

                    continue;
                }

                $key = $planId.'|'.($coverageId ?? 'null').'|'.$info['age_range_id'];
                $newRows[$key] ??= [
                    'plan_id' => $planId,
                    'coverage_id' => $coverageId,
                    'age_range_id' => (int) $info['age_range_id'],
                    'fees' => [],
                ];
                $newRows[$key]['fees'][] = round((float) $info['plan_fee'], 2);

                continue;
            }

            $age = CorporateAffiliatePlanSynchronizer::ageOf($affiliate);
            $row = $age === null ? null : $rows->first(fn (AfilliationCorporatePlan $row): bool => self::sameLine($row, $planId, $coverageId)
                && $row->ageRange !== null
                && $age >= (int) $row->ageRange->age_init
                && $age <= (int) $row->ageRange->age_end);

            if ($row === null) {
                $unmatched[] = (int) $affiliate->getKey();

                continue;
            }

            $feesByRow[(int) $row->getKey()][] = round((float) $row->fee, 2);
        }

        $report = ['updated' => 0, 'created' => 0, 'emptied' => 0, 'unmatched' => $unmatched, 'rows' => []];

        foreach ($rows as $row) {
            $fees = $feesByRow[(int) $row->getKey()] ?? [];
            $before = self::snapshot((float) $row->fee, (int) $row->total_persons, (float) $row->subtotal_anual);
            $fee = $fees === [] ? (float) $row->fee : self::mostCommon($fees);
            $annual = round(array_sum($fees), 2);
            $after = self::snapshot($fee, count($fees), $annual);

            $report['rows'][] = [
                'id' => (int) $row->getKey(),
                'plan_id' => (int) $row->plan_id,
                'coverage_id' => self::nullableId($row->coverage_id),
                'age_range_id' => self::nullableId($row->age_range_id),
                'before' => $before,
                'after' => $after,
            ];

            $amountsChanged = $before !== $after
                || self::differs($row->subtotal_quarterly, $annual / 4)
                || self::differs($row->subtotal_biannual, $annual / 2)
                || self::differs($row->subtotal_monthly, $annual / 12)
                || (string) $row->payment_frequency !== $frequency;

            if (! $amountsChanged) {
                continue;
            }

            $report['updated']++;

            if ($fees === []) {
                $report['emptied']++;
            }

            if (! $persist) {
                continue;
            }

            $attributes = [
                'fee' => $fee,
                'total_persons' => count($fees),
                'payment_frequency' => $frequency,
                ...self::subtotals($annual),
            ];

            /** La tarifa ya no es la negociada: la población se cobra con la tarifa estándar. */
            if (self::differs($row->fee, $fee)) {
                $attributes['fee_source'] = CorporatePlanRowCreator::SOURCE_STANDARD;
            }

            $row->forceFill($attributes)->save();
        }

        foreach ($newRows as $line) {
            $fee = self::mostCommon($line['fees']);
            $annual = round(array_sum($line['fees']), 2);

            $report['created']++;
            $report['rows'][] = [
                'id' => null,
                'plan_id' => $line['plan_id'],
                'coverage_id' => $line['coverage_id'],
                'age_range_id' => $line['age_range_id'],
                'before' => self::snapshot(0.0, 0, 0.0),
                'after' => self::snapshot($fee, count($line['fees']), $annual),
            ];

            if (! $persist) {
                continue;
            }

            AfilliationCorporatePlan::query()->create([
                'affiliation_corporate_id' => $affiliation->getKey(),
                'code_affiliation' => $affiliation->code,
                'plan_id' => $line['plan_id'],
                'coverage_id' => $line['coverage_id'],
                'age_range_id' => $line['age_range_id'],
                'fee' => $fee,
                'fee_source' => CorporatePlanRowCreator::SOURCE_STANDARD,
                'total_persons' => count($line['fees']),
                'payment_frequency' => $frequency,
                'status' => 'ACTIVA',
                'created_by' => $actor,
                ...self::subtotals($annual),
            ]);
        }

        return $report;
    }

    /**
     * @return array{subtotal_anual: float, subtotal_quarterly: float, subtotal_biannual: float, subtotal_monthly: float}
     */
    public static function subtotals(float $annual): array
    {
        return [
            'subtotal_anual' => round($annual, 2),
            'subtotal_quarterly' => round($annual / 4, 2),
            'subtotal_biannual' => round($annual / 2, 2),
            'subtotal_monthly' => round($annual / 12, 2),
        ];
    }

    private static function sameLine(AfilliationCorporatePlan $row, int $planId, ?int $coverageId): bool
    {
        return (int) $row->plan_id === $planId && self::nullableId($row->coverage_id) === $coverageId;
    }

    private static function nullableId(mixed $value): ?int
    {
        return filled($value) && is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * @param  list<float>  $fees
     */
    private static function mostCommon(array $fees): float
    {
        $counts = array_count_values(array_map(fn (float $fee): string => number_format($fee, 2, '.', ''), $fees));
        arsort($counts);

        return (float) array_key_first($counts);
    }

    private static function differs(mixed $current, float $target): bool
    {
        return abs((float) $current - round($target, 2)) >= 0.01;
    }

    /**
     * @return array{fee: float, persons: int, annual: float}
     */
    private static function snapshot(float $fee, int $persons, float $annual): array
    {
        return ['fee' => round($fee, 2), 'persons' => $persons, 'annual' => round($annual, 2)];
    }
}
