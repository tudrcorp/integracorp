<?php

namespace App\Filament\Exports;

use App\Models\Commission;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;

class CommissionExporter extends Exporter
{
    protected static ?string $model = Commission::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('created_at')
                ->label('FECHA'),
            ExportColumn::make('code')
                ->label('Nro. Venta'),
            ExportColumn::make('agency.name_corporative')
                ->label('AGENCIA'),
            ExportColumn::make('agent.name')
                ->label('AGENTE'),
            ExportColumn::make('affiliate_full_name')
                ->label('AFILIADO'),
            ExportColumn::make('affiliation_code')
                ->label('AFILIACION'),
            ExportColumn::make('plan.description')
                ->label('PLAN'),
            ExportColumn::make('coverage.price')
                ->label('COBERTURA'),
            ExportColumn::make('amount')
                ->label('MONTO'),
            ExportColumn::make('veto')
                ->label('VETO'),
            ExportColumn::make('payment_frequency')
                ->label('FRECUENCIA DE PAGO'),
            ExportColumn::make('pay_amount_usd')
                ->label('MONTO USD'),
            ExportColumn::make('pay_amount_ves')
                ->label('MONTO VES'),
            ExportColumn::make('payment_method')
                ->label('METODO DE PAGO'),
            ExportColumn::make('porcent_agency_master')
                ->label('% AGENCIA MASTER'),
            ExportColumn::make('commission_agency_master_usd')
                ->label('COMISION USD'),
            ExportColumn::make('commission_agency_master_ves')
                ->label('COMISION VES'),
            ExportColumn::make('porcent_agency_general')
                ->label('% AGENCIA GENERAL'),
            ExportColumn::make('commission_agency_general_usd')
                ->label('COMISION USD'),
            ExportColumn::make('commission_agency_general_ves')
                ->label('COMISION VES'),
            ExportColumn::make('porcent_agente')
                ->label('% AGENTE'),
            ExportColumn::make('commission_agent_usd')
                ->label('COMISION USD'),
            ExportColumn::make('commission_agent_ves')
                ->label('COMISION VES'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your commission export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

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