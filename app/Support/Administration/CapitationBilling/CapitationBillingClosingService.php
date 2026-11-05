<?php

declare(strict_types=1);

namespace App\Support\Administration\CapitationBilling;

use App\Models\CapitationBillingClosing;
use App\Models\CapitationBillingClosingEvent;
use App\Models\CapitationBillingClosingItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\SecurityAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Cierra o reabre la pre-facturación de capitado de un proveedor en un mes.
 *
 * Cerrar guarda la foto exacta (afiliados, tarifas, prorrateos, retiros, descuentos y tasa
 * BCV) en una transacción con bloqueo: dos analistas no pueden cerrar el mismo mes a la vez,
 * y un cierre a medias nunca queda guardado. Reabrir exige una razón y deja el cierre como
 * REABIERTO: el reporte vuelve a calcularse en vivo hasta que se cierre de nuevo.
 */
final class CapitationBillingClosingService
{
    public const MAX_DISCOUNTS = 30;

    /**
     * @param  list<array{concept: string, amount: float}>  $discounts
     */
    public static function close(Supplier $supplier, CarbonImmutable $period, array $discounts, ?float $bcvRate, User $user, ?CarbonImmutable $now = null): CapitationBillingClosing
    {
        $now ??= CarbonImmutable::now();
        $period = self::assertPeriod($period, $now);
        $discounts = self::normalizeDiscounts($discounts);
        $bcvRate = self::normalizeRate($bcvRate);

        return DB::transaction(function () use ($supplier, $period, $discounts, $bcvRate, $user, $now): CapitationBillingClosing {
            $closing = CapitationBillingClosing::query()
                ->where('supplier_id', $supplier->getKey())
                ->where('period', $period->toDateString())
                ->lockForUpdate()
                ->first();

            if ($closing?->isClosed()) {
                throw new InvalidArgumentException('Ese mes ya está cerrado para '.$supplier->name.'. Para volver a cerrarlo, reábralo primero con una razón.');
            }

            $snapshot = CapitationBillingCalculator::compute($supplier, $period, $discounts, $bcvRate, $now);

            if ($snapshot->monthRows === [] && $snapshot->prorateRows === []) {
                throw new InvalidArgumentException('No hay afiliados con capitado de '.$supplier->name.' en '.mb_strtolower($snapshot->periodLabel()).': no hay nada que cerrar.');
            }

            $previous = CapitationBillingClosing::query()
                ->where('supplier_id', $supplier->getKey())
                ->where('period', $period->subMonthNoOverflow()->toDateString())
                ->value('id');

            $closing ??= new CapitationBillingClosing([
                'supplier_id' => $supplier->getKey(),
                'period' => $period->toDateString(),
            ]);

            $totals = $snapshot->totals();

            $closing->forceFill([
                'supplier_name' => Str::limit((string) $supplier->name, 250, ''),
                'status' => CapitationBillingClosing::STATUS_CLOSED,
                'affiliates_count' => $totals['affiliates'],
                'monthly_amount' => $totals['month_amount'],
                'prorate_count' => $totals['prorate_count'],
                'prorate_amount' => $totals['prorate_amount'],
                'retirements_count' => $totals['retirements'],
                'discounts' => $discounts,
                'discounts_amount' => $totals['discounts_amount'],
                'total_amount' => $totals['total_amount'],
                'bcv_rate' => $bcvRate,
                'total_ves' => $totals['total_ves'],
                'previous_closing_id' => $previous,
                'closed_at' => $now,
                'closed_by_user_id' => $user->getKey(),
                'closed_by_name' => Str::limit((string) $user->name, 250, ''),
            ])->save();

            $closing->items()->delete();

            foreach (array_chunk([...$snapshot->monthRows, ...$snapshot->prorateRows, ...$snapshot->retirementRows], 500) as $chunk) {
                CapitationBillingClosingItem::query()->insert(array_map(fn (array $row): array => self::itemAttributes((int) $closing->getKey(), $row, $now), $chunk));
            }

            CapitationBillingClosingEvent::query()->create([
                'closing_id' => $closing->getKey(),
                'action' => CapitationBillingClosingEvent::ACTION_CLOSED,
                'totals' => $totals,
                'user_id' => $user->getKey(),
                'user_name' => $user->name,
            ]);

            SecurityAudit::log('AUDIT_CAPITATION_BILLING_CLOSED', 'administration.capitation-billing.close', [
                'closing_id' => $closing->getKey(),
                'supplier_id' => $supplier->getKey(),
                'period' => $period->toDateString(),
                'totals' => $totals,
            ], $user);

            return $closing->refresh();
        });
    }

