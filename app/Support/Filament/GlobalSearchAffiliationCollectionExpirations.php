<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\Collection as BillingCollection;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection as IlluminateCollection;
use Illuminate\Support\HtmlString;

final class GlobalSearchAffiliationCollectionExpirations
{
    public const STATUS_POR_PAGAR = 'POR PAGAR';

    /**
     * Entre cobranzas POR PAGAR, elige la fila a mostrar como «próximo pago»:
     * el vencimiento más próximo en el futuro (o hoy); si todos ya vencieron, el más antiguo
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

        /** @var list<array{row: BillingCollection, expiration: Carbon}> $valid */
        $valid = [];
        foreach ($rows as $row) {
            $expiration = self::parseStoredDateToStartOfDay($row->expiration_date);
            if ($expiration === null) {
                continue;
            }

            $valid[] = [
                'row' => $row,
                'expiration' => $expiration,
            ];
        }

        if ($valid === []) {
            return $rows->first();
        }

        usort($valid, static function (array $a, array $b): int {
            return $a['expiration']->timestamp <=> $b['expiration']->timestamp;
        });

        foreach ($valid as $item) {
            if ($item['expiration']->greaterThanOrEqualTo($todayStart)) {
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
     * Días calendario de atraso desde `expiration_date` (persistido, `d/m/Y`) hasta `$today`, si el vencimiento es anterior a hoy.
     */
    public static function calendarDaysOverdueSinceStoredExpiration(mixed $expirationDate, Carbon $today): ?int
    {
        $expiration = self::parseStoredDateToStartOfDay($expirationDate);
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

    public static function paymentExpirationDetailsValue(?string $affiliationCode): HtmlString|string
    {
        if (! filled($affiliationCode)) {
            return '—';
        }

        $rows = BillingCollection::query()
            ->where('affiliation_code', $affiliationCode)
            ->where('status', self::STATUS_POR_PAGAR)
            ->whereNotNull('expiration_date')
            ->with([
                'sale' => static function (Relation $relation): void {
                    $relation->select([
                        'id',
                        'date_activation',
                        'payment_frequency',
                    ]);
                },
            ])
            ->get(['id', 'sale_id', 'expiration_date', 'payment_frequency']);

        if ($rows->isEmpty()) {
            return 'Sin cobranzas POR PAGAR';
        }

        /** @var IlluminateCollection<int, Carbon> $parsed */
        $parsed = collect();
        foreach ($rows as $row) {
            $d = self::parseStoredDateToStartOfDay($row->expiration_date);
            if ($d !== null) {
                $parsed->push($d);
            }
        }

        if ($parsed->isEmpty()) {
            return '—';
        }

        $nextRow = self::pickNextCollectionRow(now(), $rows);

        $sale = null;
        if ($nextRow?->relationLoaded('sale')) {
            /** @var Sale|null $loadedSale */
            $loadedSale = $nextRow->getRelation('sale');
            $sale = $loadedSale;
        }

        if ($sale === null && filled($nextRow?->sale_id)) {
            $sale = Sale::query()
                ->select(['id', 'date_activation', 'payment_frequency'])
                ->find($nextRow->sale_id);
        }

        $desdeLabel = self::rawColumnForDisplay($sale, 'date_activation');
        $proximoLabel = self::rawColumnForDisplay($nextRow, 'expiration_date');
        $frequency = filled($sale?->payment_frequency)
            ? (string) $sale->payment_frequency
            : (filled($nextRow?->payment_frequency) ? (string) $nextRow->payment_frequency : null);

        $html = '<div class="space-y-1.5 text-[11px] leading-snug">';
        $html .= '<div class="text-gray-600 dark:text-gray-400"><span class="font-medium text-gray-700 dark:text-gray-300">Desde:</span> ';
        $html .= '<span class="font-semibold text-gray-900 dark:text-gray-100">'.e($desdeLabel).'</span></div>';

        $html .= '<div class="text-gray-600 dark:text-gray-400"><span class="font-medium text-gray-700 dark:text-gray-300">Próximo pago';
        if (filled($frequency)) {
            $html .= ' <span class="font-normal text-gray-500 dark:text-gray-500">('.e($frequency).')</span>';
        }
        $html .= ':</span> ';
        if ($proximoLabel !== '—') {
            $html .= '<span class="inline-flex items-center rounded-md bg-amber-100 px-1.5 py-0.5 font-semibold text-amber-950 ring-1 ring-amber-300/80 dark:bg-amber-500/15 dark:text-amber-100 dark:ring-amber-400/35">'.e($proximoLabel).'</span>';
            $overdueDays = self::calendarDaysOverdueSinceStoredExpiration($nextRow?->expiration_date, now());
            if ($overdueDays !== null && $overdueDays > 0) {
                $html .= ' <span class="text-rose-600 dark:text-rose-400" title="Días transcurridos desde el vencimiento">('.e((string) $overdueDays).' días vencidos)</span>';
            }
        } else {
            $html .= '<span>—</span>';
        }
        $html .= '</div>';

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
