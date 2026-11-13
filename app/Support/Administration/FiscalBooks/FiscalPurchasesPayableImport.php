<?php

declare(strict_types=1);

namespace App\Support\Administration\FiscalBooks;

use App\Enums\FiscalBook;
use App\Models\FiscalBookEntry;
use App\Models\OperationAccountsPayable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Trae al Libro de Compras las facturas de proveedores registradas en cuentas
 * por pagar de Operaciones.
 *
 * Las cuentas por pagar están en dólares y el libro va en bolívares: el analista
 * indica la tasa BCV de cada fecha de factura (el sistema no guarda tasas
 * históricas). Se importan como exentas; la que tenga IVA se ajusta después.
 * Todo o nada: si una falla, no se importa ninguna.
 */
final class FiscalPurchasesPayableImport
{
    public const MAX_ROWS = 300;

    /**
     * Facturas de cuentas por pagar fechadas hasta el fin del mes que aún no
     * están en ningún mes del libro de compras.
     *
     * @return list<array{id: int, date: string, number: string, control: string|null, supplier: string, rif: string|null, amount: string, currency: string}>
     */
    public static function pending(CarbonImmutable $period): array
    {
        $imported = FiscalBookEntry::query()
            ->forBook(FiscalBook::Purchases)
            ->where('source', FiscalBookEntry::SOURCE_PAYABLE)
            ->whereNotNull('source_id')
            ->pluck('source_id')
            ->map(fn ($id): int => (int) $id)
            ->flip();

        $existingKeys = FiscalBookEntry::query()
            ->forBook(FiscalBook::Purchases)
            ->get(['document_number_key', 'counterparty_rif'])
            ->map(fn (FiscalBookEntry $entry): string => $entry->document_number_key.'|'.$entry->counterparty_rif)
            ->flip();

        return OperationAccountsPayable::query()
            ->whereNotNull('invoice_date')
            ->where('invoice_date', '<=', $period->endOfMonth()->toDateString())
            ->where(fn ($query) => $query->whereNotNull('invoice_number')->where('invoice_number', '<>', ''))
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->limit(2000)
            ->get(['id', 'invoice_date', 'invoice_number', 'invoice_control_number', 'supplier_name', 'supplier_rif', 'invoice_amount', 'invoice_currency'])
            ->reject(fn (OperationAccountsPayable $payable): bool => $imported->has((int) $payable->getKey())
                || $existingKeys->has(FiscalBookEntry::documentNumberKey((string) $payable->invoice_number).'|'.FiscalBookEntryWriter::rif($payable->supplier_rif)))
            ->map(fn (OperationAccountsPayable $payable): array => [
                'id' => (int) $payable->getKey(),
                'date' => CarbonImmutable::parse($payable->invoice_date)->toDateString(),
                'number' => trim((string) $payable->invoice_number),
                'control' => filled($payable->invoice_control_number) ? trim((string) $payable->invoice_control_number) : null,
                'supplier' => trim((string) ($payable->supplier_name ?: 'PROVEEDOR SIN NOMBRE')),
                'rif' => FiscalBookEntryWriter::rif($payable->supplier_rif),
                'amount' => FiscalAmount::of((string) $payable->invoice_amount),
                'currency' => self::currency($payable->invoice_currency),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int|string>  $payableIds
     * @param  array<string, mixed>  $ratesByDate  'Y-m-d' => tasa BCV
     * @return int Facturas importadas.
     */
    public static function import(CarbonImmutable $period, array $payableIds, array $ratesByDate, ?User $user): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $payableIds), fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            throw new InvalidArgumentException('Marque al menos una factura para importar.');
        }

        if (count($ids) > self::MAX_ROWS) {
            throw new InvalidArgumentException('Importe como máximo '.self::MAX_ROWS.' facturas por vez.');
        }

        $pending = collect(self::pending($period))->keyBy('id');
        $missing = array_diff($ids, $pending->keys()->all());

        if ($missing !== []) {
            throw new InvalidArgumentException('Algunas facturas marcadas ya están en el libro o no existen. Vuelva a abrir la importación.');
        }

        $rows = [];

        foreach ($ids as $id) {
            $payable = $pending->get($id);
            $rate = null;
            $amountVes = $payable['amount'];

            if ($payable['currency'] === 'USD') {
                $rate = self::rate($ratesByDate[$payable['date']] ?? null);

                if ($rate === null) {
                    throw new InvalidArgumentException('Indique la tasa BCV del '.CarbonImmutable::parse($payable['date'])->format('d/m/Y').' (factura '.$payable['number'].').');
                }

                $amountVes = FiscalAmount::convert($payable['amount'], $rate);
            }

            $rows[] = [$payable, $amountVes, $rate];
        }

        return DB::transaction(function () use ($rows, $period, $user): int {
            foreach ($rows as [$payable, $amountVes, $rate]) {
                FiscalBookEntryWriter::create(FiscalBook::Purchases, [
                    'period' => $period->format('Y-m'),
                    'document_date' => $payable['date'],
                    'document_type' => FiscalBookEntry::TYPE_INVOICE,
                    'document_number' => $payable['number'],
                    'control_number' => $payable['control'],
                    'counterparty_name' => $payable['supplier'],
                    'counterparty_rif' => $payable['rif'],
                    'taxpayer_type' => FiscalBookEntry::TAXPAYER,
                    'exempt_amount' => $amountVes,
                    'export_amount' => '0',
                    'taxable_base' => '0',
                    'tax_rate' => FiscalBookLedger::RATE_GENERAL,
                ], $user, [
                    'source' => FiscalBookEntry::SOURCE_PAYABLE,
                    'source_id' => $payable['id'],
                    'source_amount_usd' => $payable['currency'] === 'USD' ? $payable['amount'] : null,
                    'exchange_rate' => $rate,
                ]);
            }

            return count($rows);
        });
    }

    private static function currency(mixed $currency): string
    {
        $currency = mb_strtoupper(trim((string) $currency));

        return in_array($currency, ['VES', 'BS', 'BS.', 'BSD', 'BOLIVARES', 'BOLÍVARES'], true) ? 'VES' : 'USD';
    }

    private static function rate(mixed $value): ?string
    {
        try {
            $rate = FiscalAmount::precise($value, 8);
        } catch (InvalidArgumentException) {
            return null;
        }

        return bccomp($rate, '0', 8) > 0 ? $rate : null;
    }
}
