<?php

namespace App\Filament\Imports;

use App\Models\TdevReport;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class TdevReportImporter extends Importer
{
    protected static ?string $model = TdevReport::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('fecha')
                ->requiredMapping(),
            ImportColumn::make('vaucher')
                ->requiredMapping(),
            ImportColumn::make('agencia')
                ->requiredMapping(),
            ImportColumn::make('agente')
                ->requiredMapping(),
            ImportColumn::make('subagente')
                ->rules(['max:255']),
            ImportColumn::make('salida')
                ->requiredMapping(),
            ImportColumn::make('regreso')
                ->requiredMapping(),
            ImportColumn::make('fecha_anulacion')
                ->requiredMapping(),
            ImportColumn::make('pasajero')
                ->requiredMapping(),
            ImportColumn::make('nacionalidad')
                ->requiredMapping(),
            ImportColumn::make('tipo_documento')
                ->requiredMapping(),
            ImportColumn::make('nro_documento')
                ->requiredMapping(),
            ImportColumn::make('categoria_del_plan')
                ->requiredMapping(),
            ImportColumn::make('descripcion_del_plan')
                ->requiredMapping(),
            ImportColumn::make('origen_del_viaje')
                ->requiredMapping(),
            ImportColumn::make('destino')
                ->requiredMapping(),
            ImportColumn::make('nro_dias_de_servicio')
                ->requiredMapping(),
            ImportColumn::make('edad')
                ->requiredMapping(),
            ImportColumn::make('estatus_del_vaucher')
                ->requiredMapping(),
            ImportColumn::make('referencia')
                ->requiredMapping(),
            ImportColumn::make('plan_familiar')
                ->requiredMapping(),
            ImportColumn::make('descuento')
                ->requiredMapping()
                ->numeric(),
            ImportColumn::make('impuesto')
                ->requiredMapping()
                ->numeric(),
            ImportColumn::make('precio_upgrade')
                ->requiredMapping()
                ->numeric(),
            ImportColumn::make('precio_de_venta')
                ->requiredMapping()
                ->numeric(),
                
            //----------------------------------------------
            // ImportColumn::make('total_precio_venta')
            //     ->numeric()
            //     ->rules(['decimal']),
            // ImportColumn::make('fecha_pago_vaucher')
            //     ->rules(['max:255']),
            // ImportColumn::make('forma_de_pago')
            //     ->rules(['max:255']),
            // ImportColumn::make('entidad_bancaria_receptora')
            //     ->rules(['max:255']),
            // ImportColumn::make('referencia_bancaria')
            //     ->rules(['max:255']),
            // ImportColumn::make('tasa_pago')
            //     ->numeric()
            //     ->rules(['decimal']),
            // ImportColumn::make('monto_abonado_en_cuenta')
            //     ->numeric()
            //     ->rules(['decimal']),
            // ImportColumn::make('estatus_pago')
            //     ->rules(['max:255']),
            // ImportColumn::make('dias_emision')
            //     ->rules(['max:255']),
            // ImportColumn::make('porcen_comision')
            //     ->numeric()
            //     ->rules(['decimal']),
            // ImportColumn::make('comision_agencia')
            //     ->numeric()
            //     ->rules(['decimal']),
            // ImportColumn::make('comision_agente')
            //     ->numeric()
            //     ->rules(['decimal']),
            // ImportColumn::make('comision_subagente')
            //     ->numeric()
            //     ->rules(['decimal']),
            // ImportColumn::make('monto_comision')
            //     ->numeric()
            //     ->rules(['decimal']),
            // ImportColumn::make('estatus_comision')
            //     ->rules(['max:255']),
            // ImportColumn::make('fecha_pago_comision')
            //     ->rules(['max:255']),
            // ImportColumn::make('referencia_bancaria_comision')
            //     ->rules(['max:255']),
            // ImportColumn::make('relacion_comision')
            //     ->rules(['max:255']),
            // ImportColumn::make('observaciones')
            //     ->rules(['max:255']),
            // ImportColumn::make('neto_del_servicio')
            //     ->numeric()
            //     ->rules(['decimal']),
            // ImportColumn::make('utilidad_tdev')
            //     ->numeric()
            //     ->rules(['decimal']),
            // ImportColumn::make('status_report')
            //     ->rules(['max:100']),
        ];
    }

    public function resolveRecord(): TdevReport
    {
        return new TdevReport();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your tdev report import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}