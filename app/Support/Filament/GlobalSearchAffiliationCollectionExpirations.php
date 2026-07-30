<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\Collection as BillingCollection;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\HtmlString;

final class GlobalSearchAffiliationCollectionExpirations
{
    public const STATUS_POR_PAGAR = 'POR PAGAR';

    /**
     * Entre cobranzas POR PAGAR, elige la fila a mostrar como «próximo pago» según `next_payment_date`:
     * la fecha más próxima en el futuro (o hoy); si todas ya vencieron, la más antigua
     * (primera cuota impaga en la cola). Las fechas persistidas se interpretan como `d/m/Y`.
     *
     * @param  EloquentCollection<int, BillingCollection>  $rows  Cobranzas ya filtradas por estatus POR PAGAR
     */
    public static function pickNextCollectionRow(Carbon $today, EloquentCollection $rows): ?BillingCollection
    {
        if ($rows->isEmpty()) {
            return null;
        }

        $todayStart = $today->copy()->startOfDay();

        /** @var list<array{row: BillingCollection, paymentDate: Carbon}> $valid */
        $valid = [];
        foreach ($rows as $row) {
            $paymentDate = self::parseStoredDateToStartOfDay($row->next_payment_date);
            if ($paymentDate === null) {
                continue;
            }

            $valid[] = [
                'row' => $row,
                'paymentDate' => $paymentDate,
            ];
        }

        if ($valid === []) {
            return null;
        }

        usort($valid, static function (array $a, array $b): int {
            return $a['paymentDate']->timestamp <=> $b['paymentDate']->timestamp;
        });

        foreach ($valid as $item) {
            if ($item['paymentDate']->greaterThanOrEqualTo($todayStart)) {
                return $item['row'];
            }
        }

        return $valid[0]['row'];
    }

    /**
     * Fechas de cobranza y de venta persisten como `d/m/Y` (p. ej. 04/10/2025 = 4 de octubre de 2025).
     * Si no coincide el formato, se intenta `Y-m-d` y luego el análisis por defecto de Carbon.
     */
    public static function parseStoredDateToStartOfDay(mixed $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }

