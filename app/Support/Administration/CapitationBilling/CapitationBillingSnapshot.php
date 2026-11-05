<?php

declare(strict_types=1);

namespace App\Support\Administration\CapitationBilling;

use App\Models\CapitationBillingClosing;
use Carbon\CarbonImmutable;

/**
 * Pre-facturación de capitado de un proveedor en un mes, lista para escribir en cualquier
 * formato. Sale del cierre guardado (mes cerrado) o del cálculo en vivo (preliminar).
 *
 * Cada fila es un arreglo con las mismas claves que `capitation_billing_closing_items`.
 *
 * @phpstan-type BillingRow array{section: string, subject_type: string, subject_id: int, holder_group: string, holder_document_type: ?string, holder_document: ?string, affiliate_name: string, affiliate_document_type: ?string, affiliate_document: ?string, sex: ?string, birth_date: ?CarbonImmutable, relationship: ?string, capitation_plan: ?string, tdec_plan: ?string, enrolled_on: ?CarbonImmutable, monthly_rate: float, prorate_days: ?int, amount: float, retired_on: ?CarbonImmutable, observation: ?string}
 */
final readonly class CapitationBillingSnapshot
{
    /**
     * @param  list<BillingRow>  $monthRows
     * @param  list<BillingRow>  $prorateRows
     * @param  list<BillingRow>  $retirementRows
     * @param  list<array{concept: string, amount: float}>  $discounts  montos positivos: se restan
     * @param  list<string>  $notes  avisos para el analista (afiliados sin tarifa, sin cierre anterior…)
     */
    public function __construct(
        public int $supplierId,
        public string $supplierName,
        public CarbonImmutable $period,
        public array $monthRows,
        public array $prorateRows,
        public array $retirementRows,
        public array $discounts,
        public ?float $bcvRate,
        public array $notes,
        public ?CapitationBillingClosing $closing,
        public CarbonImmutable $generatedAt,
    ) {}

    public function isClosed(): bool
    {
        return $this->closing?->isClosed() ?? false;
    }

    public function monthAmount(): float
    {
        return self::sum($this->monthRows);
    }

    public function prorateAmount(): float
    {
        return self::sum($this->prorateRows);
    }

    public function discountsAmount(): float
    {
        return round(array_sum(array_map(fn (array $discount): float => (float) $discount['amount'], $this->discounts)), 2);
    }

    public function totalAmount(): float
    {
        return round($this->monthAmount() + $this->prorateAmount() - $this->discountsAmount(), 2);
    }

    public function totalVes(): ?float
    {
        return $this->bcvRate !== null && $this->bcvRate > 0 ? round($this->totalAmount() * $this->bcvRate, 2) : null;
    }

    /**
     * Afiliados del mes agrupados por tomador, en orden alfabético, con su subtotal.
     *
     * @return list<array{group: string, rows: list<BillingRow>, count: int, amount: float}>
     */
    public function groups(): array
    {
        $groups = [];

        foreach ($this->monthRows as $row) {
            $groups[$row['holder_group']][] = $row;
        }

        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values(array_map(
            fn (array $rows, string $group): array => ['group' => $group, 'rows' => $rows, 'count' => count($rows), 'amount' => self::sum($rows)],
            $groups,
            array_keys($groups),
        ));
    }

    /**
     * @return array{affiliates: int, month_amount: float, prorate_count: int, prorate_amount: float, retirements: int, discounts_amount: float, total_amount: float, bcv_rate: ?float, total_ves: ?float}
     */
    public function totals(): array
    {
        return [
            'affiliates' => count($this->monthRows),
            'month_amount' => $this->monthAmount(),
            'prorate_count' => count($this->prorateRows),
            'prorate_amount' => $this->prorateAmount(),
            'retirements' => count($this->retirementRows),
            'discounts_amount' => $this->discountsAmount(),
            'total_amount' => $this->totalAmount(),
            'bcv_rate' => $this->bcvRate,
            'total_ves' => $this->totalVes(),
        ];
    }

    public function periodLabel(): string
    {
        return mb_strtoupper($this->period->locale('es')->translatedFormat('F Y'));
    }

    public function previousPeriodLabel(): string
    {
        return mb_strtoupper($this->period->subMonthNoOverflow()->locale('es')->translatedFormat('F Y'));
    }

    /**
     * Suma por centavos: evita que 0,1 + 0,2 se desvíe en reportes de cientos de filas.
     *
     * @param  list<BillingRow>  $rows
     */
    public static function sum(array $rows): float
    {
        $cents = 0;

        foreach ($rows as $row) {
            $cents += (int) round(((float) $row['amount']) * 100);
        }

        return round($cents / 100, 2);
    }
}
