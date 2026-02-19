<?php

namespace App\Filament\Exports;

use App\Models\AffiliationCorporate;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;

class AffiliationCorporateExporter extends Exporter
{
    protected static ?string $model = AffiliationCorporate::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('corporate_quote_id')
            ->label('Cotización Corporativa'),
            ExportColumn::make('owner_code')
            ->label('Código del Dueño'),
            ExportColumn::make('code')
            ->label('Código'),
            ExportColumn::make('code_agency')
            ->label('Código de la Agencia'),
            ExportColumn::make('agent_id')
            ->label('Agente'),
            ExportColumn::make('name_corporate')
            ->label('Nombre de la Corporación'),
            ExportColumn::make('rif')
            ->label('RIF'),
            ExportColumn::make('address')
            ->label('Dirección'),
            ExportColumn::make('city.definition')
            ->label('Ciudad'),
            ExportColumn::make('country.name')
            ->label('País'),
            ExportColumn::make('region')
            ->label('Región'),
            ExportColumn::make('phone')
            ->label('Teléfono'),
            ExportColumn::make('email')
            ->label('Correo Electrónico'),
            ExportColumn::make('full_name_contact')
            ->label('Nombre del Contacto'),
            ExportColumn::make('nro_identificacion_contact')
            ->label('Número de Identificación del Contacto'),
            ExportColumn::make('phone_contact')
            ->label('Teléfono del Contacto'),
            ExportColumn::make('email_contact')
            ->label('Correo Electrónico del Contacto'),
            ExportColumn::make('date_affiliation')
            ->label('Fecha de Afiliación'),
            ExportColumn::make('status')
            ->label('Estado'),
            ExportColumn::make('document')
            ->label('Documento'),
            ExportColumn::make('observations')
            ->label('Observaciones'),
            ExportColumn::make('payment_frequency')
            ->label('Frecuencia de Pago'),
            ExportColumn::make('fee_anual')
            ->label('Tarifa Anual'),
            ExportColumn::make('total_amount')
            ->label('Monto Total'),
            ExportColumn::make('vaucher_ils')
            ->label('Vaucher ILS'),
            ExportColumn::make('date_payment_initial_ils')
            ->label('Fecha de Pago Inicial ILS'),
            ExportColumn::make('date_payment_final_ils')
            ->label('Fecha de Pago Final ILS'),
            ExportColumn::make('document_ils')
            ->label('Documento ILS'),
            ExportColumn::make('created_at')
            ->label('Fecha de Creación'),
            ExportColumn::make('updated_at')
            ->label('Fecha de Actualización'),
            ExportColumn::make('state_id')
            ->label('Estado'),
            ExportColumn::make('poblation')
            ->label('Población'),
            ExportColumn::make('activated_at')
            ->label('Fecha de Activación'),
            ExportColumn::make('ownerAccountManagers')
            ->label('Gerentes de Cuenta'),
            ExportColumn::make('business_unit_id')
            ->label('Unidad de Negocio'),
            ExportColumn::make('business_line_id')
            ->label('Línea de Negocio'),
            ExportColumn::make('service_providers')
            ->label('Proveedores de Servicio'),
            ExportColumn::make('effective_date')
            ->label('Fecha de Efectividad'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your affiliation corporate export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

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
