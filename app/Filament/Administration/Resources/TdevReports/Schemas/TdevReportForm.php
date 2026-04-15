<?php

namespace App\Filament\Administration\Resources\TdevReports\Schemas;

use App\Enums\StatusComision;
use App\Enums\StatusPago;
use App\Enums\StatusVaucher;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TdevReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('fecha')
                    ->required(),
                TextInput::make('vaucher')
                    ->required(),
                TextInput::make('agencia'),
                TextInput::make('agente')
                    ->required(),
                TextInput::make('subagente'),
                TextInput::make('salida')
                    ->required(),
                TextInput::make('regreso')
                    ->required(),
                TextInput::make('fecha_anulacion')
                    ->required(),
                TextInput::make('pasajero')
                    ->required(),
                TextInput::make('nacionalidad')
                    ->required(),
                TextInput::make('tipo_documento')
                    ->required(),
                TextInput::make('nro_documento')
                    ->required(),
                TextInput::make('categoria_del_plan')
                    ->required(),
                TextInput::make('descripcion_del_plan')
                    ->required(),
                TextInput::make('origen_del_viaje')
                    ->required(),
                TextInput::make('destino'),
                TextInput::make('nro_dias_de_servicio')
                    ->required(),
                TextInput::make('edad')
                    ->required(),
                Select::make('estatus_vaucher')
                    ->label('Estatus del voucher')
                    ->options(StatusVaucher::options())
                    ->required()
                    ->native(false)
                    ->searchable(),
                TextInput::make('referencia')
                    ->required(),
                TextInput::make('plan_familiar')
                    ->required(),
                TextInput::make('descuento')
                    ->required()
                    ->numeric(),
                TextInput::make('impuesto')
                    ->required()
                    ->numeric(),
                TextInput::make('precio_upgrade')
                    ->required()
                    ->numeric(),
                TextInput::make('precio_de_venta')
                    ->required()
                    ->numeric(),
                TextInput::make('total_precio_venta')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('fecha_pago_vaucher'),
                TextInput::make('forma_de_pago'),
                TextInput::make('entidad_bancaria_receptora'),
                TextInput::make('referencia_bancaria'),
                TextInput::make('tasa_pago')
                    ->numeric()
                    ->default(0.0),
                TextInput::make('monto_abonado_en_cuenta')
                    ->numeric()
                    ->default(0.0),
                Select::make('estatus_pago')
                    ->label('Estatus de pago')
                    ->options(StatusPago::options())
                    ->nullable()
                    ->native(false)
                    ->searchable(),
                TextInput::make('dias_emision'),
                TextInput::make('porcen_comision')
                    ->numeric()
                    ->default(0.0),
                TextInput::make('comision_agencia')
                    ->numeric()
                    ->default(0.0),
                TextInput::make('comision_agente')
                    ->numeric()
                    ->default(0.0),
                TextInput::make('comision_subagente')
                    ->numeric()
                    ->default(0.0),
                TextInput::make('monto_comision')
                    ->numeric()
                    ->default(0.0),
                Select::make('estatus_comision')
                    ->label('Estatus de comisión')
                    ->options(StatusComision::options())
                    ->nullable()
                    ->native(false)
                    ->searchable(),
                TextInput::make('fecha_pago_comision'),
                TextInput::make('referencia_bancaria_comision'),
                TextInput::make('relacion_comision'),
                Textarea::make('observaciones')
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('neto_del_servicio')
                    ->numeric()
                    ->default(0.0),
                TextInput::make('utilidad_tdev')
                    ->numeric()
                    ->default(0.0),
                TextInput::make('status_report')
                    ->default('1'),
            ]);
    }
}
