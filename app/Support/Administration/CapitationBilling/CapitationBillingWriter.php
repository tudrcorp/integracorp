<?php

declare(strict_types=1);

namespace App\Support\Administration\CapitationBilling;

use App\Enums\OperationReportFormat;
use App\Models\CapitationBillingClosingItem;
use App\Support\CsvExportStream;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;

/**
 * Escribe la pre-facturación de capitado con el mismo formato del modelo de Atenmedi.
 *
 * - Excel: cuatro hojas — detalle del mes, resumen por tomador con el resumen de pago,
 *   prorrateo de nuevos afiliados del mes anterior y retiros. Los montos van ya calculados
 *   (sin fórmulas): el archivo cuadra igual en cualquier programa.
 * - CSV: todas las filas en una sola tabla con la columna SECCION (MES, PRORRATEO, RETIRO).
 * - PDF: resumen por tomador y de pago, y el detalle de las tres secciones.
 */
final class CapitationBillingWriter
{
    private const BRAND_BLUE = '052F60';

    private const PDF_ROWS_PER_TABLE = 45;

    /** @var list<string> */
    public const MONTH_HEADINGS = ['FECHA DE SOLICITUD', 'TIPOSOL', 'FECHA EFECTIVA', 'NOMTOM', 'TDOC', 'NDOC', 'NOMAFIL', 'TDOC', 'NDOC', 'SEXO', 'FECNAC', 'PARENTESCO', 'TIPO DE PLAN', 'PLAN', 'TARIFA MENSUAL', 'TARIFA PRORRATA', 'OBSERVACIONES'];

    /** @var list<string> */
    public const SUMMARY_HEADINGS = ['FECHA DE SOLICITUD', 'TIPOSOL', 'FECHA EFECTIVA', 'NOMTOM', 'TDOC', 'NDOC', 'NOMAFIL', 'TDOC', 'NDOC', 'SEXO', 'FECNAC', 'PARENTESCO', 'TIPO DE PLAN', 'PLAN', 'CANTIDAD AFILIADOS POR GRUPO', 'TARIFA MENSUAL'];

    /** @var list<string> */
    public const PRORATE_HEADINGS = ['FECHA DE SOLICITUD', 'TIPOSOL', 'FECHA EFECTIVA', 'NOMTOM', 'TDOC', 'NDOC', 'NOMAFIL', 'TDOC', 'NDOC', 'SEXO', 'FECNAC', 'PARENTESCO', 'AFILIADOS', 'TARIFA PRORRATA', 'TARIFA MENSUAL', 'TARIFA DIARIA', 'FECHA DE CORTE', 'DIAS'];

    /** @var list<string> */
    public const RETIREMENT_HEADINGS = ['NOMTOM', 'TDOC', 'NDOC', 'NOMAFIL', 'TDOC', 'NDOC', 'SEXO', 'FECNAC', 'PARENTESCO', 'TIPO DE PLAN', 'PLAN', 'FECHA DE RETIRO', 'OBSERVACIONES'];

    /** @var list<string> */
    public const CSV_HEADINGS = ['SECCION', 'NOMTOM', 'TDOC TOMADOR', 'NDOC TOMADOR', 'NOMAFIL', 'TDOC AFILIADO', 'NDOC AFILIADO', 'SEXO', 'FECNAC', 'PARENTESCO', 'TIPO DE PLAN', 'PLAN', 'FECHA DE ALTA', 'TARIFA MENSUAL', 'DIAS PRORRATEO', 'MONTO', 'FECHA DE RETIRO', 'OBSERVACIONES'];

    public static function write(CapitationBillingSnapshot $snapshot, OperationReportFormat $format, string $path): void
    {
        match ($format) {
            OperationReportFormat::Csv => self::csv($snapshot, $path),
            OperationReportFormat::Excel => self::xlsx($snapshot, $path),
            OperationReportFormat::Pdf => self::pdf($snapshot, $path),
        };
    }

