<?php

namespace App\Filament\Business\Resources\Plans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Models\Fee;
use App\Models\Plan;
use App\Models\Benefit;
use App\Models\Coverage;
use App\Models\BusinessUnit;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Utilities\Set;

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
                            ->required(),
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
                    ])->columnSpanFull()->columns(4),
                Section::make('ASOCIACION DE BENEFICIOS')
                    ->collapsible()
                    ->description('Seleccion multiple de beneficios')
                    ->icon('heroicon-s-share')
                    ->schema([
                        CheckboxList::make('beneficios')
                            ->label('Beneficios asociados')
                            // ->multiple()
                            ->relationship(name: 'benefitPlans', titleAttribute: 'description')
                            ->pivotData([
                                'description' => true,
                            ])
                            ->getOptionLabelFromRecordUsing(fn(Benefit $record) => "{$record->code} - {$record->description}")
                            ->searchable(),
                        // ->searchable()
                        // ->preload(),
                    ])->columnSpanFull()->columns(1),
                Section::make('ASOCIACION DE COBERTURAS')
                    ->collapsible()
                    ->description('Seleccion multiple de coberturas')
                    ->icon('heroicon-s-share')
                    ->schema([
                        CheckboxList::make('coverturas')
                            ->label('Coverturas asociadas')
                            // ->multiple()
                            ->relationship(name: 'coveragePlans', titleAttribute: 'price')
                            ->pivotData([
                                'price' => true,
                            ])
                            ->getOptionLabelFromRecordUsing(fn(Coverage $record) => "{$record->price} US$")
                            ->searchable(),
                    ])->columnSpanFull()->columns(1),

                Section::make('ASOCIACION DE TARIFAS POR RANGO DE EDADES')
                    ->collapsible()
                    ->description('Seleccion multiple de coberturas')
                    ->icon('heroicon-s-share')
                    ->schema([
                        CheckboxList::make('tarifas')
                            ->label('Tarifas asociadas')
                            // ->multiple()
                            ->relationship(name: 'feePlans', titleAttribute: 'range')
                            ->pivotData([
                                'range' => true,
                                'price' => true,
                            ])
                            ->getOptionLabelFromRecordUsing(fn(Fee $record) => "{$record->range}años - Covertura: {$record->coverage} US$ - Tarifa: {$record->price} US$")
                            ->searchable(),
                    ])->columnSpanFull()->columns(1),
            ]);
    }
}