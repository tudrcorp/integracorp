<?php

namespace App\Filament\Resources\TdevReports\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TdevReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('fecha'),
                TextEntry::make('vaucher'),
                TextEntry::make('agente'),
                TextEntry::make('subagente'),
                TextEntry::make('salida'),
                TextEntry::make('regreso'),
                TextEntry::make('fecha_anulacion'),
                TextEntry::make('pasajero'),
                TextEntry::make('nacionalidad'),
                TextEntry::make('tipo_documento'),
                TextEntry::make('nro_documento'),
                TextEntry::make('categoria_del_plan'),
                TextEntry::make('descripcion_del_plan'),
                TextEntry::make('origen_del_viaje'),
                TextEntry::make('nro_dias_de_servicio'),
                TextEntry::make('edad'),
                TextEntry::make('estatus_del_vaucher'),
                TextEntry::make('referencia'),
                TextEntry::make('plan_familiar'),
                TextEntry::make('descuento')
                    ->numeric(),
                TextEntry::make('impuesto')
                    ->numeric(),
                TextEntry::make('precio_upgrade')
                    ->numeric(),
                TextEntry::make('precio_de_venta')
                    ->numeric(),
                TextEntry::make('total_precio_venta')
                    ->numeric(),
                TextEntry::make('fecha_pago_vaucher'),
                TextEntry::make('forma_de_pago'),
                TextEntry::make('entidad_bancaria_receptora'),
                TextEntry::make('referencia_bancaria'),
                TextEntry::make('tasa_pago')
                    ->numeric(),
                TextEntry::make('monto_abonado_en_cuenta')
                    ->numeric(),
                TextEntry::make('estatus_pago'),
                TextEntry::make('dias_emision'),
                TextEntry::make('porcen_comision')
                    ->numeric(),
                TextEntry::make('comision_agencia')
                    ->numeric(),
                TextEntry::make('comision_agente')
                    ->numeric(),
                TextEntry::make('comision_subagente')
                    ->numeric(),
                TextEntry::make('monto_comision')
                    ->numeric(),
                TextEntry::make('estatus_comision'),
                TextEntry::make('fecha_pago_comision'),
                TextEntry::make('referencia_bancaria_comision'),
                TextEntry::make('relacion_comision'),
                TextEntry::make('observaciones'),
                TextEntry::make('neto_del_servicio')
                    ->numeric(),
                TextEntry::make('utilidad_tdev')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
