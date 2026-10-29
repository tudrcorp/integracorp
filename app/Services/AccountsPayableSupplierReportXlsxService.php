<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OperationAccountsPayable;
use App\Support\Operations\AccountsPayableSupplierReport;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Escribe el reporte de cuentas por pagar por proveedor ({@see AccountsPayableSupplierReport})
 * como Excel: encabezado verde, fechas y montos reales (se pueden sumar y filtrar) y
 * subtotales con SUBTOTAL(9, …), igual que el formato que arma Administración a mano.
 *
 * @phpstan-import-type ReportRow from AccountsPayableSupplierReport
 * @phpstan-import-type ReportSheet from AccountsPayableSupplierReport
 */
final class AccountsPayableSupplierReportXlsxService
{
    private const HEADER_GREEN = '70AD47';

    private const HEADER_STRONG_GREEN = '00B050';

    private const USD_FORMAT = '[$$-45C]#,##0.00';

    private const VES_FORMAT = '"Bs." #,##0.00';

    private const DATE_FORMAT = 'dd/mm/yyyy';

    /**
     * Columnas en el orden del reporte: título, ancho y si el encabezado va en el verde fuerte.
     *
     * @var list<array{title: string, width: float, strong: bool}>
     */
    private const COLUMNS = [
        ['title' => 'Fecha factura', 'width' => 12, 'strong' => false],
        ['title' => 'Fecha de registro', 'width' => 12, 'strong' => false],
        ['title' => 'Proveedor', 'width' => 38, 'strong' => false],
        ['title' => 'RIF', 'width' => 13, 'strong' => false],
        ['title' => 'Unidad Especifica de negocio', 'width' => 11, 'strong' => false],
        ['title' => 'Tipo de servicio', 'width' => 16, 'strong' => true],
        ['title' => 'Servicios Especificos', 'width' => 41, 'strong' => true],
        ['title' => 'Orden de servicio', 'width' => 12, 'strong' => true],
        ['title' => 'N.º factura', 'width' => 12, 'strong' => false],
        ['title' => 'N.º control', 'width' => 13, 'strong' => false],
        ['title' => 'Monto factura', 'width' => 14, 'strong' => false],
        ['title' => 'Estatus de pago', 'width' => 25, 'strong' => false],
    ];

    /** Columnas (base 0) que se llenan en las filas de total. */
    private const SUPPLIER_COLUMN = 2;

    private const AMOUNT_COLUMN = 10;

    private const LABEL_COLUMN = 11;

    private const AMOUNT_LETTER = 'K';

    /**
     * Valor de cada fórmula de total, por hoja (base 1), para guardarlo junto a la fórmula.
     *
     * @var array<int, array<string, float>>
     */
    private static array $formulaValues = [];

    /**
     * @param  array<int|string>  $ids
     * @return array{filename: string, contents: string, rows: int, sheets: int}
     */
    public static function build(array $ids, ?DateTimeInterface $generatedAt = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            throw new RuntimeException('Selecciona al menos una factura para exportar.');
        }

        $rows = [];

        foreach (array_chunk($ids, 1000) as $chunk) {
            OperationAccountsPayable::query()
                ->select([
                    'id', 'operation_service_order_id', 'supplier_id', 'supplier_name', 'supplier_rif',
                    'invoice_date', 'invoice_registration_date', 'invoice_number', 'invoice_control_number',
                    'invoice_amount', 'invoice_currency', 'payment_status',
                ])
                ->with(AccountsPayableSupplierReport::eagerLoads())
                ->whereKey($chunk)
                ->lazyById(500)
                ->each(function (OperationAccountsPayable $record) use (&$rows): void {
                    $rows[] = AccountsPayableSupplierReport::rowFromRecord($record);
                });
        }

        if ($rows === []) {
            throw new RuntimeException('Las facturas seleccionadas ya no existen. Actualiza la tabla e inténtalo de nuevo.');
        }

        $sheets = AccountsPayableSupplierReport::sheets($rows);

