<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Enums\CollectionFollowUpStatus;
use App\Models\Collection;
use App\Models\CollectionObservation;
use App\Support\Collections\CollectionDueDate;
use App\Support\Collections\CollectionReceivableReport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection as SupportCollection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Excel del reporte de cuentas por cobrar de «Cobranza Por Mes», con el formato del modelo
 * «GUSTAVO COBRANZA DEL MES.xlsx» (03/11/2026): las columnas del CSV sin «Periodos de pago»,
 * tres columnas de seguimiento con ✓, la última observación y una fila de totales.
 *
 * Montos y fechas van como números y fechas reales de Excel (el CSV hacía que un Excel en
 * español leyera `904.00` como 90400). Se arma directo, sin cola: recorre la consulta de la
 * tabla por lotes y precarga las observaciones de cada lote en una sola consulta.
 */
final class CollectionReceivableXlsxExportService
{
    private const int CHUNK_SIZE = 500;

    private const string MONEY_FORMAT = '#,##0.00';

    private const string DATE_FORMAT = 'dd/mm/yyyy';

    private const string HEADER_BACKGROUND = '052F60';

    private const string CHECK_COLOR = '16A34A';

    /** Primera columna de seguimiento (T, base 0). */
    private const int FOLLOW_UP_FIRST_COLUMN = 19;

    /**
     * Encabezados de la fila 1. Las tres columnas de seguimiento comparten «SEGUIMIENTO»
     * (celdas combinadas) y su detalle va en la fila 2.
     *
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'FECHA DE EMISION',
            'AFILIADO O TITULAR',
            'C.I./R.I.F.',
            'TOMADOR',
            'C.I./R.I.F. TOMADOR',
            'TELEFONO',
            'EMAIL',
            'PLAN',
            'POBLACION',
            'AGENCIA',
            'AGENTE',
            'TARIFA ANUAL (US$)',
            'MONTO SEGUN FRECUENCIA (US$)',
            'FECHA DE VIGENCIA',
            'FRACCIONAMIENTO DE CUOTAS PARA PAGO',
            'FECHA DE VENCIMIENTO',
            'ESTATUS DE COBRO',
            'DIAS',
            'ESTATUS DEL AFILIADO O TITULAR',
            'SEGUIMIENTO',
            'SEGUIMIENTO',
            'SEGUIMIENTO',
            'OBSERVACIONES',
        ];
    }

    /**
     * Columnas de seguimiento en el orden del modelo: T, U, V.
     *
     * @return list<CollectionFollowUpStatus>
     */
    public static function followUpColumns(): array
    {
        return [
            CollectionFollowUpStatus::AvisoEnviado,
            CollectionFollowUpStatus::AvisoRemitido,
            CollectionFollowUpStatus::AvisoEnRevision,
        ];
    }

    /**
     * Anchos del modelo, columna por columna.
     *
     * @return list<float>
     */
    private static function columnWidths(): array
    {
        return [14, 40, 14, 40, 20, 18, 34, 22, 12, 44, 34, 18, 20, 14, 20, 14, 16, 8, 18, 16, 16, 18, 50];
    }

