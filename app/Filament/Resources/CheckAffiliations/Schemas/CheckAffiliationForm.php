<?php

namespace App\Filament\Resources\CheckAffiliations\Schemas;

use App\Models\Fee;
use App\Models\Plan;
use App\Models\Agent;
use App\Models\Agency;
use App\Models\AgeRange;
use App\Models\Coverage;
use Filament\Schemas\Schema;
use App\Models\DetailIndividualQuote;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;

class CheckAffiliationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nro_afiliado')
                    ->numeric(),
                TextInput::make('fecha_emision'),
                TextInput::make('codigo_tdec'),
                TextInput::make('tipo_plan'),

                TextInput::make('proveedor'),
                TextInput::make('nro_vaucher'),
                TextInput::make('cobertura'),

                TextInput::make('tomador'),
                TextInput::make('tipo_doc'),
                TextInput::make('nro_doc'),
                TextInput::make('afiliado'),
                TextInput::make('tipo_doc_dos'),
                TextInput::make('nro_doc_tres'),
                TextInput::make('sexo'),
                TextInput::make('fecha_nacimiento'),
                TextInput::make('edad'),
                TextInput::make('parentesco'),
                TextInput::make('telefono')
                    ->tel(),
                TextInput::make('correo'),
                TextInput::make('estado'),
                TextInput::make('ciudad'),
                TextInput::make('direccion'),
                TextInput::make('vigencia_desde'),
                TextInput::make('vigencia_hasta'),
                TextInput::make('agencia'),

                TextInput::make('agente'),

                TextInput::make('plan'),
                TextInput::make('frecuencia_pago'),
                TextInput::make('forma_pago'),
                TextInput::make('monto_plan'),
                TextInput::make('monto_recibido'),
                TextInput::make('diferencia'),
                TextInput::make('estatus_pago'),
                TextInput::make('moneda'),
                TextInput::make('referencia'),
                TextInput::make('fecha_pago'),
                TextInput::make('pagado_desde'),
                TextInput::make('pagado_hasta'),
                TextInput::make('estatus_renovacion'),
                TextInput::make('estatus_afiliado'),
                TextInput::make('dias_para_vencer'),
                TextInput::make('estado_del_plan'),
                TextInput::make('pagado_ils_desde'),
                TextInput::make('pagado_ils_hasta'),
                TextInput::make('dia_vencimiento_ils'),
                TextInput::make('estado_plan_ils'),
                TextInput::make('fecha_egreso'),
                Textarea::make('observaciones')
                    ->columnSpanFull(),
                Fieldset::make('Información adicional requerida para INTEGRACORP')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('agency_id')
                            ->label('Agencia')
                            ->options(function (get $get) {
                                return Agency::all()->pluck('name_corporative', 'code');
                            })
                            ->live()
                            ->searchable()
                            ->required()
                            ->validationMessages([
                                'required'  => 'Campo Obligatorio',
                            ])
                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                if ($state == null) {
                                    $set('owner_code', null);
                                    return;
                                }
                                $owner_code = Agency::where('code', $state)->first()->owner_code;
                                $set('owner_code', $owner_code);
                            })
                            ->prefixIcon('heroicon-s-globe-europe-africa'),
                        TextInput::make('owner_code')
                        ->label('Código del propietario')
                        ->disabled()
                        ->dehydrated(),
                        Select::make('agent_id')
                            ->label('Agente')
                            ->options(function (get $get) {
                                return Agent::where('owner_code', $get('agency_id'))->get()->pluck('name', 'id');
                            })
                            ->live()
                            ->searchable()
                            ->required()
                            ->validationMessages([
                                'required'  => 'Campo Obligatorio',
                            ])
                            ->prefixIcon('heroicon-s-globe-europe-africa')
                            ->preload(),
                        Select::make('plan_id')
                            ->options(function () {
                                return Plan::all()->pluck('description', 'id');
                            })
                            ->label('Planes')
                            ->required()
                            ->validationMessages([
                                'required'  => 'Campo Obligatorio',
                            ])
                            ->live()
                            ->preload()
                            ->placeholder('Seleccione plan(es)'),

                        Select::make('coverage_id')
                            ->label('Cobertura')
                            ->options(function (get $get) {
                                return Coverage::where('plan_id', $get('plan_id'))->get()->pluck('price', 'id');
                            })
                            ->live()
                            ->searchable()
                            ->prefixIcon('heroicon-s-globe-europe-africa')
                            ->preload(),
                        Select::make('age_range_id')
                            ->label('Rango de edad')
                            ->options(function (get $get) {
                                return AgeRange::where('plan_id', $get('plan_id'))->get()->pluck('range', 'id');
                            })
                            ->live()
                            ->searchable()
                            ->required()
                            ->validationMessages([
                                'required'  => 'Campo Obligatorio',
                            ])
                            ->prefixIcon('heroicon-s-globe-europe-africa')
                            ->preload(),
                        Select::make('fee')
                            ->label('Tarifa Anual')
                            ->options(function (get $get) {
                                return Fee::where('age_range_id', $get('age_range_id'))->where('coverage_id', $get('coverage_id'))->get()->pluck('price', 'price');
                            })
                            ->live()
                            ->searchable()
                            ->required()
                            ->validationMessages([
                                'required'  => 'Campo Obligatorio',
                            ])
                            ->prefixIcon('heroicon-s-globe-europe-africa')
                            ->preload(),
                        Select::make('payment_frequency')
                            ->label('Frecuencia de pago')
                            ->live()
                            ->options([
                                'ANUAL'      => 'ANUAL',
                                'SEMESTRAL'  => 'SEMESTRAL',
                                'TRIMESTRAL' => 'TRIMESTRAL',
                            ])
                            ->searchable()
                            ->live()
                            ->prefixIcon('heroicon-s-globe-europe-africa')
                            ->required()
                            ->validationMessages([
                                'required'  => 'Campo Obligatorio',
                            ])
                            ->preload(),
                        TextInput::make('total_persons')
                        ->numeric()
                        ->required()
                        ->validationMessages([
                            'required'  => 'Campo Obligatorio',
                        ]),
                        Hidden::make('status_migration')
                            ->default('SIN PROCESAR')
                    ])
            ]);
    }
}