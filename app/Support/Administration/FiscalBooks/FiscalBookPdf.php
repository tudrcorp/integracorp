<?php

declare(strict_types=1);

namespace App\Support\Administration\FiscalBooks;

use App\Enums\FiscalBook;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;

/**
 * Libro fiscal en PDF (oficio horizontal) con «VAN / VIENEN» por página.
 *
 * Las páginas se arman en PHP con un número fijo de filas: DomPDF no puede
 * calcular arrastres por página, y así cada «VAN» es la suma exacta (bcmath) de
 * las filas impresas hasta ahí.
 */
final class FiscalBookPdf
{
    public const ROWS_PER_PAGE = 33;

    private const CARRY_COLUMNS = ['total', 'exempt', 'export', 'taxpayer_base', 'taxpayer_tax', 'non_taxpayer_base', 'non_taxpayer_tax', 'withholding'];

    public static function write(FiscalBook $book, CarbonImmutable $period, string $path): void
    {
        $report = FiscalBookReport::build($book, $period);

        Pdf::loadView('documents.fiscal-book', [
            'report' => $report,
            'pages' => self::pages($report['rows']),
        ])
            ->setPaper('legal', 'landscape')
            ->save($path);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{rows: list<array<string, mixed>>, carried: array<string, string>|null, carry: array<string, string>, last: bool}>
     */
    public static function pages(array $rows): array
    {
        $chunks = $rows === [] ? [[]] : array_chunk($rows, self::ROWS_PER_PAGE);
        $carry = array_fill_keys(self::CARRY_COLUMNS, FiscalAmount::ZERO);
        $pages = [];

        foreach ($chunks as $index => $chunk) {
            $carried = $index === 0 ? null : $carry;

            foreach ($chunk as $row) {
                foreach (self::CARRY_COLUMNS as $column) {
                    $carry[$column] = FiscalAmount::add($carry[$column], $row[$column]);
                }
            }

            $pages[] = [
                'rows' => $chunk,
                'carried' => $carried,
                'carry' => $carry,
                'last' => $index === count($chunks) - 1,
            ];
        }

        return $pages;
    }
}
