<?php

namespace App\Filament\Administration\Resources\RrhhNominas\Schemas;

use App\Models\RrhhNomina;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

class RrhhNominaInfolist
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumen del cálculo')
                    ->description('Totales del período quincenal (sueldo al 50%) y tasa BCV utilizada para la conversión a VES.')
                    ->icon('heroicon-o-calculator')
                    ->extraAttributes(['class' => self::SECTION_CARD])
                    ->schema([
                        Fieldset::make('Período y tasa')
                            ->schema([
                                TextEntry::make('periodo')
                                    ->label('Período')
                                    ->state(fn (RrhhNomina $record): string => $record->periodoLabel())
                                    ->icon('heroicon-o-calendar-days'),
                                TextEntry::make('tasa_bcv')
                                    ->label('Tasa BCV')
                                    ->numeric(decimalPlaces: 4)
                                    ->suffix(' VES/USD')
                                    ->icon('heroicon-o-currency-dollar'),
                                TextEntry::make('detalleNomina_count')
                                    ->label('Colaboradores')
                                    ->state(fn (RrhhNomina $record): int => $record->detalleNomina()->count())
                                    ->icon('heroicon-o-users'),
                            ])
                            ->columns(3),
                        Fieldset::make('Totales USD$ / VES')
                            ->schema([
                                TextEntry::make('total_salarios')
                                    ->label('Total sueldos')
                                    ->formatStateUsing(fn ($state, RrhhNomina $record): string => self::pair($state, $record->total_salarios_ves)),
                                TextEntry::make('total_descuentos')
                                    ->label('Total descuentos')
                                    ->color('danger')
                                    ->formatStateUsing(fn ($state, RrhhNomina $record): string => self::pair($state, $record->total_descuentos_ves)),
                                TextEntry::make('total_asignaciones')
                                    ->label('Total asignaciones')
                                    ->color('success')
                                    ->formatStateUsing(fn ($state, RrhhNomina $record): string => self::pair($state, $record->total_asignaciones_ves)),
                                TextEntry::make('total_prestamos')
                                    ->label('Total préstamos')
                                    ->color('warning')
                                    ->formatStateUsing(fn ($state, RrhhNomina $record): string => self::pair($state, $record->total_prestamos_ves)),
                                TextEntry::make('total_neto')
                                    ->label('Total neto a pagar')
                                    ->weight(FontWeight::Bold)
                                    ->color('primary')
                                    ->formatStateUsing(fn ($state, RrhhNomina $record): string => self::pair($state, $record->total_neto_ves)),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function pair(mixed $usd, mixed $ves): string
    {
        return 'USD$ '.number_format((float) $usd, 2, '.', ',')
            .' · VES '.number_format((float) $ves, 2, '.', ',');
    }
}
