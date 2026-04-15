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

        ];
    }

    public function resolveRecord(): TdevReport
    {
        return new TdevReport;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your tdev report import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