    /**
     * @param  Builder<Collection>  $query  Consulta filtrada y ordenada de la tabla.
     */
    public function download(Builder $query): BinaryFileResponse
    {
        $path = $this->writeToFile($query);

        return response()
            ->download($path, 'reporte_cxc_'.now()->format('Y-m-d_His').'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'X-Content-Type-Options' => 'nosniff',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * Escribe el libro en un archivo temporal y devuelve su ruta. Si algo falla, borra el
     * archivo a medias antes de propagar el error.
     *
     * @param  Builder<Collection>  $query
     */
    public function writeToFile(Builder $query): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cxc_');

        if ($path === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal del reporte.');
        }

        $writer = new Writer($this->options());

        try {
            $writer->openToFile($path);
            $writer->getCurrentSheet()->setName('Cobranza por mes');
            $writer->getCurrentSheet()->setSheetView((new SheetView)->setFreezeRow(3));

            $this->writeHeader($writer);

            $totals = ['rows' => 0, 'population' => 0, 'annual_fee' => 0.0, 'amount_by_frequency' => 0.0];
            $bodyStyles = $this->bodyStyles();

            (clone $query)
                ->with(CollectionReceivableReport::eagerLoads())
                ->lazy(self::CHUNK_SIZE)
                ->chunk(self::CHUNK_SIZE)
                ->each(function ($records) use ($writer, &$totals, $bodyStyles): void {
                    $records = SupportCollection::make($records->all());
                    $observations = self::latestObservations($records);

                    foreach ($records as $record) {
                        $values = self::rowValues($record, $observations->get((string) $record->affiliation_code));

                        $totals['rows']++;
                        $totals['population'] += (int) ($values['population'] ?? 0);
                        $totals['annual_fee'] += (float) ($values['annual_fee'] ?? 0);
                        $totals['amount_by_frequency'] += (float) ($values['amount_by_frequency'] ?? 0);

                        $writer->addRow($this->bodyRow($values, $bodyStyles));
                    }
                });

            $writer->addRow($this->totalsRow($totals));
            $writer->close();
        } catch (\Throwable $exception) {
            @unlink($path);

            throw $exception;
        }

        return $path;
    }

    /**
     * Valores de una fila, ya interpretados (montos como float, fechas como fecha).
     *
     * @return array{
     *     emission_date: ?CarbonImmutable, holder_name: string, holder_document: string,
     *     payer_name: string, payer_document: string, phone: string, email: string,
     *     plan: string, population: ?int, agency: string, agent: string,
     *     annual_fee: ?float, amount_by_frequency: ?float, effective_date: ?CarbonImmutable,
     *     frequency: string, due_date: ?CarbonImmutable, collection_status: string,
     *     days: ?int, affiliate_status: string, follow_up: list<bool>, observation: string
     * }
     */
    public static function rowValues(Collection $record, ?CollectionObservation $latestObservation): array
    {
        $states = CollectionFollowUpStatus::decode($record->follow_up_status ?? null);
        $population = $record->persons;

        return [
            'emission_date' => CollectionDueDate::parse(CollectionReceivableReport::emissionDate($record)),
            'holder_name' => trim((string) ($record->affiliate_full_name ?? '')),
            'holder_document' => CollectionReceivableReport::holderDocumentWithPrefix($record),
            'payer_name' => trim((string) (CollectionReceivableReport::payerName($record) ?? '')),
            'payer_document' => CollectionReceivableReport::payerDocumentWithPrefix($record),
            'phone' => (string) ($record->affiliate_phone ?? ''),
            'email' => (string) ($record->affiliate_email ?? ''),
            'plan' => (string) (CollectionReceivableReport::planLabel($record) ?? ''),
            'population' => is_numeric($population) ? (int) $population : null,
            'agency' => (string) (CollectionReceivableReport::agencyLabel($record) ?? ''),
            'agent' => trim((string) ($record->agent?->name ?? '')),
            'annual_fee' => CollectionReceivableReport::annualFee($record),
            'amount_by_frequency' => CollectionReceivableReport::amountByFrequency($record),
            'effective_date' => CollectionDueDate::parse(CollectionReceivableReport::effectiveDate($record)),
            'frequency' => (string) (CollectionReceivableReport::paymentFrequency($record) ?? ''),
            'due_date' => CollectionReceivableReport::dueDate($record),
            'collection_status' => CollectionReceivableReport::collectionStatus($record),
            'days' => CollectionReceivableReport::daysCount($record),
            'affiliate_status' => (string) (CollectionReceivableReport::affiliateStatus($record) ?? ''),
            'follow_up' => array_map(
                fn (CollectionFollowUpStatus $state): bool => in_array($state, $states, true),
                self::followUpColumns(),
            ),
            'observation' => self::observationText($latestObservation),
        ];
    }

    /**
     * Última observación de cada afiliación del lote, en una sola consulta.
     *
     * @param  SupportCollection<int, Collection>  $records
     * @return SupportCollection<string, CollectionObservation>
     */
    public static function latestObservations(SupportCollection $records): SupportCollection
    {
        $codes = $records
            ->map(fn (Collection $record): string => trim((string) $record->affiliation_code))
            ->filter()
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            return collect();
        }

        return CollectionObservation::query()
            ->whereIn('id', CollectionObservation::query()
                ->selectRaw('MAX(id)')
                ->whereIn('affiliation_code', $codes->all())
                ->groupBy('affiliation_code'))
            ->get(['id', 'affiliation_code', 'observation', 'created_at', 'created_by_name'])
            ->keyBy(fn (CollectionObservation $observation): string => (string) $observation->affiliation_code);
    }

    private static function observationText(?CollectionObservation $observation): string
    {
        if (! $observation instanceof CollectionObservation) {
            return '';
        }

        $text = trim((string) $observation->observation);

        if ($text === '') {
            return '';
        }

        $date = $observation->created_at?->format('d/m/Y');

        return $date !== null ? "{$date}: {$text}" : $text;
    }

    private function options(): Options
    {
        $options = new Options;

        foreach (self::columnWidths() as $index => $width) {
            $options->setColumnWidth($width, $index + 1);
        }

        // Fila 1 + 2 combinadas en cada columna, salvo las de seguimiento, que comparten título.
        foreach (array_keys(self::headers()) as $column) {
            if ($column >= self::FOLLOW_UP_FIRST_COLUMN && $column < self::FOLLOW_UP_FIRST_COLUMN + 3) {
                continue;
            }

            $options->mergeCells($column, 1, $column, 2);
        }

        $options->mergeCells(self::FOLLOW_UP_FIRST_COLUMN, 1, self::FOLLOW_UP_FIRST_COLUMN + 2, 1);

        return $options;
    }

