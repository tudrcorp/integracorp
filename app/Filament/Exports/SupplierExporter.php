<?php

namespace App\Filament\Exports;

use App\Models\Supplier;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;

class SupplierExporter extends Exporter
{
    protected static ?string $model = Supplier::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')->label('Nombre del Proveedor'),
            ExportColumn::make('rif')->label('RIF'),
            ExportColumn::make('razon_social')->label('Razon Social'),
            ExportColumn::make('status_convenio')->label('Estatus del Convenio'),
            ExportColumn::make('status_sistema')->label('Estatus del Sistema'),
            ExportColumn::make('SupplierClasificacion.description')->label('Clasificacion del Proveedor'),
            ExportColumn::make('tipo_clinica')->label('Tipo de Clinica'),
            ExportColumn::make('type_service')->label('Tipo de Servicio'),
            ExportColumn::make('state.definition')->label('Estado'),
            ExportColumn::make('city.definition')->label('Ciudad'),
            ExportColumn::make('tipo_servicio')->label('Tipo de Servicio'),
            ExportColumn::make('state_services')->label('Prestan Servicios en:')->listAsJson(),
            ExportColumn::make('personal_phone')->label('Teléfono Celular'),
            ExportColumn::make('local_phone')->label('Teléfono Local'),
            ExportColumn::make('correo_principal')->label('Correo Principal'),
            ExportColumn::make('afiliacion_proveedor')->label('Afiliación Proveedor'),
            ExportColumn::make('ubicacion_principal')->label('Ubicación Principal'),
            ExportColumn::make('convenio_pago')->label('Convenio de Pago'),
            ExportColumn::make('tiempo_credito')->label('Tiempo de Credito'),
            ExportColumn::make('promedio_costo_proveedor')->label('Promedio Costo Proveedor'),
            ExportColumn::make('densitometria_osea')->label('Densitómetro'),
            ExportColumn::make('dialisis')->label('Equipo de Dialisis'),
            ExportColumn::make('electrocardiograma_centro')->label('Electrocardiógrafo'),
            ExportColumn::make('equipos_especiales_oftalmologia'),
            ExportColumn::make('mamografia')->label('Mamógrafo'),
            ExportColumn::make('quirofanos'),
            ExportColumn::make('radioterapia_intraoperatoria'),
            ExportColumn::make('resonancia')->label('Resonador'),
            ExportColumn::make('tomografo')->label('Tomógrafo'),
            ExportColumn::make('uci_pediatrica')->label('UCI Pediatrica(Unidad de Cuidados Intensivos)'),
            ExportColumn::make('uci_adulto')->label('UCI Adulto(Unidad de Cuidados Intensivos)'),
            ExportColumn::make('estacionamiento_propio'),
            ExportColumn::make('ascensor')->label('Ascensor Operativo'),
            ExportColumn::make('robotica')->label('Equipo de  Cirugía Robótica'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your supplier export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

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
