<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\AnnualCollection;
use App\Support\Collections\CollectionReceivableReport;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;

/**
 * Exporta el reporte de cuentas por cobrar de «Cobranza por mes» con las columnas y
 * el orden del «Reporte global de cuentas por cobrar» (REPORTE CXC.xlsx): una fila
 * por afiliación con su próxima cuota pendiente.
 */
class CollectionReceivableExporter extends Exporter
{
    protected static ?string $model = AnnualCollection::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return CollectionReceivableReport::scopePending($query)
            ->with(CollectionReceivableReport::eagerLoads());
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('include_date')
                ->label('FECHA DE INCLUSION O EMISION'),
            ExportColumn::make('affiliate_full_name')
                ->label('AFILIADO O TITULAR'),
            ExportColumn::make('affiliate_ci_rif')
                ->label('C.I./R.I.F.'),
            ExportColumn::make('payer_name')
                ->label('TOMADOR')
                ->state(fn (AnnualCollection $record): ?string => CollectionReceivableReport::payerName($record)),
            ExportColumn::make('payer_document')
                ->label('C.I./R.I.F. TOMADOR')
                ->state(fn (AnnualCollection $record): ?string => CollectionReceivableReport::payerDocument($record)),
            ExportColumn::make('affiliate_phone')
                ->label('TELEFONO'),
            ExportColumn::make('affiliate_email')
                ->label('EMAIL'),
            ExportColumn::make('plan_label')
                ->label('PLAN')
                ->state(fn (AnnualCollection $record): ?string => CollectionReceivableReport::planLabel($record)),
            ExportColumn::make('persons')
                ->label('POBLACION'),
            ExportColumn::make('agency_label')
                ->label('AGENCIA')
                ->state(fn (AnnualCollection $record): ?string => CollectionReceivableReport::agencyLabel($record)),
            ExportColumn::make('agent_name')
                ->label('AGENTE')
                ->state(fn (AnnualCollection $record): ?string => $record->agent?->name),
            ExportColumn::make('annual_fee')
                ->label('TARIFA ANUAL')
                ->state(fn (AnnualCollection $record): ?float => CollectionReceivableReport::annualFee($record)),
            ExportColumn::make('effective_date')
                ->label('FECHA DE VIGENCIA')
                ->state(fn (AnnualCollection $record): ?string => CollectionReceivableReport::effectiveDate($record)),
            ExportColumn::make('payment_frequency')
                ->label('FRACCIONAMIENTO DE CUOTAS PARA PAGO')
                ->state(fn (AnnualCollection $record): ?string => CollectionReceivableReport::paymentFrequency($record)),
            ExportColumn::make('installment')
                ->label('PERIODOS DE PAGO')
                ->state(fn (AnnualCollection $record): ?string => CollectionReceivableReport::installmentLabel($record)),
            ExportColumn::make('due_date')
                ->label('FECHA DE VENCIMIENTO')
                ->state(fn (AnnualCollection $record): ?string => CollectionReceivableReport::dueDate($record)?->format('d/m/Y')),
            ExportColumn::make('collection_status')
                ->label('ESTATUS DE COBRO')
                ->state(fn (AnnualCollection $record): string => CollectionReceivableReport::collectionStatus($record)),
            ExportColumn::make('days')
                ->label('DIAS')
                ->state(fn (AnnualCollection $record): ?int => CollectionReceivableReport::daysCount($record)),
            ExportColumn::make('affiliate_status_label')
                ->label('ESTATUS DEL AFILIADO O TITULAR')
                ->state(fn (AnnualCollection $record): ?string => CollectionReceivableReport::affiliateStatus($record)),
            ExportColumn::make('installment_amount')
                ->label('MONTO DE LA CUOTA')
                ->state(fn (AnnualCollection $record): ?float => CollectionReceivableReport::installmentAmount($record))
                ->enabledByDefault(false),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $rows = (int) $export->successful_rows;
        $body = 'El reporte de cuentas por cobrar está listo: '.Number::format($rows).' '.($rows === 1 ? 'afiliación exportada' : 'afiliaciones exportadas').'.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.($failedRowsCount === 1 ? 'fila no se pudo exportar' : 'filas no se pudieron exportar').'.';
        }

        return $body;
    }

    public function getXlsxHeaderCellStyle(): ?Style
    {
        return (new Style)
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(Color::rgb(5, 47, 96))
            ->setCellAlignment(CellAlignment::CENTER);
    }
}
