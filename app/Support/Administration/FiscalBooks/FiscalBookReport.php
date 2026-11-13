<?php

declare(strict_types=1);

namespace App\Support\Administration\FiscalBooks;

use App\Enums\FiscalBook;
use App\Models\FiscalBookEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Datos de un libro fiscal listos para Excel y PDF: filas en el orden del
 * libro, totales y resumen fiscal. Una consulta de filas y una de totales.
 */
final class FiscalBookReport
{
    public const COMPANY_NAME = 'TU DOCTOR EN CASA, C.A.';

    public const COMPANY_RIF = 'J-503583681';

    /**
     * @return array{
     *     book: FiscalBook,
     *     period: CarbonImmutable,
     *     title: string,
     *     month: string,
     *     closed: bool,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, int|string>,
     *     summary: list<array{item: string, label: string, base: string, tax: string|null, withholding: string|null, strong: bool}>
     * }
     */
    public static function build(FiscalBook $book, CarbonImmutable $period): array
    {
        $period = $period->startOfMonth();
        $totals = FiscalBookLedger::totals($book, $period);
        $rows = [];

        foreach (FiscalBookLedger::entries($book, $period) as $index => $entry) {
            $rows[] = self::row($entry, $index + 1);
        }

        return [
            'book' => $book,
            'period' => $period,
            'title' => mb_strtoupper($book->label()).' CORRESPONDIENTE AL MES DE '.mb_strtoupper($period->locale('es')->translatedFormat('F')).' '.$period->year,
            'month' => mb_strtoupper($period->locale('es')->translatedFormat('F')),
            'closed' => FiscalBookPeriods::isClosed($book, $period),
            'rows' => $rows,
            'totals' => $totals,
            'summary' => FiscalBookLedger::summaryLines($book, $totals),
        ];
    }

    public static function filename(FiscalBook $book, CarbonImmutable $period, string $extension): string
    {
        return Str::slug($book->label()).'-'.$period->format('Y-m').'.'.$extension;
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(FiscalBookEntry $entry, int $number): array
    {
        $taxpayer = $entry->taxpayer_type === FiscalBookEntry::TAXPAYER;

        return [
            'number' => $number,
            'date' => $entry->document_date,
            'type' => FiscalBookEntry::documentTypeOptions()[$entry->document_type] ?? $entry->document_type,
            'document_number' => $entry->document_number,
            'control_number' => $entry->control_number,
            'series' => $entry->series,
            'affected' => $entry->affected_document_number,
            'name' => $entry->isAnnulled() ? 'ANULADA' : $entry->counterparty_name,
            'rif' => $entry->isAnnulled() ? null : $entry->counterparty_rif,
            'taxpayer' => $entry->taxpayer_type,
            'annulled' => $entry->isAnnulled(),
            'total' => FiscalAmount::of($entry->total_amount),
            'exempt' => FiscalAmount::of($entry->exempt_amount),
            'export' => FiscalAmount::of($entry->export_amount),
            'taxpayer_base' => $taxpayer ? FiscalAmount::of($entry->taxable_base) : FiscalAmount::ZERO,
            'taxpayer_tax' => $taxpayer ? FiscalAmount::of($entry->tax_amount) : FiscalAmount::ZERO,
            'non_taxpayer_base' => $taxpayer ? FiscalAmount::ZERO : FiscalAmount::of($entry->taxable_base),
            'non_taxpayer_tax' => $taxpayer ? FiscalAmount::ZERO : FiscalAmount::of($entry->tax_amount),
            'rate' => FiscalAmount::of($entry->tax_rate),
            'withholding_date' => $entry->withholding_date,
            'withholding_number' => $entry->withholding_number,
            'withholding' => FiscalAmount::of($entry->withholding_amount),
        ];
    }
}