    /**
     * Líneas del «Resumen de pago», en el orden del modelo.
     *
     * @return list<array{label: string, amount: float, kind: string}>
     */
    public static function paymentLines(CapitationBillingSnapshot $snapshot): array
    {
        $lines = [
            ['label' => 'Facturación '.$snapshot->supplierName.' - '.$snapshot->periodLabel(), 'amount' => $snapshot->monthAmount(), 'kind' => 'line'],
            ['label' => 'Prorrateo '.$snapshot->previousPeriodLabel().' Nuevos Afiliados', 'amount' => $snapshot->prorateAmount(), 'kind' => 'line'],
        ];

        foreach ($snapshot->discounts as $discount) {
            $lines[] = ['label' => 'Descuento: '.$discount['concept'], 'amount' => -round((float) $discount['amount'], 2), 'kind' => 'discount'];
        }

        $lines[] = ['label' => 'Monto Total a Pagar', 'amount' => $snapshot->totalAmount(), 'kind' => 'total'];

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<scalar|null>
     */
    public static function monthValues(array $row, CapitationBillingSnapshot $snapshot, ?float $prorate): array
    {
        $first = $snapshot->period->format('d/m/Y');

        return [
            $first, 'Inclusión', $first,
            ...self::people($row),
            $row['capitation_plan'], $row['tdec_plan'],
            round((float) $row['monthly_rate'], 2),
            $prorate,
            $row['observation'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<scalar|null>
     */
    public static function prorateValues(array $row, CapitationBillingSnapshot $snapshot): array
    {
        $enrolled = self::formatDate($row['enrolled_on']);
        $rate = round((float) $row['monthly_rate'], 2);

        return [
            $enrolled, 'Inclusión', $enrolled,
            ...self::people($row),
            1,
            round((float) $row['amount'], 2),
            $rate,
            round($rate / CapitationBillingCalculator::DAYS_PER_MONTH, 4),
            $snapshot->period->subMonthNoOverflow()->endOfMonth()->format('d/m/Y'),
            (int) ($row['prorate_days'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<scalar|null>
     */
    public static function retirementValues(array $row): array
    {
        return [...self::people($row), $row['capitation_plan'], $row['tdec_plan'], self::formatDate($row['retired_on']), $row['observation']];
    }

    private static function csv(CapitationBillingSnapshot $snapshot, string $path): void
    {
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException('No se pudo crear el archivo CSV del reporte.');
        }

        try {
            fwrite($handle, CsvExportStream::UTF8_BOM);
            fputcsv($handle, self::CSV_HEADINGS, escape: '');

            foreach ([...$snapshot->monthRows, ...$snapshot->prorateRows, ...$snapshot->retirementRows] as $row) {
                fputcsv($handle, array_map(static fn (mixed $value): string => match (true) {
                    $value === null => '',
                    is_float($value) => number_format($value, 2, '.', ''),
                    default => (string) $value,
                }, [
                    $row['section'],
                    ...self::people($row),
                    $row['capitation_plan'],
                    $row['tdec_plan'],
                    self::formatDate($row['enrolled_on']),
                    round((float) $row['monthly_rate'], 2),
                    $row['prorate_days'],
                    round((float) $row['amount'], 2),
                    self::formatDate($row['retired_on']),
                    $row['observation'],
                ]), escape: '');
            }
        } finally {
            fclose($handle);
        }
    }

    private static function xlsx(CapitationBillingSnapshot $snapshot, string $path): void
    {
        $options = new XlsxOptions;
        $options->setColumnWidth(13, 1, 2, 3, 5, 8, 11);
        $options->setColumnWidth(34, 4, 7);
        $options->setColumnWidth(12, 6, 9, 10, 12);
        $options->setColumnWidth(16, 13, 14, 15, 16);
        $options->setColumnWidth(40, 17);

        $writer = new XlsxWriter($options);
        $writer->openToFile($path);

        $title = (new Style)->setFontBold()->setFontSize(13)->setFontColor(self::BRAND_BLUE);
        $meta = (new Style)->setFontColor('475569');
        $header = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor(self::BRAND_BLUE)->setShouldWrapText();
        $money = (new Style)->setFormat('#,##0.00');
        $subtotal = (new Style)->setFontBold()->setBackgroundColor('E2E8F0');
        $total = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor(self::BRAND_BLUE);
        $warning = (new Style)->setFontBold()->setFontColor('92400E');
        $status = $snapshot->isClosed() ? 'MES CERRADO' : 'PRELIMINAR · el mes no está cerrado';
        $prorateBySubject = [];

        foreach ($snapshot->prorateRows as $row) {
            $prorateBySubject[$row['subject_type'].':'.$row['subject_id']] = round((float) $row['amount'], 2);
        }

        $intro = function (string $heading) use ($writer, $snapshot, $title, $meta, $warning, $status): void {
            $writer->addRow(Row::fromValues(['FACTURACIÓN DE MOVIMIENTOS DE AFILIACIÓN · '.$snapshot->supplierName], $title));
            $writer->addRow(Row::fromValues(['MES:', $heading], $meta));
            $writer->addRow(Row::fromValues([$status.' · Generado el '.$snapshot->generatedAt->format('d/m/Y h:i A')], $snapshot->isClosed() ? $meta : $warning));
        };

        try {
            /** 1. Detalle del mes. */
            $writer->getCurrentSheet()->setName(self::sheetName($snapshot->periodLabel()));
            $intro($snapshot->periodLabel());
            $writer->addRow(Row::fromValues(self::MONTH_HEADINGS, $header));

            foreach ($snapshot->monthRows as $row) {
                $writer->addRow(Row::fromValuesWithStyles(
                    self::monthValues($row, $snapshot, $prorateBySubject[$row['subject_type'].':'.$row['subject_id']] ?? null),
                    null,
                    [14 => $money, 15 => $money],
                ));
            }

            $writer->addRow(Row::fromValuesWithStyles(
                ['TOTAL: '.self::affiliates(count($snapshot->monthRows)), ...array_fill(0, 13, null), $snapshot->monthAmount(), null, null],
                $total,
                [14 => $money],
            ));

            /** 2. Resumen por tomador + resumen de pago. */
            $writer->addNewSheetAndMakeItCurrent()->setName(self::sheetName('RESUMEN '.$snapshot->periodLabel()));
            $intro($snapshot->periodLabel());
            $writer->addRow(Row::fromValues(self::SUMMARY_HEADINGS, $header));

            foreach ($snapshot->groups() as $group) {
                foreach ($group['rows'] as $row) {
                    $values = self::monthValues($row, $snapshot, null);
                    $writer->addRow(Row::fromValuesWithStyles([...array_slice($values, 0, 14), 1, $values[14]], null, [15 => $money]));
                }

                $writer->addRow(Row::fromValuesWithStyles(
                    [null, null, null, 'Total '.$group['group'], ...array_fill(0, 10, null), $group['count'], $group['amount']],
                    $subtotal,
                    [15 => $money],
                ));
            }

            $writer->addRow(Row::fromValuesWithStyles(
                [null, null, null, 'Total general', ...array_fill(0, 10, null), count($snapshot->monthRows), $snapshot->monthAmount()],
                $total,
                [15 => $money],
            ));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([null, null, null, 'RESUMEN DE PAGO:'], (new Style)->setFontBold()->setFontColor(self::BRAND_BLUE)));

            foreach (self::paymentLines($snapshot) as $line) {
                $writer->addRow(Row::fromValuesWithStyles(
                    [null, null, null, $line['label'], ...array_fill(0, 11, null), $line['amount']],
                    $line['kind'] === 'total' ? $total : null,
                    [15 => $money],
                ));
            }

            if ($snapshot->bcvRate !== null) {
                $writer->addRow(Row::fromValuesWithStyles(
                    [null, null, null, 'TC BCV', ...array_fill(0, 10, null), $snapshot->bcvRate, $snapshot->totalVes()],
                    $subtotal,
                    [14 => (new Style)->setFormat('#,##0.0000'), 15 => (new Style)->setFormat('"Bs." #,##0.00')],
                ));
            }

            if ($snapshot->notes !== []) {
                $writer->addRow(Row::fromValues([]));

                foreach ($snapshot->notes as $note) {
                    $writer->addRow(Row::fromValues([null, null, null, $note], $meta));
                }
            }

            /** 3. Prorrateo de nuevos afiliados del mes anterior. */
            $writer->addNewSheetAndMakeItCurrent()->setName(self::sheetName('Prorrateo '.$snapshot->previousPeriodLabel()));
            $intro('PRORRATEO '.$snapshot->previousPeriodLabel().' · NUEVOS AFILIADOS');
            $writer->addRow(Row::fromValues(self::PRORATE_HEADINGS, $header));

            foreach ($snapshot->prorateRows as $row) {
                $writer->addRow(Row::fromValuesWithStyles(self::prorateValues($row, $snapshot), null, [13 => $money, 14 => $money, 15 => (new Style)->setFormat('#,##0.0000')]));
            }

            $writer->addRow(Row::fromValuesWithStyles(
                ['TOTAL: '.self::affiliates(count($snapshot->prorateRows)), ...array_fill(0, 11, null), count($snapshot->prorateRows), $snapshot->prorateAmount(), null, null, null, null],
                $total,
                [13 => $money],
            ));

            /** 4. Retiros. */
            $writer->addNewSheetAndMakeItCurrent()->setName('RETIROS');
            $intro($snapshot->periodLabel());
            $writer->addRow(Row::fromValues(self::RETIREMENT_HEADINGS, $header));

            foreach ($snapshot->retirementRows as $row) {
                $writer->addRow(Row::fromValues(self::retirementValues($row)));
            }

            if ($snapshot->retirementRows === []) {
                $writer->addRow(Row::fromValues(['Sin retiros en el periodo.'], $meta));
            }
        } finally {
            $writer->close();
        }
    }

    private static function pdf(CapitationBillingSnapshot $snapshot, string $path): void
    {
        $prorateBySubject = [];

        foreach ($snapshot->prorateRows as $row) {
            $prorateBySubject[$row['subject_type'].':'.$row['subject_id']] = round((float) $row['amount'], 2);
        }

        $pdf = Pdf::loadView('documents.capitation-billing', [
            'snapshot' => $snapshot,
            'groups' => $snapshot->groups(),
            'paymentLines' => self::paymentLines($snapshot),
            'monthChunks' => array_chunk(array_map(fn (array $row): array => self::monthValues($row, $snapshot, $prorateBySubject[$row['subject_type'].':'.$row['subject_id']] ?? null), $snapshot->monthRows), self::PDF_ROWS_PER_TABLE),
            'prorateChunks' => array_chunk(array_map(fn (array $row): array => self::prorateValues($row, $snapshot), $snapshot->prorateRows), self::PDF_ROWS_PER_TABLE),
            'retirementChunks' => array_chunk(array_map(fn (array $row): array => self::retirementValues($row), $snapshot->retirementRows), self::PDF_ROWS_PER_TABLE),
            'logoDataUri' => self::logoDataUri(),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isFontSubsettingEnabled', true);

        if (file_put_contents($path, $pdf->output()) === false) {
            throw new RuntimeException('No se pudo guardar el PDF del reporte.');
        }
    }

    /**
     * Tomador y afiliado: NOMTOM, TDOC, NDOC, NOMAFIL, TDOC, NDOC, SEXO, FECNAC, PARENTESCO.
     *
     * @param  array<string, mixed>  $row
     * @return list<scalar|null>
     */
    private static function people(array $row): array
    {
        return [
            $row['holder_group'],
            $row['holder_document_type'],
            $row['holder_document'],
            $row['affiliate_name'],
            $row['affiliate_document_type'],
            $row['affiliate_document'],
            $row['sex'],
            self::formatDate($row['birth_date']),
            $row['relationship'],
        ];
    }

    private static function formatDate(mixed $value): ?string
    {
        return $value instanceof \DateTimeInterface ? CarbonImmutable::instance($value)->format('d/m/Y') : null;
    }

    private static function affiliates(int $count): string
    {
        return number_format($count, 0, ',', '.').' '.($count === 1 ? 'afiliado' : 'afiliados');
    }

    /**
     * Excel admite 31 caracteres en el nombre de la hoja y no admite : \ / ? * [ ].
     */
    private static function sheetName(string $name): string
    {
        return mb_substr(trim((string) preg_replace('/[:\\\\\/?*\[\]]/', ' ', $name)), 0, 31);
    }

    private static function logoDataUri(): string
    {
        $logoPath = public_path('image/logoNewPdf.png');

        return is_file($logoPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath)) : '';
    }

    /**
     * Sección legible para el analista.
     */
    public static function sectionLabel(string $section): string
    {
        return match ($section) {
            CapitationBillingClosingItem::SECTION_PRORATE => 'Prorrateo',
            CapitationBillingClosingItem::SECTION_RETIREMENT => 'Retiro',
            default => 'Mes',
        };
    }
}
