<?php

namespace App\Filament\Administration\Resources\RrhhNominas\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class RrhhNominaForm
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const INNER_CARD = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('rrhhNominaFormTabs')
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Totales de nómina')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Fieldset::make('Período y tasa')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Parámetros de pago')
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                DatePicker::make('fecha_desde')
                                                    ->label('Fecha desde')
                                                    ->native(false)
                                                    ->displayFormat('d/m/Y')
                                                    ->disabled()
                                                    ->dehydrated(),
                                                DatePicker::make('fecha_hasta')
                                                    ->label('Fecha hasta')
                                                    ->native(false)
                                                    ->displayFormat('d/m/Y')
                                                    ->disabled()
                                                    ->dehydrated(),
                                                TextInput::make('tasa_bcv')
                                                    ->label('Tasa BCV')
                                                    ->numeric()
                                                    ->prefix('VES')
                                                    ->suffix('/USD')
                                                    ->disabled()
                                                    ->dehydrated(),
                                            ])
                                            ->columns(3)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                                Fieldset::make('Totales USD$')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Montos en dólares')
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                TextInput::make('total_salarios')->label('Total sueldos')->prefix('USD$')->numeric()->disabled()->dehydrated(),
                                                TextInput::make('total_descuentos')->label('Total descuentos')->prefix('USD$')->numeric()->disabled()->dehydrated(),
                                                TextInput::make('total_asignaciones')->label('Total asignaciones')->prefix('USD$')->numeric()->disabled()->dehydrated(),
                                                TextInput::make('total_prestamos')->label('Total préstamos')->prefix('USD$')->numeric()->disabled()->dehydrated(),
                                                TextInput::make('total_neto')->label('Total neto')->prefix('USD$')->numeric()->disabled()->dehydrated(),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                                Fieldset::make('Totales VES')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Montos en bolívares')
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                TextInput::make('total_salarios_ves')->label('Total sueldos')->prefix('VES')->numeric()->disabled()->dehydrated(),
                                                TextInput::make('total_descuentos_ves')->label('Total descuentos')->prefix('VES')->numeric()->disabled()->dehydrated(),
                                                TextInput::make('total_asignaciones_ves')->label('Total asignaciones')->prefix('VES')->numeric()->disabled()->dehydrated(),
                                                TextInput::make('total_prestamos_ves')->label('Total préstamos')->prefix('VES')->numeric()->disabled()->dehydrated(),
                                                TextInput::make('total_neto_ves')->label('Total neto')->prefix('VES')->numeric()->disabled()->dehydrated(),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
