<?php

namespace App\Filament\Administration\Resources\RrhhAsignacions\Schemas;

use App\Support\Rrhh\RrhhValorCalculo;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class RrhhAsignacionForm
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const INNER_CARD = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('rrhhAsignacionFormTabs')
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Información principal')
                            ->icon('heroicon-o-plus-circle')
                            ->schema([
                                Fieldset::make('Identificación de la asignación')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Datos generales')
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Nombre de la asignación')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->placeholder('Ej: Bono de transporte')
                                                    ->prefixIcon('heroicon-m-tag'),
                                                Textarea::make('description')
                                                    ->label('Descripción')
                                                    ->required()
                                                    ->rows(3)
                                                    ->placeholder('Detalle o condiciones de la asignación.')
                                                    ->helperText('Explica el alcance o criterio de esta asignación.')
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                                Fieldset::make('Aplicación')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Alcance de la asignación')
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                ToggleButtons::make('aplicacion')
                                                    ->label('¿A quién se aplica?')
                                                    ->options([
                                                        'departamento' => 'Departamento',
                                                        'colaborador' => 'Colaborador',
                                                    ])
                                                    ->icons([
                                                        'departamento' => Heroicon::OutlinedBuildingOffice2,
                                                        'colaborador' => Heroicon::OutlinedUser,
                                                    ])
                                                    ->inline()
                                                    ->live()
                                                    ->default('departamento')
                                                    ->required()
                                                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                                                        if ($state === 'departamento') {
                                                            $set('colaborador_id', null);
                                                        }

                                                        if ($state === 'colaborador') {
                                                            $set('departamento_id', null);
                                                        }
                                                    })
                                                    ->helperText('Puede aplicar la asignación a un departamento completo o a un colaborador específico.')
                                                    ->columnSpanFull(),
                                                Select::make('departamento_id')
                                                    ->label('Departamento')
                                                    ->relationship('departamento', 'description', fn ($query) => $query->orderBy('description'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->native(false)
                                                    ->prefixIcon('heroicon-m-building-office-2')
                                                    ->placeholder('Seleccione departamento')
                                                    ->visible(fn (Get $get): bool => $get('aplicacion') === 'departamento')
                                                    ->required(fn (Get $get): bool => $get('aplicacion') === 'departamento')
                                                    ->columnSpanFull(),
                                                Select::make('colaborador_id')
                                                    ->label('Colaborador')
                                                    ->relationship('colaborador', 'fullName', fn ($query) => $query->orderBy('fullName'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->native(false)
                                                    ->prefixIcon('heroicon-m-user')
                                                    ->placeholder('Seleccione colaborador')
                                                    ->visible(fn (Get $get): bool => $get('aplicacion') === 'colaborador')
                                                    ->required(fn (Get $get): bool => $get('aplicacion') === 'colaborador')
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                                Fieldset::make('Valor')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Tipo de cálculo')
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                ToggleButtons::make('tipo_valor')
                                                    ->label('¿Cómo se calcula?')
                                                    ->options(RrhhValorCalculo::tipoOptions())
                                                    ->icons([
                                                        RrhhValorCalculo::TIPO_MONTO => Heroicon::OutlinedBanknotes,
                                                        RrhhValorCalculo::TIPO_PORCENTAJE => Heroicon::OutlinedReceiptPercent,
                                                    ])
                                                    ->inline()
                                                    ->live()
                                                    ->default(RrhhValorCalculo::TIPO_MONTO)
                                                    ->required()
                                                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                                                        if ($state === RrhhValorCalculo::TIPO_MONTO) {
                                                            $set('porcentaje', null);
                                                        }

                                                        if ($state === RrhhValorCalculo::TIPO_PORCENTAJE) {
                                                            $set('monto', null);
                                                        }
                                                    })
                                                    ->helperText('Monto fijo sobre el sueldo total, o porcentaje sobre el sueldo base del colaborador.')
                                                    ->columnSpanFull(),
                                                TextInput::make('monto')
                                                    ->label('Monto fijo')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->step(0.01)
                                                    ->prefix('US$')
                                                    ->placeholder('0.00')
                                                    ->helperText('Se resta o suma como monto fijo sobre el sueldo total. Use punto(.) para decimales.')
                                                    ->visible(fn (Get $get): bool => $get('tipo_valor') === RrhhValorCalculo::TIPO_MONTO)
                                                    ->required(fn (Get $get): bool => $get('tipo_valor') === RrhhValorCalculo::TIPO_MONTO)
                                                    ->columnSpanFull(),
                                                TextInput::make('porcentaje')
                                                    ->label('Porcentaje sobre sueldo base')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->step(0.01)
                                                    ->suffix('%')
                                                    ->placeholder('0.00')
                                                    ->prefixIcon('heroicon-m-receipt-percent')
                                                    ->helperText('Se calcula como porcentaje del sueldo base del colaborador.')
                                                    ->visible(fn (Get $get): bool => $get('tipo_valor') === RrhhValorCalculo::TIPO_PORCENTAJE)
                                                    ->required(fn (Get $get): bool => $get('tipo_valor') === RrhhValorCalculo::TIPO_PORCENTAJE)
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                            ]),
                    ]),
                Hidden::make('created_by')
                    ->default(fn () => Auth::user()?->name ?? '')
                    ->dehydrated()
                    ->hiddenOn('edit'),
                Hidden::make('updated_by')
                    ->default(fn () => Auth::user()?->name ?? '')
                    ->dehydrated(),
            ]);
    }
}
