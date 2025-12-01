<?php

namespace App\Filament\Exports;

use App\Models\Affiliation;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;

class AffiliationExporter extends Exporter
{
    protected static ?string $model = Affiliation::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('individual_quote_id')
                ->label('COTIZACIÓN INDIVIDUAL'),    
            ExportColumn::make('owner_code')
                ->label('PERTENECE A:'),
            ExportColumn::make('code')
                ->label('CÓDIGO AFILIACIÓN'),
            ExportColumn::make('code_agency')
                ->label('CÓDIGO AGENCIA'),
            ExportColumn::make('agent.name')
                ->label('AGENTE'),
            ExportColumn::make('plan.description')
                ->label('PLAN'),
            ExportColumn::make('coverage.price')
                ->label('COBERTURA'),
            ExportColumn::make('payment_frequency')
                ->label('FRECUENCIA DE PAGO'),
            ExportColumn::make('full_name_payer')
                ->label('NOMBRE COMPLETO PAGADOR'),
            ExportColumn::make('nro_identificacion_payer')
                ->label('C.I. PAGADOR'),
            ExportColumn::make('phone_payer')
                ->label('TELÉFONO PAGADOR'),
            ExportColumn::make('email_payer')
                ->label('CORREO PAGADOR'),
            ExportColumn::make('relationship_payer')
                ->label('RELACIÓN CON EL PAGADOR'),
            ExportColumn::make('full_name_ti')
                ->label('NOMBRE COMPLETO TITULAR DE LA AFILIACIÓN'),
            ExportColumn::make('nro_identificacion_ti')
                ->label('C.I. TITULAR DE LA AFILIACIÓN'),
            ExportColumn::make('sex_ti')
                ->label('SEXO TITULAR DE LA AFILIACIÓN'),
            ExportColumn::make('birth_date_ti')
                ->label('FECHA DE NACIMIENTO TITULAR DE LA AFILIACIÓN'),
            ExportColumn::make('adress_ti')
                ->label('DIRECCIÓN TITULAR DE LA AFILIACIÓN'),
            ExportColumn::make('city_id_ti')
                ->label('CIUDAD TITULAR DE LA AFILIACIÓN'),
            ExportColumn::make('state_id_ti')
                ->label('ESTADO TITULAR DE LA AFILIACIÓN'),
            ExportColumn::make('country_id_ti')
                ->label('PAÍS TITULAR DE LA AFILIACIÓN'),    
            ExportColumn::make('region_ti')
                ->label('REGIÓN TITULAR DE LA AFILIACIÓN'),    
            ExportColumn::make('phone_ti')
                ->label('TELÉFONO TITULAR DE LA AFILIACIÓN'),    
            ExportColumn::make('email_ti')
                ->label('CORREO TITULAR DE LA AFILIACIÓN'),
            ExportColumn::make('created_by')
                ->label('CREADO POR'),
            ExportColumn::make('status')
                ->label('ESTATUS'),
            ExportColumn::make('document'),
            ExportColumn::make('activated_at')
                ->label('FECHA DE ACTIVACIÓN'),
            ExportColumn::make('family_members')
                ->label('FAMILIARES'),
            ExportColumn::make('vaucher_ils')
                ->label('VAUCHER ILS'),
            ExportColumn::make('date_payment_initial_ils')
                ->label('FECHA PAGO INICIAL ILS'),  
            ExportColumn::make('date_payment_final_ils')
                ->label('FECHA PAGO FINAL ILS'),
            ExportColumn::make('created_at')
                ->label('FECHA DE CREACIÓN'),
            ExportColumn::make('updated_at')
                ->label('FECHA DE ACTUALIZACIÓN'),
            ExportColumn::make('fee_anual')
                ->label('FEE ANUAL'),
            ExportColumn::make('total_amount')
                ->label('MONTO TOTAL'),
            ExportColumn::make('business_unit_id')
                ->label('UNIDAD DE NEGOCIO'),    
            ExportColumn::make('business_line_id')
                ->label('LÍNEA DE NEGOCIO'),
            ExportColumn::make('service_providers')
                ->label('PROVEEDORES DE SERVICIO'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Tu exportación de afiliaciones ha finalizado y ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' registros exportados.';

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
            ->setFontSize(11)
            ->setFontName('Helvetica')
            ->setFontColor(Color::rgb(252, 254, 253))
            ->setBackgroundColor(Color::rgb(33, 65, 56))
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
    }

    public function getXlsxCellStyle(): ?Style
    {
        return (new Style())
            ->setFontSize(10)
            ->setFontName('Helvetica')
            ->setFontColor(Color::rgb(0, 0, 0))
            ->setCellAlignment(CellAlignment::LEFT)
            ->setCellVerticalAlignment(CellVerticalAlignment::TOP);
    }
}