    private function writeHeader(Writer $writer): void
    {
        $border = new Border(
            new BorderPart(Border::BOTTOM, 'FFFFFF', Border::WIDTH_THIN),
            new BorderPart(Border::RIGHT, 'FFFFFF', Border::WIDTH_THIN),
        );

        $style = (new Style)
            ->setFontBold()
            ->setFontSize(10)
            ->setFontColor('FFFFFF')
            ->setBackgroundColor(self::HEADER_BACKGROUND)
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText()
            ->setBorder($border);

        $writer->addRow(Row::fromValues(self::headers(), $style)->setHeight(30));

        $subtitles = array_fill(0, count(self::headers()), '');

        foreach (self::followUpColumns() as $offset => $state) {
            $subtitles[self::FOLLOW_UP_FIRST_COLUMN + $offset] = $state->value;
        }

        $writer->addRow(Row::fromValues($subtitles, $style)->setHeight(30));
    }

    /**
     * @return array{text: Style, money: Style, date: Style, number: Style, check: Style}
     */
    private function bodyStyles(): array
    {
        return [
            'text' => (new Style)->setFontSize(10),
            'money' => (new Style)->setFontSize(10)->setFormat(self::MONEY_FORMAT),
            'date' => (new Style)->setFontSize(10)->setFormat(self::DATE_FORMAT)->setCellAlignment(CellAlignment::CENTER),
            'number' => (new Style)->setFontSize(10)->setCellAlignment(CellAlignment::CENTER),
            'check' => (new Style)->setFontSize(12)->setFontBold()->setFontColor(self::CHECK_COLOR)->setCellAlignment(CellAlignment::CENTER),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array{text: Style, money: Style, date: Style, number: Style, check: Style}  $styles
     */
    private function bodyRow(array $values, array $styles): Row
    {
        $cells = [
            self::dateCell($values['emission_date'], $styles['date']),
            Cell::fromValue($values['holder_name'], $styles['text']),
            Cell::fromValue($values['holder_document'], $styles['text']),
            Cell::fromValue($values['payer_name'], $styles['text']),
            Cell::fromValue($values['payer_document'], $styles['text']),
            Cell::fromValue($values['phone'], $styles['text']),
            Cell::fromValue($values['email'], $styles['text']),
            Cell::fromValue($values['plan'], $styles['text']),
            Cell::fromValue($values['population'], $styles['number']),
            Cell::fromValue($values['agency'], $styles['text']),
            Cell::fromValue($values['agent'], $styles['text']),
            Cell::fromValue($values['annual_fee'], $styles['money']),
            Cell::fromValue($values['amount_by_frequency'], $styles['money']),
            self::dateCell($values['effective_date'], $styles['date']),
            Cell::fromValue($values['frequency'], $styles['text']),
            self::dateCell($values['due_date'], $styles['date']),
            Cell::fromValue($values['collection_status'], $styles['text']),
            Cell::fromValue($values['days'], $styles['number']),
            Cell::fromValue($values['affiliate_status'], $styles['text']),
        ];

        foreach ($values['follow_up'] as $marked) {
            $cells[] = Cell::fromValue($marked ? '✓' : '', $styles['check']);
        }

        $cells[] = Cell::fromValue($values['observation'], $styles['text']);

        return new Row($cells);
    }

    /**
     * @param  array{rows: int, population: int, annual_fee: float, amount_by_frequency: float}  $totals
     */
    private function totalsRow(array $totals): Row
    {
        $base = (new Style)
            ->setFontBold()
            ->setFontSize(10)
            ->setBackgroundColor('E2E8F0')
            ->setBorder(new Border(new BorderPart(Border::TOP, '052F60', Border::WIDTH_MEDIUM)));
        $money = (clone $base)->setFormat(self::MONEY_FORMAT);
        $number = (clone $base)->setCellAlignment(CellAlignment::CENTER);

        $cells = array_map(fn (): Cell => Cell::fromValue('', $base), self::headers());
        $cells[0] = Cell::fromValue('TOTAL', $base);
        $cells[1] = Cell::fromValue($totals['rows'].' '.($totals['rows'] === 1 ? 'registro' : 'registros'), $base);
        $cells[8] = Cell::fromValue($totals['population'], $number);
        $cells[11] = Cell::fromValue(round($totals['annual_fee'], 2), $money);
        $cells[12] = Cell::fromValue(round($totals['amount_by_frequency'], 2), $money);

        return new Row($cells);
    }

    private static function dateCell(?CarbonImmutable $date, Style $style): Cell
    {
        return $date !== null
            ? Cell::fromValue($date->toDateTimeImmutable(), $style)
            : Cell::fromValue('', $style);
    }
}
