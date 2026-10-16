<?php

declare(strict_types=1);

namespace App\Support\Administration\Reports;

use App\Enums\OperationReportFormat;
use App\Support\CsvExportStream;
use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;

/**
 * Escribe un {@see CollectionReportData} en disco como CSV, Excel o PDF.
 *
 * - CSV: solo filas de datos, sin encabezados de grupo ni subtotales, para
 *   procesarlo en otra herramienta sin limpiarlo antes.
 * - Excel: título, criterios, encabezados de agencia/agente, subtotales y total.
 * - PDF: lo mismo que Excel con las columnas que caben en A4 horizontal.
 *
 * CSV y Excel escriben línea por línea; el PDF recibe un detalle ya acotado.
 */
final class CollectionReportWriter
{
    private const BRAND_BLUE = '052F60';

    /**
     * Filas por tabla en el PDF: DomPDF arma un mapa de celdas por tabla que
     * crece con cada fila y una sola tabla de mil filas agota la memoria.
     */
    private const PDF_ROWS_PER_TABLE = 50;

    /**
     * Encabezados largos que en la columna angosta del PDF se parten a mitad de palabra.
     *
     * @var array<int, string>
     */
    private const PDF_SHORT_HEADINGS = [
        CollectionReportBuilder::COL_FREQUENCY => 'FRECUENCIA DE PAGO',
        CollectionReportBuilder::COL_AFFILIATE_STATUS => 'ESTATUS DEL AFILIADO',
    ];

    public static function write(CollectionReportData $data, OperationReportFormat $format, string $path, ?string $notice = null): void
    {
        match ($format) {
            OperationReportFormat::Csv => self::csv($data, $path),
            OperationReportFormat::Excel => self::xlsx($data, $path, $notice),
            OperationReportFormat::Pdf => self::pdf($data, $path, $notice),
        };
    }

    private static function csv(CollectionReportData $data, string $path): void
    {
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException('No se pudo crear el archivo CSV del reporte.');
        }

        try {
            fwrite($handle, CsvExportStream::UTF8_BOM);
            fputcsv($handle, $data->headings, escape: '');

            foreach ($data->lines as $line) {
                if ($line['kind'] !== CollectionReportData::LINE_ROW) {
                    continue;
                }

                fputcsv($handle, array_map(
                    static fn (mixed $value): string => match (true) {
                        $value === null => '',
                        is_float($value) => number_format($value, 2, '.', ''),
                        default => (string) $value,
                    },
                    $line['values'],
                ), escape: '');
            }
        } finally {
            fclose($handle);
        }
    }

    private static function xlsx(CollectionReportData $data, string $path, ?string $notice): void
    {
        $options = new XlsxOptions;
        $options->setColumnWidth(14, CollectionReportBuilder::COL_INCLUDE_DATE + 1);
        $options->setColumnWidth(34, CollectionReportBuilder::COL_HOLDER + 1, CollectionReportBuilder::COL_PAYER + 1, CollectionReportBuilder::COL_AGENCY + 1, CollectionReportBuilder::COL_AGENT + 1);
        $options->setColumnWidth(26, CollectionReportBuilder::COL_EMAIL + 1, CollectionReportBuilder::COL_PLAN + 1);
        $options->setColumnWidth(16, CollectionReportBuilder::COL_ANNUAL_FEE + 1, CollectionReportBuilder::COL_INSTALLMENT_AMOUNT + 1, CollectionReportBuilder::COL_DUE_DATE + 1);

        $writer = new XlsxWriter($options);
        $writer->openToFile($path);

        $money = (new Style)->setFormat('#,##0.00');
        $moneyColumns = [
            CollectionReportBuilder::COL_ANNUAL_FEE => $money,
            CollectionReportBuilder::COL_INSTALLMENT_AMOUNT => $money,
        ];

        try {
            $writer->addRow(Row::fromValues(
                [$data->title],
                (new Style)->setFontBold()->setFontSize(14)->setFontColor(self::BRAND_BLUE),
            ));

            foreach ($data->criteria as $line) {
                $writer->addRow(Row::fromValues([$line], (new Style)->setFontColor('475569')));
            }

            $writer->addRow(Row::fromValues(['Generado el '.now()->format('d/m/Y h:i A')], (new Style)->setFontColor('475569')));

            if (filled($notice)) {
                $writer->addRow(Row::fromValues([$notice], (new Style)->setFontBold()->setFontColor('92400E')));
            }

            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(
                $data->headings,
                (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor(self::BRAND_BLUE),
            ));

            foreach ($data->lines as $line) {
                $writer->addRow(match ($line['kind']) {
                    CollectionReportData::LINE_AGENCY => Row::fromValues(
                        [$line['label'] ?? ''],
                        (new Style)->setFontBold()->setFontColor(self::BRAND_BLUE)->setBackgroundColor('D6E4F5'),
                    ),
                    CollectionReportData::LINE_AGENT => Row::fromValues(
                        [$line['label'] ?? ''],
                        (new Style)->setFontBold()->setFontItalic()->setBackgroundColor('EEF2F7'),
                    ),
                    CollectionReportData::LINE_SUBTOTAL => Row::fromValuesWithStyles(
                        self::withLabel($line),
                        (new Style)->setFontBold()->setBackgroundColor('E2E8F0'),
                        $moneyColumns,
                    ),
                    CollectionReportData::LINE_TOTAL => Row::fromValuesWithStyles(
                        self::withLabel($line),
                        (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor(self::BRAND_BLUE),
                        $moneyColumns,
                    ),
                    default => Row::fromValuesWithStyles($line['values'], null, $moneyColumns),
                });
            }
        } finally {
            $writer->close();
        }
    }

    private static function pdf(CollectionReportData $data, string $path, ?string $notice): void
    {
        $keep = $data->pdfColumns;
        $lines = [];

        foreach ($data->lines as $line) {
            $values = $line['kind'] === CollectionReportData::LINE_ROW
                || $line['kind'] === CollectionReportData::LINE_SUBTOTAL
                || $line['kind'] === CollectionReportData::LINE_TOTAL
                ? array_map(static fn (int $index): mixed => $line['values'][$index] ?? null, $keep)
                : [];

            $lines[] = ['kind' => $line['kind'], 'label' => $line['label'] ?? '', 'values' => $values];
        }

        $hasData = array_filter($lines, static fn (array $line): bool => $line['kind'] === CollectionReportData::LINE_ROW) !== [];

        $pdf = Pdf::loadView('documents.administration-collection-report', [
            'title' => $data->title,
            'headings' => array_map(static fn (int $index): string => self::PDF_SHORT_HEADINGS[$index] ?? $data->headings[$index], $keep),
            'lineChunks' => $hasData ? array_chunk($lines, self::PDF_ROWS_PER_TABLE) : [],
            'numericColumns' => array_values(array_keys(array_intersect($keep, $data->numericColumns))),
            'criteria' => $data->criteria,
            'notice' => $notice,
            'generatedAt' => now(),
            'logoDataUri' => self::logoDataUri(),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isFontSubsettingEnabled', true);

        if (file_put_contents($path, $pdf->output()) === false) {
            throw new RuntimeException('No se pudo guardar el PDF del reporte.');
        }
    }

    /**
     * Subtotal y total llevan su texto en la primera columna.
     *
     * @param  array{kind: string, values: list<scalar|null>, label?: string}  $line
     * @return list<scalar|null>
     */
    private static function withLabel(array $line): array
    {
        $values = $line['values'];
        $values[0] = $line['label'] ?? '';

        return $values;
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
