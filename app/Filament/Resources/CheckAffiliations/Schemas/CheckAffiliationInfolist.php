<?php

namespace App\Filament\Resources\CheckAffiliations\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CheckAffiliationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('nro_afiliado')
                    ->numeric(),
                TextEntry::make('fecha_emision'),
                TextEntry::make('codigo_tdec'),
                TextEntry::make('tipo_plan'),
                TextEntry::make('proveedor'),
                TextEntry::make('nro_vaucher'),
                TextEntry::make('cobertura'),
                TextEntry::make('tomador'),
                TextEntry::make('tipo_doc'),
                TextEntry::make('nro_doc'),
                TextEntry::make('afiliado'),
                TextEntry::make('tipo_doc_dos'),
                TextEntry::make('nro_doc_tres'),
                TextEntry::make('sexo'),
                TextEntry::make('fecha_nacimiento'),
                TextEntry::make('edad'),
                TextEntry::make('parentesco'),
                TextEntry::make('telefono'),
                TextEntry::make('correo'),
                TextEntry::make('estado'),
                TextEntry::make('ciudad'),
                TextEntry::make('direccion'),
                TextEntry::make('vigencia_desde'),
                TextEntry::make('vigencia_hasta'),
                TextEntry::make('agencia'),
                TextEntry::make('agente'),
                TextEntry::make('plan'),
                TextEntry::make('frecuencia_pago'),
                TextEntry::make('forma_pago'),
                TextEntry::make('monto_plan'),
                TextEntry::make('monto_recibido'),
                TextEntry::make('diferencia'),
                TextEntry::make('estatus_pago'),
                TextEntry::make('moneda'),
                TextEntry::make('referencia'),
                TextEntry::make('fecha_pago'),
                TextEntry::make('pagado_desde'),
                TextEntry::make('pagado_hasta'),
                TextEntry::make('estatus_renovacion'),
                TextEntry::make('estatus_afiliado'),
                TextEntry::make('dias_para_vencer'),
                TextEntry::make('estado_del_plan'),
                TextEntry::make('pagado_ils_desde'),
                TextEntry::make('pagado_ils_hasta'),
                TextEntry::make('dia_vencimiento_ils'),
                TextEntry::make('estado_plan_ils'),
                TextEntry::make('fecha_egreso'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