    public static function reopen(CapitationBillingClosing $closing, string $reason, User $user): CapitationBillingClosing
    {
        $reason = Str::squish($reason);

        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('Indique la razón para reabrir el mes (mínimo 10 caracteres).');
        }

        return DB::transaction(function () use ($closing, $reason, $user): CapitationBillingClosing {
            $locked = CapitationBillingClosing::query()->lockForUpdate()->findOrFail($closing->getKey());

            if (! $locked->isClosed()) {
                throw new InvalidArgumentException('Ese mes no está cerrado.');
            }

            $next = CapitationBillingClosing::query()
                ->where('supplier_id', $locked->supplier_id)
                ->where('period', $locked->period->addMonthNoOverflow()->toDateString())
                ->where('status', CapitationBillingClosing::STATUS_CLOSED)
                ->exists();

            if ($next) {
                throw new InvalidArgumentException('El mes siguiente ya está cerrado y se calculó a partir de este. Reabra primero el mes siguiente.');
            }

            $locked->forceFill([
                'status' => CapitationBillingClosing::STATUS_REOPENED,
                'reopened_at' => now(),
                'reopened_by_name' => Str::limit((string) $user->name, 250, ''),
                'reopen_reason' => Str::limit($reason, 2000, ''),
            ])->save();

            CapitationBillingClosingEvent::query()->create([
                'closing_id' => $locked->getKey(),
                'action' => CapitationBillingClosingEvent::ACTION_REOPENED,
                'reason' => $reason,
                'totals' => [
                    'affiliates' => $locked->affiliates_count,
                    'total_amount' => (float) $locked->total_amount,
                ],
                'user_id' => $user->getKey(),
                'user_name' => $user->name,
            ]);

            SecurityAudit::log('AUDIT_CAPITATION_BILLING_REOPENED', 'administration.capitation-billing.reopen', [
                'closing_id' => $locked->getKey(),
                'supplier_id' => $locked->supplier_id,
                'period' => $locked->period->toDateString(),
                'reason' => $reason,
            ], $user);

            return $locked->refresh();
        });
    }

    /**
     * La pre-facturación del mes: la foto guardada si el mes está cerrado (los descuentos y la
     * tasa que se reciben se ignoran: valen los del cierre) o el cálculo en vivo si no.
     *
     * @param  list<array{concept: string, amount: float}>  $discounts
     */
    public static function snapshot(Supplier $supplier, CarbonImmutable $period, array $discounts = [], ?float $bcvRate = null): CapitationBillingSnapshot
    {
        $period = $period->startOfMonth();
        $closing = CapitationBillingClosing::query()
            ->where('supplier_id', $supplier->getKey())
            ->where('period', $period->toDateString())
            ->first();

        if ($closing?->isClosed()) {
            $items = $closing->items()->orderBy('id')->get()->groupBy('section');
            $rows = fn (string $section): array => CapitationBillingCalculator::sorted(
                $items->get($section, collect())->map(fn (CapitationBillingClosingItem $item): array => CapitationBillingCalculator::itemToRow($item))->values()->all(),
            );

            return new CapitationBillingSnapshot(
                supplierId: (int) $supplier->getKey(),
                supplierName: (string) $closing->supplier_name,
                period: $period,
                monthRows: $rows(CapitationBillingClosingItem::SECTION_MONTH),
                prorateRows: $rows(CapitationBillingClosingItem::SECTION_PRORATE),
                retirementRows: $rows(CapitationBillingClosingItem::SECTION_RETIREMENT),
                discounts: self::normalizeDiscounts($closing->discounts ?? []),
                bcvRate: $closing->bcv_rate !== null ? (float) $closing->bcv_rate : null,
                notes: ['Mes cerrado el '.$closing->closed_at?->format('d/m/Y h:i A').' por '.$closing->closed_by_name.'. Este reporte reproduce exactamente ese cierre.'],
                closing: $closing,
                generatedAt: CarbonImmutable::now(),
            );
        }

        $snapshot = CapitationBillingCalculator::compute($supplier, $period, self::normalizeDiscounts($discounts), self::normalizeRate($bcvRate));

        return new CapitationBillingSnapshot(
            supplierId: $snapshot->supplierId,
            supplierName: $snapshot->supplierName,
            period: $snapshot->period,
            monthRows: $snapshot->monthRows,
            prorateRows: $snapshot->prorateRows,
            retirementRows: $snapshot->retirementRows,
            discounts: $snapshot->discounts,
            bcvRate: $snapshot->bcvRate,
            notes: [
                'PRELIMINAR: el mes no está cerrado; las cifras se calcularon con los datos de hoy.'.($closing !== null ? ' Se reabrió el '.$closing->reopened_at?->format('d/m/Y').' por '.$closing->reopened_by_name.': '.$closing->reopen_reason : ''),
                ...$snapshot->notes,
            ],
            closing: $closing,
            generatedAt: $snapshot->generatedAt,
        );
    }

    /**
     * @param  array<int, mixed>  $discounts
     * @return list<array{concept: string, amount: float}>
     */
    public static function normalizeDiscounts(array $discounts): array
    {
        $clean = [];

        foreach ($discounts as $discount) {
            if (! is_array($discount)) {
                continue;
            }

            $concept = Str::limit(Str::squish((string) ($discount['concept'] ?? '')), 250, '');
            $amount = is_numeric($discount['amount'] ?? null) ? round(abs((float) $discount['amount']), 2) : 0.0;

            if ($concept === '' && $amount <= 0) {
                continue;
            }

            if ($concept === '') {
                throw new InvalidArgumentException('Cada descuento necesita su concepto.');
            }

            if ($amount <= 0) {
                throw new InvalidArgumentException('El descuento «'.$concept.'» necesita un monto mayor que cero.');
            }

            $clean[] = ['concept' => $concept, 'amount' => $amount];
        }

        if (count($clean) > self::MAX_DISCOUNTS) {
            throw new InvalidArgumentException('Puede cargar hasta '.self::MAX_DISCOUNTS.' descuentos.');
        }

        return $clean;
    }

    private static function normalizeRate(?float $rate): ?float
    {
        if ($rate === null) {
            return null;
        }

        if ($rate <= 0 || $rate > 1_000_000) {
            throw new InvalidArgumentException('La tasa BCV debe ser un número mayor que cero.');
        }

        return round($rate, 4);
    }

    private static function assertPeriod(CarbonImmutable $period, CarbonImmutable $now): CarbonImmutable
    {
        $period = $period->startOfMonth();

        if ($period->greaterThan($now->startOfMonth())) {
            throw new InvalidArgumentException('No se puede cerrar un mes que todavía no empieza.');
        }

        return $period;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function itemAttributes(int $closingId, array $row, CarbonImmutable $now): array
    {
        $date = fn (mixed $value): ?string => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : null;

        return [
            'closing_id' => $closingId,
            'section' => $row['section'],
            'subject_type' => $row['subject_type'],
            'subject_id' => $row['subject_id'],
            'holder_group' => Str::limit((string) $row['holder_group'], 250, ''),
            'holder_document_type' => $row['holder_document_type'] !== null ? Str::limit((string) $row['holder_document_type'], 2, '') : null,
            'holder_document' => $row['holder_document'] !== null ? Str::limit((string) $row['holder_document'], 40, '') : null,
            'affiliate_name' => Str::limit((string) $row['affiliate_name'], 250, ''),
            'affiliate_document_type' => $row['affiliate_document_type'] !== null ? Str::limit((string) $row['affiliate_document_type'], 2, '') : null,
            'affiliate_document' => $row['affiliate_document'] !== null ? Str::limit((string) $row['affiliate_document'], 40, '') : null,
            'sex' => $row['sex'] !== null ? Str::limit((string) $row['sex'], 20, '') : null,
            'birth_date' => $date($row['birth_date']),
            'relationship' => $row['relationship'] !== null ? Str::limit((string) $row['relationship'], 40, '') : null,
            'capitation_plan' => $row['capitation_plan'] !== null ? Str::limit((string) $row['capitation_plan'], 250, '') : null,
            'tdec_plan' => $row['tdec_plan'] !== null ? Str::limit((string) $row['tdec_plan'], 250, '') : null,
            'enrolled_on' => $date($row['enrolled_on']),
            'monthly_rate' => round((float) $row['monthly_rate'], 2),
            'prorate_days' => $row['prorate_days'],
            'amount' => round((float) $row['amount'], 2),
            'retired_on' => $date($row['retired_on']),
            'observation' => $row['observation'] !== null ? Str::limit((string) $row['observation'], 500, '') : null,
            'created_at' => $now->toDateTimeString(),
        ];
    }
}