        return [
            'filename' => AccountsPayableSupplierReport::filename($rows, $generatedAt ?? CarbonImmutable::now()),
            'contents' => self::write($sheets),
            'rows' => count($rows),
            'sheets' => count($sheets),
        ];
    }

    /**
     * Se escribe a un temporal y se devuelve el contenido: si algo falla a la mitad, no queda
     * ningún archivo a medias ni se entrega un Excel corrupto.
     *
     * @param  list<ReportSheet>  $sheets
     */
    public static function write(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cxp-proveedor-');

        if ($path === false) {
            throw new RuntimeException('No se pudo preparar el archivo del reporte.');
        }

        try {
            $options = new Options;
            $options->DEFAULT_ROW_STYLE->setFontSize(11)->setFontName('Calibri');

            self::$formulaValues = [];
            $writer = new Writer($options);
            $writer->openToFile($path);

            foreach ($sheets as $index => $sheet) {
                self::writeSheet($writer, $sheet, $index === 0, $index + 1);
            }

            $writer->close();
            self::storeFormulaValues($path);

            $contents = file_get_contents($path);

            if ($contents === false || $contents === '') {
                throw new RuntimeException('No se pudo leer el reporte generado.');
            }

            return $contents;
        } catch (Throwable $exception) {
            if (isset($writer)) {
                try {
                    $writer->close();
                } catch (Throwable) {
                }
            }

            throw $exception;
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * OpenSpout escribe la fórmula sin su resultado: Excel la calcula al abrir, pero las vistas
     * previas (Quick Look, WhatsApp, el teléfono) mostrarían el total vacío. Se guarda el valor
     * junto a la fórmula, que sigue recalculándose si alguien edita el archivo.
     */
    private static function storeFormulaValues(string $path): void
    {
        if (self::$formulaValues === []) {
            return;
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('No se pudo completar los totales del reporte.');
        }

        try {
            foreach (self::$formulaValues as $sheetNumber => $values) {
                $entry = 'xl/worksheets/sheet'.$sheetNumber.'.xml';
                $xml = $zip->getFromName($entry);

                if ($xml === false) {
                    throw new RuntimeException('No se pudo completar los totales del reporte.');
                }

                foreach ($values as $formula => $value) {
                    $xml = str_replace(
                        '<f>'.$formula.'</f></c>',
                        '<f>'.$formula.'</f><v>'.number_format($value, 2, '.', '').'</v></c>',
                        $xml,
                    );
                }

                if (! $zip->addFromString($entry, $xml)) {
                    throw new RuntimeException('No se pudo completar los totales del reporte.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * @param  ReportSheet  $sheet
     */
    private static function writeSheet(Writer $writer, array $sheet, bool $isFirst, int $sheetNumber): void
    {
        $current = $isFirst ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
        $current->setName($sheet['name']);
        $current->setSheetView((new SheetView)->setShowGridLines(false)->setFreezeRow(2)->setTabSelected($isFirst));

        foreach (self::COLUMNS as $index => $column) {
            $current->setColumnWidth($column['width'], $index + 1);
        }

        $headerRow = new Row(array_map(
            fn (array $column): Cell => new StringCell($column['title'], self::headerStyle($column['strong'])),
            self::COLUMNS,
        ));
        $headerRow->setHeight(52.5);
        $writer->addRow($headerRow);

        $currencies = [];

        foreach ($sheet['blocks'] as $block) {
            foreach ($block['rows'] as $row) {
                $currencies[$row['currency']] = true;
            }
        }

        $singleCurrency = count($currencies) === 1 ? array_key_first($currencies) : null;
        $rowNumber = 1;
        $allRows = [];

        foreach ($sheet['blocks'] as $block) {
            $firstRow = $rowNumber + 1;

            foreach ($block['rows'] as $row) {
                $writer->addRow(self::dataRow($row));
                $rowNumber++;
                $allRows[] = $row;
            }

            $rowNumber += self::addTotals($writer, $sheetNumber, $sheet['supplier'], $block['total_label'], $block['rows'], $singleCurrency, $firstRow, $rowNumber, true);
        }

        self::addTotals($writer, $sheetNumber, $sheet['supplier'], 'Total general', $allRows, $singleCurrency, 2, $rowNumber, false);
    }

    /**
     * Con una sola moneda en la hoja el total es una fórmula SUBTOTAL (se recalcula si se edita
     * el Excel). Con US$ y Bs. mezclados no se pueden sumar: un total por moneda, ya calculado.
     *
     * @param  list<ReportRow>  $rows
     * @return int filas escritas
     */
    private static function addTotals(Writer $writer, int $sheetNumber, string $supplier, string $label, array $rows, ?string $singleCurrency, int $firstRow, int $lastRow, bool $bordered): int
    {
        if ($singleCurrency !== null) {
            $sum = round(array_sum(array_column($rows, 'amount')), 2);
            $formula = '=SUBTOTAL(9,'.self::AMOUNT_LETTER.$firstRow.':'.self::AMOUNT_LETTER.$lastRow.')';
            self::$formulaValues[$sheetNumber][substr($formula, 1)] = $sum;
            $writer->addRow(self::totalRow($supplier, $label, new FormulaCell($formula, self::totalAmountStyle($singleCurrency, $bordered), $sum), $bordered));

            return 1;
        }

        $written = 0;

        foreach (['USD' => 'US$', 'VES' => 'Bs.'] as $currency => $symbol) {
            $currencyRows = array_filter($rows, fn (array $row): bool => $row['currency'] === $currency);

            if ($currencyRows === []) {
                continue;
            }

            $sum = round(array_sum(array_column($currencyRows, 'amount')), 2);
            $writer->addRow(self::totalRow($supplier, $label.' ('.$symbol.')', new NumericCell($sum, self::totalAmountStyle($currency, $bordered)), $bordered));
            $written++;
        }

        return $written;
    }

    private static function totalRow(string $supplier, string $label, Cell $amount, bool $bordered): Row
    {
        $text = self::totalTextStyle($bordered);
        $cells = [];

        foreach (array_keys(self::COLUMNS) as $index) {
            $cells[] = match ($index) {
                self::SUPPLIER_COLUMN => new StringCell($supplier, $text),
                self::AMOUNT_COLUMN => $amount,
                self::LABEL_COLUMN => new StringCell($label, $text),
                default => new StringCell('', $bordered ? self::bodyStyle() : null),
            };
        }

        return new Row($cells);
    }

    /**
     * @param  ReportRow  $row
     */
    private static function dataRow(array $row): Row
    {
        return new Row([
            self::dateCell($row['invoice_date']),
            self::dateCell($row['registration_date']),
            new StringCell($row['supplier'], self::bodyStyle()),
            new StringCell($row['rif'], self::bodyStyle()),
            new StringCell($row['specific_business_unit'], self::bodyStyle()),
            new StringCell($row['service_type'], self::bodyStyle()),
            new StringCell($row['services'], self::bodyStyle()),
            new StringCell($row['order_number'] ?? AccountsPayableSupplierReport::MANUAL_ORDER, self::bodyStyle()),
            new StringCell($row['invoice_number'], self::bodyStyle(CellAlignment::RIGHT)),
            new StringCell($row['control_number'], self::bodyStyle()),
            new NumericCell($row['amount'], self::bodyStyle(CellAlignment::RIGHT)->setFormat(self::moneyFormat($row['currency']))),
            new StringCell(AccountsPayableSupplierReport::statusLabel($row['status']), self::bodyStyle()),
        ]);
    }

    private static function dateCell(?CarbonImmutable $date): Cell
    {
        return $date !== null
            ? new DateTimeCell($date, self::bodyStyle()->setFormat(self::DATE_FORMAT))
            : new StringCell(AccountsPayableSupplierReport::EMPTY, self::bodyStyle());
    }

    private static function headerStyle(bool $strong): Style
    {
        return (new Style)
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor($strong ? self::HEADER_STRONG_GREEN : self::HEADER_GREEN)
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText()
            ->setBorder(self::border());
    }

    private static function bodyStyle(?string $alignment = null): Style
    {
        $style = (new Style)->setBorder(self::border());

        return $alignment !== null ? $style->setCellAlignment($alignment) : $style;
    }

    private static function totalTextStyle(bool $bordered): Style
    {
        $style = (new Style)->setFontBold();

        return $bordered ? $style->setBorder(self::border()) : $style;
    }

    private static function totalAmountStyle(string $currency, bool $bordered): Style
    {
        return self::totalTextStyle($bordered)
            ->setCellAlignment(CellAlignment::RIGHT)
            ->setFormat(self::moneyFormat($currency));
    }

    private static function moneyFormat(string $currency): string
    {
        return $currency === 'VES' ? self::VES_FORMAT : self::USD_FORMAT;
    }

    private static function border(): Border
    {
        return new Border(
            new BorderPart(Border::LEFT, Color::BLACK, Border::WIDTH_THIN),
            new BorderPart(Border::RIGHT, Color::BLACK, Border::WIDTH_THIN),
            new BorderPart(Border::TOP, Color::BLACK, Border::WIDTH_THIN),
            new BorderPart(Border::BOTTOM, Color::BLACK, Border::WIDTH_THIN),
        );
    }
}
