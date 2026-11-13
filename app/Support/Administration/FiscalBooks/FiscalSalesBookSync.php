<?php

declare(strict_types=1);

namespace App\Support\Administration\FiscalBooks;

use App\Enums\FiscalBook;
use App\Models\FiscalBookEntry;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Lleva al Libro de Ventas las facturas fiscales emitidas desde Ventas
 * (`sales.invoice_generated` + `sales.invoice_snapshot`).
 *
 * Reglas:
 * - Fecha y monto en bolívares salen de la foto de la factura (lo impreso).
 * - Si el número ya está en el libro (por ejemplo, del Excel histórico), no se duplica.
 * - Un mes cerrado nunca se toca.
 * - Si el analista corrigió la línea a mano, la sincronización no la pisa.
 * - Las facturas de Ventas son exentas; si alguna es gravada, el analista la ajusta.
 */
final class FiscalSalesBookSync
{
    /**
     * Sincroniza una venta. Nunca lanza: si falla, lo registra y devuelve null,
     * para no interrumpir la emisión de la factura.
     */
    public static function syncSaleSafely(Sale $sale, ?User $user): ?FiscalBookEntry
    {
        try {
            return self::syncSale($sale, $user)['entry'];
        } catch (Throwable $exception) {
            Log::warning('FiscalSalesBookSync: no se pudo llevar la factura al libro de ventas', [
                'sale_id' => $sale->getKey(),
                'invoice' => $sale->invoice_generated,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{entry: FiscalBookEntry|null, result: string}
     */
    public static function syncSale(Sale $sale, ?User $user): array
    {
        $data = self::invoiceData($sale);

        if ($data === null) {
            return ['entry' => null, 'result' => 'sin_factura'];
        }

        if ($data['total'] === null) {
            return ['entry' => null, 'result' => 'sin_monto'];
        }

        $period = CarbonImmutable::parse($data['date'])->startOfMonth();

        $linked = FiscalBookEntry::query()
            ->forBook(FiscalBook::Sales)
            ->where('source', FiscalBookEntry::SOURCE_SALE)
            ->where('source_id', $sale->getKey())
            ->first();

        if ($linked !== null) {
            if ($linked->manually_adjusted || $linked->isAnnulled()
                || FiscalBookPeriods::isClosed(FiscalBook::Sales, CarbonImmutable::parse($linked->period))
                || FiscalBookPeriods::isClosed(FiscalBook::Sales, $period)) {
                return ['entry' => $linked, 'result' => 'existente'];
            }

            return ['entry' => FiscalBookEntryWriter::update($linked, self::input($data, $period), $user, fromSource: true), 'result' => 'actualizada'];
        }

        $existing = FiscalBookEntry::query()
            ->forBook(FiscalBook::Sales)
            ->where('document_number_key', FiscalBookEntry::documentNumberKey($data['number']))
            ->where('document_type', FiscalBookEntry::TYPE_INVOICE)
            ->whereNull('series')
            ->first();

        if ($existing !== null) {
            return ['entry' => $existing, 'result' => 'existente'];
        }

        if (FiscalBookPeriods::isClosed(FiscalBook::Sales, $period)) {
            return ['entry' => null, 'result' => 'mes_cerrado'];
        }

        $entry = FiscalBookEntryWriter::create(FiscalBook::Sales, self::input($data, $period), $user, [
            'source' => FiscalBookEntry::SOURCE_SALE,
            'source_id' => $sale->getKey(),
        ], audit: false);

        return ['entry' => $entry, 'result' => 'creada'];
    }

    /**
     * Revisa todas las facturas emitidas desde Ventas con fecha en el mes.
     *
     * @return array{creadas: int, actualizadas: int, existentes: int, mes_cerrado: int, sin_monto: list<string>, errores: list<string>}
     */
    public static function syncPeriod(CarbonImmutable $period, ?User $user): array
    {
        $period = $period->startOfMonth();
        FiscalBookPeriods::assertOpen(FiscalBook::Sales, $period);

        $report = ['creadas' => 0, 'actualizadas' => 0, 'existentes' => 0, 'mes_cerrado' => 0, 'sin_monto' => [], 'errores' => []];
        $suffix = $period->format('/m/Y');

        $sales = Sale::query()
            ->whereNotNull('invoice_generated')
            ->where('invoice_generated', '<>', '')
            ->where(function ($query) use ($suffix, $period): void {
                $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(invoice_snapshot, '$.date')) LIKE ?", ['%'.$suffix])
                    ->orWhere(function ($fallback) use ($period): void {
                        $fallback->whereRaw("(invoice_snapshot IS NULL OR JSON_EXTRACT(invoice_snapshot, '$.date') IS NULL)")
                            ->whereBetween('created_at', [$period->toDateTimeString(), $period->endOfMonth()->toDateTimeString()]);
                    });
            })
            ->orderBy('id')
            ->get();

        foreach ($sales as $sale) {
            try {
                $result = self::syncSale($sale, $user)['result'];
            } catch (InvalidArgumentException $exception) {
                $report['errores'][] = 'Factura '.$sale->invoice_generated.': '.$exception->getMessage();

                continue;
            }

            match ($result) {
                'creada' => $report['creadas']++,
                'actualizada' => $report['actualizadas']++,
                'existente' => $report['existentes']++,
                'mes_cerrado' => $report['mes_cerrado']++,
                'sin_monto' => $report['sin_monto'][] = (string) $sale->invoice_generated,
                default => null,
            };
        }

        return $report;
    }

    /**
     * @return array{number: string, date: string, total: string|null, name: string, rif: string|null}|null
     */
    public static function invoiceData(Sale $sale): ?array
    {
        $number = trim((string) $sale->invoice_generated);

        if ($number === '') {
            return null;
        }

        $snapshot = is_array($sale->invoice_snapshot) ? $sale->invoice_snapshot : [];
        $party = is_array($snapshot['billing_party'] ?? null) ? $snapshot['billing_party'] : [];

        $date = null;

        if (is_string($snapshot['date'] ?? null) && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $snapshot['date']) === 1) {
            $date = CarbonImmutable::createFromFormat('d/m/Y', $snapshot['date'])->toDateString();
        }

        $date ??= CarbonImmutable::parse($sale->created_at ?? now())->toDateString();

        $total = is_numeric($snapshot['total_ves'] ?? null) && (float) $snapshot['total_ves'] > 0
            ? FiscalAmount::of((string) $snapshot['total_ves'])
            : null;

        $name = trim((string) ($party['full_name_ti'] ?? ''));

        if ($name === '') {
            $name = trim((string) ($sale->affiliate_full_name ?? '')) ?: 'CLIENTE SIN NOMBRE';
        }

        return [
            'number' => $number,
            'date' => $date,
            'total' => $total,
            'name' => $name,
            'rif' => self::rifFor($party['ci_rif_ti'] ?? $sale->affiliate_ci_rif ?? null),
        ];
    }

    /**
     * Las facturas guardan la cédula o el RIF sin letra. Nueve dígitos es un RIF
     * jurídico (J); menos, una cédula (V). El analista puede corregirlo.
     */
    public static function rifFor(mixed $value): ?string
    {
        $clean = mb_strtoupper(preg_replace('/[\s.\-]+/', '', (string) ($value ?? '')) ?? '');

        if ($clean === '') {
            return null;
        }

        if (preg_match('/^[VEJGPC]\d+$/', $clean) === 1) {
            return FiscalBookEntryWriter::rif($clean);
        }

        if (ctype_digit($clean)) {
            return (strlen($clean) >= 9 ? 'J-' : 'V-').$clean;
        }

        return FiscalBookEntryWriter::rif($clean);
    }

    /**
     * @param  array{number: string, date: string, total: string|null, name: string, rif: string|null}  $data
     * @return array<string, mixed>
     */
    private static function input(array $data, CarbonImmutable $period): array
    {
        return [
            'period' => $period->format('Y-m'),
            'document_date' => $data['date'],
            'document_type' => FiscalBookEntry::TYPE_INVOICE,
            'document_number' => $data['number'],
            'control_number' => $data['number'],
            'counterparty_name' => $data['name'],
            'counterparty_rif' => $data['rif'],
            'taxpayer_type' => FiscalBookEntry::TAXPAYER,
            'exempt_amount' => $data['total'],
            'export_amount' => '0',
            'taxable_base' => '0',
            'tax_rate' => FiscalBookLedger::RATE_GENERAL,
        ];
    }
}
