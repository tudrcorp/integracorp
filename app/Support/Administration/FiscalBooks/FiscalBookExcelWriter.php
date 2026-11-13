<?php

declare(strict_types=1);

namespace App\Support\Administration\FiscalBooks;

use App\Enums\FiscalBook;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Libro fiscal en .xlsx con el formato del libro que llevaba Administración:
 * encabezado de la empresa, columnas A–T del formato fiscal, total del mes y
 * resumen de débitos o créditos fiscales.
 *
 * Los montos se escriben como números con formato #,##0.00 desde los valores
 * exactos ya calculados; los totales no son fórmulas, así el archivo muestra
 * exactamente lo que cuadró el sistema.
 */
final class FiscalBookExcelWriter
{
    private const MONEY = '#,##0.00';

    private const LAST_COLUMN = 20;

    public static function write(FiscalBook $book, CarbonImmutable $period, string $path): void
    {
        $report = FiscalBookReport::build($book, $period);

        $options = new Options;
        $options->setColumnWidth(6, 1);
        $options->setColumnWidth(11, 2);
        $options->setColumnWidth(13, 3);
        $options->setColumnWidth(14, 4, 5);
        $options->setColumnWidth(7, 6);
        $options->setColumnWidth(42, 7);
        $options->setColumnWidth(15, 8);
        $options->setColumnWidth(10, 9);
        $options->setColumnWidth(17, 10, 11, 12, 13, 14, 15, 16);
        $options->setColumnWidth(9, 17);
        $options->setColumnWidth(13, 18);
        $options->setColumnWidth(16, 19, 20);
        $options->setColumnWidth(14, 21);

        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(mb_substr($report['month'].' '.$period->year, 0, 31));

        $title = (new Style)->setFontBold()->setFontSize(13);
        $bold = (new Style)->setFontBold();
        $header = self::header();
        $money = (new Style)->setFormat(self::MONEY);
        $moneyBold = (new Style)->setFormat(self::MONEY)->setFontBold();
        $date = (new Style)->setFormat('dd/mm/yyyy')->setCellAlignment(CellAlignment::CENTER);
        $center = (new Style)->setCellAlignment(CellAlignment::CENTER);
        $percent = (new Style)->setFormat('0%')->setCellAlignment(CellAlignment::CENTER);
        $annulled = (new Style)->setFontColor(Color::rgb(185, 28, 28))->setFontBold();

        $writer->addRow(Row::fromValues(['NOMBRE O RAZÓN SOCIAL: '.FiscalBookReport::COMPANY_NAME, '', '', '', '', '', '', 'RIF: '.FiscalBookReport::COMPANY_RIF], $bold));
        $writer->addRow(Row::fromValues([$report['title']], $title));
        $writer->addRow(Row::fromValues([$report['closed'] ? 'Mes cerrado' : 'Mes abierto · documento de trabajo']));
        $options->mergeCells(0, 2, self::LAST_COLUMN, 2);

        $groups = array_fill(0, self::LAST_COLUMN + 1, '');
        $groups[1] = 'DETALLE DE FACTURAS, NOTAS DE DÉBITO Y CRÉDITO';
        $groups[9] = 'TOTAL '.mb_strtoupper($book->operationsLabel());
        $groups[12] = 'CONTRIBUYENTES';
        $groups[14] = 'NO CONTRIBUYENTES';
        $groups[17] = 'COMPROBANTE DE RETENCIÓN';
        $writer->addRow(Row::fromValues($groups, $header));
        $options->mergeCells(1, 4, 8, 4);
        $options->mergeCells(9, 4, 11, 4);
        $options->mergeCells(12, 4, 13, 4);
        $options->mergeCells(14, 4, 15, 4);
        $options->mergeCells(17, 4, 19, 4);

        $writer->addRow(Row::fromValues([
            'N° OPER.',
            'FECHA',
            'TIPO DE DOCUMENTO',
            'N° DE FACTURA O DOCUMENTO',
            'N° DE CONTROL',
            'SERIE',
            'NOMBRE O RAZÓN SOCIAL DEL '.mb_strtoupper($book->counterpartyLabel()),
            'RIF O CÉDULA',
            'TIPO DE CONTRIBUYENTE',
            'TOTAL INCLUYENDO IVA Y EXENTAS',
            'EXENTAS, EXONERADAS O NO SUJETAS',
            $book === FiscalBook::Sales ? 'MONTO EXPORTACIÓN' : 'MONTO IMPORTACIÓN',
            'BASE IMPONIBLE',
            'IVA',
            'BASE IMPONIBLE',
            'IVA',
            'ALÍCUOTA',
            'FECHA',
            'NÚMERO',
            $book === FiscalBook::Sales ? 'MONTO RETENIDO POR EL CLIENTE' : 'MONTO RETENIDO AL PROVEEDOR',
            'DOCUMENTO AFECTADO',
        ], $header));

        foreach ($report['rows'] as $row) {
            $writer->addRow(new Row([
                Cell::fromValue($row['number'], $center),
                Cell::fromValue(self::excelDate($row['date']), $date),
                Cell::fromValue($row['type']),
                Cell::fromValue((string) $row['document_number']),
                Cell::fromValue((string) ($row['control_number'] ?? '')),
                Cell::fromValue((string) ($row['series'] ?? '')),
                Cell::fromValue((string) $row['name'], $row['annulled'] ? $annulled : null),
                Cell::fromValue((string) ($row['rif'] ?? '')),
                Cell::fromValue($row['taxpayer'], $center),
                Cell::fromValue(FiscalAmount::toFloat($row['total']), $money),
                Cell::fromValue(FiscalAmount::toFloat($row['exempt']), $money),
                Cell::fromValue(FiscalAmount::toFloat($row['export']), $money),
                Cell::fromValue(FiscalAmount::toFloat($row['taxpayer_base']), $money),
                Cell::fromValue(FiscalAmount::toFloat($row['taxpayer_tax']), $money),
                Cell::fromValue(FiscalAmount::toFloat($row['non_taxpayer_base']), $money),
                Cell::fromValue(FiscalAmount::toFloat($row['non_taxpayer_tax']), $money),
                Cell::fromValue(FiscalAmount::toFloat($row['rate']) / 100, $percent),
                Cell::fromValue(self::excelDate($row['withholding_date']), $date),
                Cell::fromValue((string) ($row['withholding_number'] ?? '')),
                Cell::fromValue(FiscalAmount::toFloat($row['withholding']), $money),
                Cell::fromValue((string) ($row['affected'] ?? '')),
            ]));
        }

        $totals = $report['totals'];
        $writer->addRow(new Row([
            ...array_map(fn (): Cell => Cell::fromValue(''), range(0, 5)),
            Cell::fromValue('TOTAL DEL MES ('.$totals['documents'].' documentos'.($totals['annulled'] > 0 ? ', '.$totals['annulled'].' anulados' : '').')', $bold),
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue(FiscalAmount::toFloat($totals['total']), $moneyBold),
            Cell::fromValue(FiscalAmount::toFloat($totals['exempt']), $moneyBold),
            Cell::fromValue(FiscalAmount::toFloat($totals['export']), $moneyBold),
            Cell::fromValue(FiscalAmount::toFloat($totals['taxpayer_base']), $moneyBold),
            Cell::fromValue(FiscalAmount::toFloat($totals['taxpayer_tax']), $moneyBold),
            Cell::fromValue(FiscalAmount::toFloat($totals['non_taxpayer_base']), $moneyBold),
            Cell::fromValue(FiscalAmount::toFloat($totals['non_taxpayer_tax']), $moneyBold),
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue(''),
            Cell::fromValue(FiscalAmount::toFloat($totals['withholding']), $moneyBold),
        ]));

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['', '', '', '', 'RESUMEN DE '.($book === FiscalBook::Sales ? 'DÉBITOS' : 'CRÉDITOS').' FISCALES · '.$report['month'].' '.$period->year], $bold));
        $writer->addRow(Row::fromValues(['', '', '', '', 'CONCEPTO', '', '', '', '', 'ÍTEM', 'BASE IMPONIBLE', $book === FiscalBook::Sales ? 'DÉBITO FISCAL' : 'CRÉDITO FISCAL', 'IVA RETENIDO'], $header));

        foreach ($report['summary'] as $line) {
            $writer->addRow(new Row([
                ...array_map(fn (): Cell => Cell::fromValue(''), range(0, 3)),
                Cell::fromValue($line['label'], $line['strong'] ? $bold : null),
                ...array_map(fn (): Cell => Cell::fromValue(''), range(5, 8)),
                Cell::fromValue('Ítem '.$line['item'], $center),
                Cell::fromValue(FiscalAmount::toFloat($line['base']), $line['strong'] ? $moneyBold : $money),
                Cell::fromValue($line['tax'] === null ? '' : FiscalAmount::toFloat($line['tax']), $line['strong'] ? $moneyBold : $money),
                Cell::fromValue($line['withholding'] === null ? '' : FiscalAmount::toFloat($line['withholding']), $line['strong'] ? $moneyBold : $money),
            ]));
        }

        $writer->close();
    }

    private static function header(): Style
    {
        $line = new BorderPart(Border::BOTTOM, Color::rgb(148, 163, 184), Border::WIDTH_THIN, Border::STYLE_SOLID);

        return (new Style)
            ->setFontBold()
            ->setFontSize(9)
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(Color::rgb(5, 47, 96))
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText()
            ->setBorder(new Border($line));
    }

    private static function excelDate(mixed $date): ?DateTimeImmutable
    {
        return $date instanceof \DateTimeInterface ? DateTimeImmutable::createFromInterface($date)->setTime(0, 0) : null;
    }
}
