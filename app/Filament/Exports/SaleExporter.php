<?php

namespace App\Filament\Exports;

use App\Models\Sale;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;


class SaleExporter extends Exporter
{
    protected static ?string $model = Sale::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('date_activation')
                ->label('FECHA DE ACTIVACION'),
            ExportColumn::make('code_agency')
                ->label('AGENCIA'),
            ExportColumn::make('agent_id')
                ->label('AGENTE'),
            ExportColumn::make('invoice_number')
                ->label('NRO. VENTA'),
            ExportColumn::make('affiliation_code')
                ->label('AFILIACION'),
            ExportColumn::make('affiliate_full_name')
                ->label('AFILIADO'),
            ExportColumn::make('affiliate_ci_rif')
                ->label('C.I. / RIF'),
            ExportColumn::make('affiliate_phone')
                ->label('TELEFONO'),
            ExportColumn::make('affiliate_email')
                ->label('CORREO'),
            ExportColumn::make('plan_id')
                ->label('PLAN'),
            ExportColumn::make('coverage_id')
                ->label('COBERTURA'),
            ExportColumn::make('service')
                ->label('SERVICIO'),
            ExportColumn::make('persons')
                ->label('PERSONAS'),
            ExportColumn::make('total_amount')
                ->label('MONTO'),
            ExportColumn::make('type')
                ->label('TIPO'),
            ExportColumn::make('payment_method')
                ->label('METODO DE PAGO'),
            ExportColumn::make('payment_frequency')
                ->label('FRECUENCIA DE PAGO'),
            ExportColumn::make('status_payment_commission')
                ->label('ESTADO DE PAGO'),
            ExportColumn::make('pay_amount_usd')
                ->label('MONTO USD'),
            ExportColumn::make('pay_amount_ves')
                ->label('MONTO VES'),
            ExportColumn::make('bank_usd')
                ->label('BANCO USD'),
            ExportColumn::make('bank_ves')
                ->label('BANCO VES'),
            ExportColumn::make('payment_date')
                ->label('FECHA DE PAGO'),
            ExportColumn::make('payment_method_usd')
                ->label('METODO DE PAGO USD'),
            ExportColumn::make('payment_method_ves')
                ->label('METODO DE PAGO VES'),
            ExportColumn::make('reference_payment')
                ->label('REFERENCIA DE PAGO'),
            ExportColumn::make('observations')
                ->label('OBSERVACIONES'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your sale export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }

    public function getXlsxHeaderCellStyle(): ?Style
    {
        return (new Style())
            ->setFontBold()
            ->setFontItalic()
            ->setFontSize(8)
            ->setFontName('Helvetica')
            ->setFontColor(Color::rgb(252, 254, 253))
            ->setBackgroundColor(Color::rgb(33, 65, 56))
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
    }

    public function getXlsxCellStyle(): ?Style
    {
        return (new Style())
            ->setFontSize(8)
            ->setFontName('Helvetica')
            ->setFontColor(Color::rgb(0, 0, 0))
            ->setCellAlignment(CellAlignment::LEFT)
            ->setCellVerticalAlignment(CellVerticalAlignment::TOP);
    }
}