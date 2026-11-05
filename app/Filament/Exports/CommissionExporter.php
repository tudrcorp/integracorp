<?php

namespace App\Filament\Exports;

use App\Models\Commission;
use App\Support\CommercialStructure\CommissionReferidorPercentage;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
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
            ExportColumn::make('master_agency')
                ->label('AGENCIA MASTER')
                ->state(fn (Commission $record): string => $record->masterAgencyDisplayName()),
            ExportColumn::make('general_agency')
                ->label('AGENCIA GENERAL')
                ->state(fn (Commission $record): string => $record->generalAgencyDisplayName()),
            ExportColumn::make('structure_agent')
                ->label('AGENTE')
                ->state(fn (Commission $record): string => $record->structureAgentName()),
            ExportColumn::make('subagent')
                ->label('SUBAGENTE')
                ->state(fn (Commission $record): string => $record->subagentName()),
            ExportColumn::make('referidor')
                ->label('REFERIDOR')
                ->state(fn (Commission $record): string => $record->referidorBeneficiaryLabel()),
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
                ->label('COMISION AGENCIA MASTER USD'),
            ExportColumn::make('commission_agency_master_ves')
                ->label('COMISION AGENCIA MASTER VES'),
            ExportColumn::make('porcent_agency_general')
                ->label('% AGENCIA GENERAL'),
            ExportColumn::make('commission_agency_general_usd')
                ->label('COMISION AGENCIA GENERAL USD'),
            ExportColumn::make('commission_agency_general_ves')
                ->label('COMISION AGENCIA GENERAL VES'),
            ExportColumn::make('porcent_agente')
                ->label('% AGENTE'),
            ExportColumn::make('commission_agent_usd')
                ->label('COMISION AGENTE USD'),
            ExportColumn::make('commission_agent_ves')
                ->label('COMISION AGENTE VES'),
            ExportColumn::make('porcent_referidor')
                ->label('% REFERIDOR'),
            ExportColumn::make('commission_referidor_usd')
                ->label('COMISION REFERIDOR USD'),
            ExportColumn::make('commission_referidor_ves')
                ->label('COMISION REFERIDOR VES'),
        ];
    }

    /**
     * Estructura comercial completa sin una consulta por fila: agencia y su master, agente y
     * su responsable (subagentes) y los referidores.
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'agency.masterAgency',
            'agent.ownerAgent',
            ...CommissionReferidorPercentage::eagerLoadRelations(),
            'plan',
            'coverage',
        ]);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'La exportación de comisiones terminó: '.Number::format($export->successful_rows).' '.($export->successful_rows === 1 ? 'fila exportada' : 'filas exportadas').'.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.($failedRowsCount === 1 ? 'fila no se pudo exportar' : 'filas no se pudieron exportar').'.';
        }

        return $body;
    }

    public function getXlsxHeaderCellStyle(): ?Style
    {
        return (new Style)
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
        return (new Style)
            ->setFontSize(8)
            ->setFontName('Helvetica')
            ->setFontColor(Color::rgb(0, 0, 0))
            ->setCellAlignment(CellAlignment::LEFT)
            ->setCellVerticalAlignment(CellVerticalAlignment::TOP);
    }
}
