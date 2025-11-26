<?php

namespace App\Filament\Business\Resources\Plans\Schemas;

use App\Models\Fee;
use App\Models\Plan;
use App\Models\Limit;
use App\Models\Agency;
use App\Models\Benefit;
use App\Models\AgeRange;
use App\Models\Coverage;
use App\Models\BusinessUnit;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Repeater\TableColumn;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                
                Section::make('PLANES')
                    ->description('Formulario para el registro de los planes. Campo Requerido(*)')
                    ->icon('heroicon-s-squares-plus')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('code')
                                ->label('Código')
                                ->prefixIcon('heroicon-m-clipboard-document-check')
                                ->default(function () {
                                    if (Plan::max('id') == null) {
                                        $parte_entera = 0;
                                    } else {
                                        $parte_entera = Plan::max('id');
                                    }
                                    return 'TDEC-PL-000' . $parte_entera + 1;
                                })
                                ->required()
                                ->disabled()
                                ->dehydrated()
                                ->maxLength(255),
                        ])->columnSpanFull()->columns(3),
                        TextInput::make('description')
                            ->label('Definición')
                            ->prefixIcon('heroicon-m-pencil')
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('description', strtoupper($state));
                            })
                            ->live(onBlur: true)
                            ->required()
                            ->maxLength(255),

                        //UNIDAD DE NEGOCIOS
                        //-------------------------------------------------
                        Select::make('business_unit_id')
                            ->label('Unidad de Negocio')
                            ->options(BusinessUnit::all()->pluck('definition', 'id'))
                            ->prefixIcon('heroicon-m-pencil')
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Section::make('UNIDAD DE NEGOCIO')
                                    ->description('Formulario para el registro de la unidad de negocio. Campo Requerido(*)')
                                    ->icon('heroicon-m-rectangle-group')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextInput::make('code')
                                                ->label('Código')
                                                ->prefixIcon('heroicon-m-clipboard-document-check')
                                                ->default(function () {
                                                    if (BusinessUnit::max('id') == null) {
                                                        $parte_entera = 0;
                                                    } else {
                                                        $parte_entera = BusinessUnit::max('id');
                                                    }
                                                    return 'TDEC-UN-000' . $parte_entera + 1;
                                                })
                                                ->required()
                                                ->disabled()
                                                ->dehydrated()
                                                ->maxLength(255),
                                        ]),
                                        TextInput::make('definition')
                                            ->label('Definición')
                                            ->prefixIcon('heroicon-m-pencil')
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('definition', strtoupper($state));
                                            })
                                            ->live(onBlur: true)
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('status')
                                            ->label('Estatus')
                                            ->prefixIcon('heroicon-m-shield-check')
                                            ->disabled()
                                            ->dehydrated()
                                            ->maxLength(255)
                                            ->default('ACTIVO'),
                                        TextInput::make('created_by')
                                            ->label('Creado Por:')
                                            ->prefixIcon('heroicon-s-user-circle')
                                            ->disabled()
                                            ->dehydrated()
                                            ->default(Auth::user()->name)
                                            ->maxLength(255),
                                    ])->columnSpanFull()->columns(3),
                            ]),
                        Select::make('type')
                            ->label('Tipo de Plan')
                            ->options([
                                'BASICO' => 'BÁSICO',
                                'DRESS-TAILOR' => 'DRESS-TAILOR',
                            ])
                            ->default('BASICO')
                            ->helperText('DRESS-TAILOR, son los planes que se utilizaran para las cotizaciones hechas a la medida del cliente.')
                            ->prefixIcon('heroicon-m-pencil')
                            ->preload()
                            ->live()
                            ->required(),
                        TextInput::make('status')
                            ->label('Estatus')
                            ->prefixIcon('heroicon-m-shield-check')
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255)
                            ->default('INACTIVO'),
                        Hidden::make('created_by')->default(Auth::user()->name)
                    ])->columnSpanFull()->columns(4),

                //...Crear los rangos de edad
                Section::make('RANGO DE EDADES Y COBERTURAS')
                    ->hiddenOn('edit')
                    ->description('Formulario dinamico para crear el/los rango(s) etario(e) y sus coberturas con costo Anual . Campo Requerido(*)')
                    ->icon('heroicon-s-squares-plus')
                    ->schema([
                        Repeater::make('edades')
                            ->table([
                                TableColumn::make('Rango de edad'),
                                TableColumn::make('Edad Minima'),
                                TableColumn::make('Edad Maxima'),
                            ])
                            ->schema([
                                TextInput::make('range')
                                    ->required(),
                                TextInput::make('age_init')
                                    ->numeric()
                                    ->required(),
                                TextInput::make('age_end')
                                    ->numeric()   
                                    ->required(),
                            ]),
                        Repeater::make('coberturas')
                            ->table([
                                TableColumn::make('Cobertura'),
                                TableColumn::make('Tarifa Anual'),
                            ])
                            ->schema([
                                TextInput::make('price_coverages'),
                                TextInput::make('price_fees')
                                    ->numeric()
                                    ->required(),
                            ])
                    ])->columnSpanFull()->columns(2),

                //...Beneficios
                Section::make('ASOCIACION DE BENEFICIOS')
                    ->collapsible()
                    ->description('Seleccion multiple de beneficios')
                    ->icon('heroicon-m-trophy')
                    ->schema([
                        Select::make('beneficios')
                            ->label('Beneficios asociados')
                            ->multiple()
                            ->relationship(name: 'benefitPlans', titleAttribute: 'description')
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn(Benefit $record) => "{$record->code} - {$record->description}")
                            ->searchable()
                            ->createOptionForm([
                                Section::make('UNIDAD DE NEGOCIO')
                                    ->description('Formulario para el registro de la unidad de negocio. Campo Requerido(*)')
                                    ->icon('heroicon-m-rectangle-group')
                                    ->schema([
                                        Grid::make()->schema([
                                            TextInput::make('code')
                                                ->label('Código')
                                                ->prefixIcon('heroicon-m-clipboard-document-check')
                                                ->default(function () {
                                                    if (Benefit::max('id') == null) {
                                                        $parte_entera = 0;
                                                    } else {
                                                        $parte_entera = Benefit::max('id');
                                                    }
                                                    return 'TDEC-BN-000' . $parte_entera + 1;
                                                })
                                                ->required()
                                                ->disabled()
                                                ->dehydrated()
                                                ->maxLength(255),
                                        ])->columnSpanFull()->columns(3),
                                        TextInput::make('description')
                                            ->label('Definición')
                                            ->prefixIcon('heroicon-m-pencil')
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('description', strtoupper($state));
                                            })
                                            ->live(onBlur: true)
                                            ->required()
                                            ->maxLength(255),

                                        Select::make('limit_id')
                                            ->label('Límite de Consumo del Beneficio')
                                            ->relationship('limit', 'description')
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                Section::make('LIMITES')
                                                    ->description('Formulario para el registro de los limites asociados a los beneficios de planes. Campo Requerido(*)')
                                                    ->icon('heroicon-c-adjustments-horizontal')
                                                    ->schema([
                                                        Grid::make(3)->schema([
                                                            TextInput::make('code')
                                                                ->label('Código')
                                                                ->prefixIcon('heroicon-m-clipboard-document-check')
                                                                ->default(function () {
                                                                    if (Limit::max('id') == null) {
                                                                        $parte_entera = 0;
                                                                    } else {
                                                                        $parte_entera = Limit::max('id');
                                                                    }
                                                                    return 'TDEC-BN-000' . $parte_entera + 1;
                                                                })
                                                                ->required()
                                                                ->disabled()
                                                                ->dehydrated()
                                                                ->maxLength(255),
                                                        ]),
                                                        TextInput::make('description')
                                                            ->label('Definición')
                                                            ->prefixIcon('heroicon-m-pencil')
                                                            ->afterStateUpdated(function (Set $set, $state) {
                                                                $set('description', strtoupper($state));
                                                            })
                                                            ->live(onBlur: true)
                                                            ->required()
                                                            ->maxLength(255),
                                                        TextInput::make('status')
                                                            ->label('Estatus')
                                                            ->prefixIcon('heroicon-m-shield-check')
                                                            ->disabled()
                                                            ->dehydrated()
                                                            ->maxLength(255)
                                                            ->default('ACTIVO'),
                                                        TextInput::make('created_by')
                                                            ->label('Creado Por:')
                                                            ->prefixIcon('heroicon-s-user-circle')
                                                            ->disabled()
                                                            ->dehydrated()
                                                            ->default(Auth::user()->name)
                                                            ->maxLength(255),
                                                    ])->columnSpanFull()->columns(3),
                                            ]),
                                        TextInput::make('status')
                                            ->label('Estatus')
                                            ->prefixIcon('heroicon-m-shield-check')
                                            ->disabled()
                                            ->dehydrated()
                                            ->maxLength(255)
                                            ->default('ACTIVO'),
                                        TextInput::make('created_by')
                                            ->label('Creado Por:')
                                            ->prefixIcon('heroicon-s-user-circle')
                                            ->disabled()
                                            ->dehydrated()
                                            ->default(Auth::user()->name)
                                            ->maxLength(255),
                                    ])->columnSpanFull()->columns(3),
                            ]),
                    ])->columnSpanFull()->columns(1),

                //.. Asociacion de Beneficios y Coberturas
                Section::make('ASIGNACIÓN DE PLANES PARA AGENCIAS')
                    ->hidden(fn(Get $get) => $get('type') != 'DRESS-TAILOR')
                    ->collapsible()
                    ->description('Seleccion multiple de agencias, estas agencia son las que podrán cotizar el plan')
                    ->icon('heroicon-s-shopping-cart')
                    ->schema([
                        Select::make('agencies')
                            ->label('Agencias asociadas al Plan')
                            ->relationship(name: 'agencyPlans', titleAttribute: 'name_corporative')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ])->columnSpanFull()->columns(1),

            ]);
    }
}