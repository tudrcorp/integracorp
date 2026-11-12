<?php

declare(strict_types=1);

namespace App\Support\Operations\Reports;

use App\Enums\OperationReportFormat;
use App\Support\CsvExportStream;
use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;

/**
 * Escribe un {@see OperationReportData} en disco como CSV, Excel o PDF.
 *
 * CSV y Excel escriben fila por fila, así que el detalle grande no se carga en
 * memoria. El PDF sólo recibe resúmenes o un detalle ya acotado.
 */
final class OperationReportWriter
{
    public static function write(OperationReportData $data, OperationReportFormat $format, string $path, ?string $notice = null): void
    {
        match ($format) {
            OperationReportFormat::Csv => self::csv($data, $path),
            OperationReportFormat::Excel => self::xlsx($data, $path),
            OperationReportFormat::Pdf => self::pdf($data, $path, $notice),
        };
    }

    private static function csv(OperationReportData $data, string $path): void
    {
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException('No se pudo crear el archivo CSV del reporte.');
        }

        try {
            fwrite($handle, CsvExportStream::UTF8_BOM);
            fputcsv($handle, $data->headings, escape: '');

            foreach ($data->rows as $row) {
                fputcsv($handle, array_map(self::cell(...), $row), escape: '');
            }

            if ($data->totals !== null) {
                fputcsv($handle, array_map(self::cell(...), $data->totals), escape: '');
            }

            foreach ($data->sections as $section) {
                fputcsv($handle, [], escape: '');
                fputcsv($handle, [$section['title']], escape: '');
                fputcsv($handle, $section['headings'], escape: '');

                foreach ($section['rows'] as $row) {
                    fputcsv($handle, array_map(self::cell(...), $row), escape: '');
                }

                if (($section['totals'] ?? null) !== null) {
                    fputcsv($handle, array_map(self::cell(...), $section['totals']), escape: '');
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private static function xlsx(OperationReportData $data, string $path): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);

        try {
            $headerStyle = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('052F60');
            $totalStyle = (new Style)->setFontBold()->setBackgroundColor('E2E8F0');
            $sectionStyle = (new Style)->setFontBold()->setFontColor('052F60');

            $writer->addRow(Row::fromValues($data->headings, $headerStyle));

            foreach ($data->rows as $row) {
                $writer->addRow(Row::fromValues(array_map(self::cell(...), $row)));
            }

            if ($data->totals !== null) {
                $writer->addRow(Row::fromValues(array_map(self::cell(...), $data->totals), $totalStyle));
            }

            foreach ($data->sections as $section) {
                $writer->addRow(Row::fromValues([]));
                $writer->addRow(Row::fromValues([$section['title']], $sectionStyle));
                $writer->addRow(Row::fromValues($section['headings'], $headerStyle));

                foreach ($section['rows'] as $row) {
                    $writer->addRow(Row::fromValues(array_map(self::cell(...), $row)));
                }

                if (($section['totals'] ?? null) !== null) {
                    $writer->addRow(Row::fromValues(array_map(self::cell(...), $section['totals']), $totalStyle));
                }
            }
        } finally {
            $writer->close();
        }
    }

    /**
     * Filas por tabla en el PDF. DomPDF arma un mapa de celdas por tabla que
     * crece con cada fila: una sola tabla de mil filas agota la memoria.
     */
    private const PDF_ROWS_PER_TABLE = 50;

    private static function pdf(OperationReportData $data, string $path, ?string $notice): void
    {
        $keep = $data->pdfColumns ?? array_keys($data->headings);
        $pick = static fn (array $row): array => array_map(
            static fn (int $index): string|int|float|null => self::cell($row[$index] ?? null),
            $keep,
        );

        $rows = [];

        foreach ($data->rows as $row) {
            $rows[] = $pick($row);
        }

        $numericColumns = array_values(array_keys(array_intersect($keep, $data->numericColumns)));

        $sections = [];

        foreach ($data->sections as $section) {
            $sectionRows = array_map(
                static fn (array $row): array => array_map(self::cell(...), $row),
                $section['rows'],
            );

            $sections[] = [
                'title' => $section['title'],
                'headings' => $section['headings'],
                'rowChunks' => array_chunk($sectionRows, self::PDF_ROWS_PER_TABLE),
                'totals' => ($section['totals'] ?? null) === null ? null : array_map(self::cell(...), $section['totals']),
                'numericColumns' => $section['numericColumns'] ?? [],
            ];
        }

        $pdf = Pdf::loadView('documents.operation-report', [
            'title' => $data->title,
            'headings' => $pick($data->headings),
            'rowChunks' => array_chunk($rows, self::PDF_ROWS_PER_TABLE),
            'totals' => $data->totals === null ? null : $pick($data->totals),
            'numericColumns' => $numericColumns,
            'criteria' => $data->criteria,
            'notice' => $notice,
            'sections' => $sections,
            'generatedAt' => now(),
            'logoDataUri' => self::logoDataUri(),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isFontSubsettingEnabled', true);

        if (file_put_contents($path, $pdf->output()) === false) {
            throw new RuntimeException('No se pudo guardar el PDF del reporte.');
        }
    }

    private static function cell(mixed $value): string|int|float|null
    {
        return match (true) {
            $value === null => null,
            is_int($value), is_float($value) => $value,
            is_bool($value) => $value ? 'SI' : 'NO',
            default => (string) $value,
        };
    }

    private static function logoDataUri(): string
    {
        $logoPath = public_path('image/logoNewPdf.png');

        if (! is_file($logoPath)) {
            return '';
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath));
    }
}
