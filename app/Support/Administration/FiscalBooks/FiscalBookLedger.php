<?php

declare(strict_types=1);

namespace App\Support\Administration\FiscalBooks;

use App\Enums\FiscalBook;
use App\Models\FiscalBookEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Lectura de un libro fiscal en un mes: orden de las líneas, número de
 * operación, totales y resumen de débitos o créditos fiscales.
 *
 * Los totales salen de una sola consulta con SUM sobre columnas DECIMAL, que
 * MySQL suma de forma exacta; aquí solo se normalizan como cadenas.
 */
final class FiscalBookLedger
{
    /** Alícuotas del resumen fiscal: general, reducida y general más adicional. */
    public const RATE_GENERAL = '16.00';

    public const RATE_REDUCED = '8.00';

    public const RATE_LUXURY = '31.00';

    /**
     * @var array<string, array<int, int>>
     */
    private static array $numbersCache = [];

    /**
     * @return array<string, string>
     */
    public static function rateOptions(): array
    {
        return [
            self::RATE_GENERAL => 'General 16 %',
            self::RATE_REDUCED => 'Reducida 8 %',
            self::RATE_LUXURY => 'General más adicional 31 %',
        ];
    }

    /**
     * @return Builder<FiscalBookEntry>
     */
    public static function query(FiscalBook $book, CarbonImmutable $period): Builder
    {
        return FiscalBookEntry::query()
            ->forBook($book)
            ->where('period', $period->startOfMonth()->toDateString());
    }

    /**
     * Orden del libro: el del Excel histórico cuando existe, y luego por fecha
     * del documento y orden de carga.
     *
     * @param  Builder<FiscalBookEntry>  $query
     * @return Builder<FiscalBookEntry>
     */
    public static function ordered(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN position IS NULL THEN 1 ELSE 0 END')
            ->orderBy('position')
            ->orderBy('document_date')
            ->orderBy('id');
    }

    /**
     * @return Collection<int, FiscalBookEntry>
     */
    public static function entries(FiscalBook $book, CarbonImmutable $period): Collection
    {
        return self::ordered(self::query($book, $period))->get();
    }

    /**
     * Número de operación de cada línea del mes (1, 2, 3…), estable aunque la
     * tabla se ordene, filtre o pagine distinto. Una consulta liviana de ids por mes.
     *
     * @return array<int, int>
     */
    public static function operationNumbers(FiscalBook $book, CarbonImmutable $period): array
    {
        $key = $book->value.'|'.$period->format('Y-m');

        if (isset(self::$numbersCache[$key])) {
            return self::$numbersCache[$key];
        }

        $ids = self::ordered(self::query($book, $period))->pluck('id')->all();
        $numbers = [];

        foreach ($ids as $index => $id) {
            $numbers[(int) $id] = $index + 1;
        }

        return self::$numbersCache[$key] = $numbers;
    }

    public static function flush(): void
    {
        self::$numbersCache = [];
    }

    /**
     * Totales del mes y resumen fiscal.
     *
     * @return array{
     *     documents: int,
     *     annulled: int,
     *     total: string,
     *     exempt: string,
     *     export: string,
     *     taxpayer_base: string,
     *     taxpayer_tax: string,
     *     non_taxpayer_base: string,
     *     non_taxpayer_tax: string,
     *     general_base: string,
     *     general_tax: string,
     *     reduced_base: string,
     *     reduced_tax: string,
     *     luxury_base: string,
     *     luxury_tax: string,
     *     other_base: string,
     *     other_tax: string,
     *     taxable_base: string,
     *     tax: string,
     *     withholding: string,
     *     determination_base: string
     * }
     */
    public static function totals(FiscalBook $book, CarbonImmutable $period): array
    {
        return self::totalsFor(self::query($book, $period));
    }