        foreach (['d/m/Y', 'j/n/Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $str);

                return $parsed->startOfDay();
            } catch (\Throwable) {
                // siguiente formato
            }
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $str) === 1) {
            try {
                return Carbon::parse($str)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse($str)->startOfDay();
        } catch (\Throwable) {
            $ts = strtotime($str);

            return $ts !== false ? Carbon::createFromTimestamp($ts)->startOfDay() : null;
        }
    }

    /**
     * Días calendario de atraso desde la fecha de próximo pago (persistida, `d/m/Y`) hasta `$today`,
     * si esa fecha es anterior a hoy.
     */
    public static function calendarDaysOverdueSinceStoredExpiration(mixed $paymentDate, Carbon $today): ?int
    {
        $expiration = self::parseStoredDateToStartOfDay($paymentDate);
        if ($expiration === null) {
            return null;
        }

        $todayStart = $today->copy()->startOfDay();
        if ($expiration->greaterThanOrEqualTo($todayStart)) {
            return null;
        }

        return (int) $expiration->diffInDays($todayStart);
    }

    /**
     * @param  list<Carbon>  $sortedDates  Fechas válidas ordenadas ascendente (startOfDay)
     */
    public static function pickNextPaymentDate(Carbon $today, array $sortedDates): ?Carbon
    {
        if ($sortedDates === []) {
            return null;
        }

        $todayStart = $today->copy()->startOfDay();

        foreach ($sortedDates as $date) {
            if ($date->greaterThanOrEqualTo($todayStart)) {
                return $date;
            }
        }

        return $sortedDates[0];
    }

    /**
     * Separa cobranzas POR PAGAR en vencidas (fecha anterior a hoy) y vigentes/futuras (fecha de hoy o posterior).
     * Ambas listas quedan ordenadas por `next_payment_date` ascendente.
     *
     * @param  EloquentCollection<int, BillingCollection>  $rows
     * @return array{overdue: list<BillingCollection>, upcoming: list<BillingCollection>}
     */
    public static function partitionPorPagarByOverdue(Carbon $today, EloquentCollection $rows): array
    {
        $todayStart = $today->copy()->startOfDay();

        /** @var list<array{row: BillingCollection, paymentDate: Carbon}> $overdue */
        $overdue = [];
        /** @var list<array{row: BillingCollection, paymentDate: Carbon}> $upcoming */
        $upcoming = [];

        foreach ($rows as $row) {
            $paymentDate = self::parseStoredDateToStartOfDay($row->next_payment_date);
            if ($paymentDate === null) {
                continue;
            }

            $item = [
                'row' => $row,
                'paymentDate' => $paymentDate,
            ];

            if ($paymentDate->lessThan($todayStart)) {
                $overdue[] = $item;
            } else {
                $upcoming[] = $item;
            }
        }

        $byDate = static fn (array $a, array $b): int => $a['paymentDate']->timestamp <=> $b['paymentDate']->timestamp;
        usort($overdue, $byDate);
        usort($upcoming, $byDate);

        return [
            'overdue' => array_column($overdue, 'row'),
            'upcoming' => array_column($upcoming, 'row'),
        ];
    }

    public static function paymentExpirationDetailsValue(?string $affiliationCode): HtmlString|string
    {
        if (! filled($affiliationCode)) {
            return '—';
        }

        $rows = BillingCollection::query()
            ->where('affiliation_code', $affiliationCode)
            ->where('status', self::STATUS_POR_PAGAR)
            ->whereNotNull('next_payment_date')
            ->with([
                'sale' => static function (Relation $relation): void {
                    $relation->select([
                        'id',
                        'date_activation',
                        'payment_frequency',
                    ]);
                },
            ])
            ->get(['id', 'sale_id', 'next_payment_date', 'payment_frequency']);

        if ($rows->isEmpty()) {
            return 'Sin cobranzas POR PAGAR';
        }

        $today = now();
        $partition = self::partitionPorPagarByOverdue($today, $rows);
        $overdueRows = $partition['overdue'];
        $upcomingRows = $partition['upcoming'];

        if ($overdueRows === [] && $upcomingRows === []) {
            return '—';
        }

        $nextRow = $upcomingRows[0] ?? $overdueRows[0] ?? null;
        if ($nextRow === null) {
            return '—';
        }

        $sale = null;
        if ($nextRow->relationLoaded('sale')) {
            /** @var Sale|null $loadedSale */
            $loadedSale = $nextRow->getRelation('sale');
            $sale = $loadedSale;
        }

        if ($sale === null && filled($nextRow->sale_id)) {
            $sale = Sale::query()
                ->select(['id', 'date_activation', 'payment_frequency'])
                ->find($nextRow->sale_id);
        }

        $desdeLabel = self::rawColumnForDisplay($sale, 'date_activation');
        $frequency = filled($sale?->payment_frequency)
            ? (string) $sale->payment_frequency
            : (filled($nextRow->payment_frequency) ? (string) $nextRow->payment_frequency : null);

        $html = '<div class="fi-global-search-payment-meta">';
        $html .= '<div><span class="fi-global-search-payment-meta__label">Desde:</span> ';
        $html .= '<span class="fi-global-search-payment-meta__value">'.e($desdeLabel).'</span></div>';

        foreach ($overdueRows as $overdueRow) {
            $overdueLabel = self::rawColumnForDisplay($overdueRow, 'next_payment_date');
            $overdueDays = self::calendarDaysOverdueSinceStoredExpiration($overdueRow->next_payment_date, $today);
            $html .= '<div><span class="fi-global-search-payment-meta__overdue-label">Pago vencido:</span> ';
            if ($overdueLabel !== '—') {
                $html .= '<span class="fi-global-search-payment-badge fi-global-search-payment-badge--overdue">'.e($overdueLabel).'</span>';
                if ($overdueDays !== null && $overdueDays > 0) {
                    $html .= ' <span class="fi-global-search-payment-meta__overdue-days" title="Días transcurridos desde el vencimiento">('.e((string) $overdueDays).' días vencidos)</span>';
                }
            } else {
                $html .= '<span>—</span>';
            }
            $html .= '</div>';
        }

        if ($upcomingRows !== []) {
            $proximoLabel = self::rawColumnForDisplay($upcomingRows[0], 'next_payment_date');
            $html .= '<div><span class="fi-global-search-payment-meta__label">Próximo pago';
            if (filled($frequency)) {
                $html .= ' <span class="fi-global-search-payment-meta__freq">('.e($frequency).')</span>';
            }
            $html .= ':</span> ';
            if ($proximoLabel !== '—') {
                $html .= '<span class="fi-global-search-payment-badge fi-global-search-payment-badge--upcoming">'.e($proximoLabel).'</span>';
            } else {
                $html .= '<span>—</span>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    /**
     * Valor de columna tal como viene persistido (sin reformatear) para mostrar en búsqueda global.
     */
    private static function rawColumnForDisplay(?Model $model, string $column): string
    {
        if ($model === null) {
            return '—';
        }

        $original = $model->getRawOriginal();
        if (is_array($original) && array_key_exists($column, $original)) {
            $val = $original[$column];
            if ($val !== null && $val !== '') {
                return (string) $val;
            }
        }

        $attrs = $model->getAttributes();
        if (array_key_exists($column, $attrs) && is_string($attrs[$column]) && $attrs[$column] !== '') {
            return $attrs[$column];
        }

        return '—';
    }
}