    /**
     * @param  Builder<FiscalBookEntry>  $query
     * @return array<string, int|string>
     */
    public static function totalsFor(Builder $query): array
    {
        $general = self::RATE_GENERAL;
        $reduced = self::RATE_REDUCED;
        $luxury = self::RATE_LUXURY;
        $taxpayer = FiscalBookEntry::TAXPAYER;
        $annulled = FiscalBookEntry::STATUS_ANNULLED;

        $row = (clone $query)
            ->reorder()
            ->toBase()
            ->selectRaw('COUNT(*) AS documents')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS annulled', [$annulled])
            ->selectRaw('SUM(total_amount) AS total')
            ->selectRaw('SUM(exempt_amount) AS exempt')
            ->selectRaw('SUM(export_amount) AS export')
            ->selectRaw('SUM(CASE WHEN taxpayer_type = ? THEN taxable_base ELSE 0 END) AS taxpayer_base', [$taxpayer])
            ->selectRaw('SUM(CASE WHEN taxpayer_type = ? THEN tax_amount ELSE 0 END) AS taxpayer_tax', [$taxpayer])
            ->selectRaw('SUM(CASE WHEN taxpayer_type <> ? THEN taxable_base ELSE 0 END) AS non_taxpayer_base', [$taxpayer])
            ->selectRaw('SUM(CASE WHEN taxpayer_type <> ? THEN tax_amount ELSE 0 END) AS non_taxpayer_tax', [$taxpayer])
            ->selectRaw('SUM(CASE WHEN tax_rate = ? THEN taxable_base ELSE 0 END) AS general_base', [$general])
            ->selectRaw('SUM(CASE WHEN tax_rate = ? THEN tax_amount ELSE 0 END) AS general_tax', [$general])
            ->selectRaw('SUM(CASE WHEN tax_rate = ? THEN taxable_base ELSE 0 END) AS reduced_base', [$reduced])
            ->selectRaw('SUM(CASE WHEN tax_rate = ? THEN tax_amount ELSE 0 END) AS reduced_tax', [$reduced])
            ->selectRaw('SUM(CASE WHEN tax_rate = ? THEN taxable_base ELSE 0 END) AS luxury_base', [$luxury])
            ->selectRaw('SUM(CASE WHEN tax_rate = ? THEN tax_amount ELSE 0 END) AS luxury_tax', [$luxury])
            ->selectRaw('SUM(taxable_base) AS taxable_base')
            ->selectRaw('SUM(tax_amount) AS tax')
            ->selectRaw('SUM(withholding_amount) AS withholding')
            ->first();

        $value = fn (string $column): string => FiscalAmount::of($row?->{$column} ?? '0');

        $totals = [
            'documents' => (int) ($row->documents ?? 0),
            'annulled' => (int) ($row->annulled ?? 0),
            'total' => $value('total'),
            'exempt' => $value('exempt'),
            'export' => $value('export'),
            'taxpayer_base' => $value('taxpayer_base'),
            'taxpayer_tax' => $value('taxpayer_tax'),
            'non_taxpayer_base' => $value('non_taxpayer_base'),
            'non_taxpayer_tax' => $value('non_taxpayer_tax'),
            'general_base' => $value('general_base'),
            'general_tax' => $value('general_tax'),
            'reduced_base' => $value('reduced_base'),
            'reduced_tax' => $value('reduced_tax'),
            'luxury_base' => $value('luxury_base'),
            'luxury_tax' => $value('luxury_tax'),
            'taxable_base' => $value('taxable_base'),
            'tax' => $value('tax'),
            'withholding' => $value('withholding'),
        ];

        $totals['other_base'] = FiscalAmount::sub($totals['taxable_base'], FiscalAmount::add($totals['general_base'], $totals['reduced_base'], $totals['luxury_base']));
        $totals['other_tax'] = FiscalAmount::sub($totals['tax'], FiscalAmount::add($totals['general_tax'], $totals['reduced_tax'], $totals['luxury_tax']));
        $totals['determination_base'] = FiscalAmount::add($totals['exempt'], $totals['export'], $totals['taxable_base']);

        return $totals;
    }

    /**
     * Resumen de débitos (ventas) o créditos (compras) fiscales con los mismos
     * renglones del libro en Excel.
     *
     * @param  array<string, int|string>  $totals
     * @return list<array{item: string, label: string, base: string, tax: string|null, withholding: string|null, strong: bool}>
     */
    public static function summaryLines(FiscalBook $book, array $totals): array
    {
        $operations = mb_strtolower($book->operationsLabel());

        $lines = [
            ['item' => '40', 'label' => 'Total '.$operations.' no gravadas y/o sin derecho a crédito', 'base' => $totals['exempt'], 'tax' => null, 'withholding' => null, 'strong' => false],
            ['item' => '41', 'label' => $book === FiscalBook::Sales ? 'Total ventas de exportación' : 'Total importaciones gravadas', 'base' => $totals['export'], 'tax' => null, 'withholding' => null, 'strong' => false],
            ['item' => '42 / 43', 'label' => 'Total '.$operations.' internas gravadas por alícuota general 16 %', 'base' => $totals['general_base'], 'tax' => $totals['general_tax'], 'withholding' => null, 'strong' => false],
            ['item' => '442 / 452', 'label' => 'Total '.$operations.' internas gravadas por alícuota reducida 8 %', 'base' => $totals['reduced_base'], 'tax' => $totals['reduced_tax'], 'withholding' => null, 'strong' => false],
            ['item' => '443 / 453', 'label' => 'Total '.$operations.' internas gravadas por alícuota general más adicional', 'base' => $totals['luxury_base'], 'tax' => $totals['luxury_tax'], 'withholding' => null, 'strong' => false],
        ];

        if (! FiscalAmount::isZero($totals['other_base']) || ! FiscalAmount::isZero($totals['other_tax'])) {
            $lines[] = ['item' => '—', 'label' => 'Total '.$operations.' gravadas con otras alícuotas', 'base' => $totals['other_base'], 'tax' => $totals['other_tax'], 'withholding' => null, 'strong' => false];
        }

        $lines[] = [
            'item' => '46',
            'label' => 'Total '.$operations.' y '.($book === FiscalBook::Sales ? 'débitos' : 'créditos').' fiscales para efectos de determinación',
            'base' => $totals['determination_base'],
            'tax' => $totals['tax'],
            'withholding' => $totals['withholding'],
            'strong' => true,
        ];

        return $lines;
    }
}
