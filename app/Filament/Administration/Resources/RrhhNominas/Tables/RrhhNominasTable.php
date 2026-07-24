<?php

namespace App\Filament\Administration\Resources\RrhhNominas\Tables;

use App\Models\RrhhNomina;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RrhhNominasTable
{
    private const IOS_PRIMARY_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_DANGER_BUTTON_CLASS = 'aviso-btn-ios-danger shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Cálculos de nómina')
            ->description('Totales a pagar por período. Montos expresados en USD$ y VES según la tasa BCV de pago.')
            ->emptyStateHeading('No hay cálculos de nómina')
            ->emptyStateDescription('Use «Calcular nómina» para elegir el período quincenal (24 al año), cargar la tasa BCV y generar los totales.')
            ->emptyStateIcon('heroicon-o-currency-dollar')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('periodo')
                    ->label('Período')
                    ->state(fn (RrhhNomina $record): string => $record->periodoLabel())
                    ->icon('heroicon-o-calendar-days'),
                TextColumn::make('tasa_bcv')
                    ->label('Tasa BCV')
                    ->numeric(decimalPlaces: 4)
                    ->suffix(' VES/USD')
                    ->sortable(),
                TextColumn::make('total_salarios')
                    ->label('Total sueldos')
                    ->state(fn (RrhhNomina $record): string => self::usd($record->total_salarios))
                    ->description(fn (RrhhNomina $record): string => self::ves($record->total_salarios_ves))
                    ->sortable(),
                TextColumn::make('total_descuentos')
                    ->label('Total descuentos')
                    ->state(fn (RrhhNomina $record): string => self::usd($record->total_descuentos))
                    ->description(fn (RrhhNomina $record): string => self::ves($record->total_descuentos_ves))
                    ->color('danger')
                    ->sortable(),
                TextColumn::make('total_asignaciones')
                    ->label('Total asignaciones')
                    ->state(fn (RrhhNomina $record): string => self::usd($record->total_asignaciones))
                    ->description(fn (RrhhNomina $record): string => self::ves($record->total_asignaciones_ves))
                    ->color('success')
                    ->sortable(),
                TextColumn::make('total_prestamos')
                    ->label('Total préstamos')
                    ->state(fn (RrhhNomina $record): string => self::usd($record->total_prestamos))
                    ->description(fn (RrhhNomina $record): string => self::ves($record->total_prestamos_ves))
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('total_neto')
                    ->label('Total neto')
                    ->state(fn (RrhhNomina $record): string => self::usd($record->total_neto))
                    ->description(fn (RrhhNomina $record): string => self::ves($record->total_neto_ves))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Generada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver detalle')
                    ->icon('heroicon-m-eye')
                    ->color('primary')
                    ->extraAttributes([
                        'class' => self::IOS_PRIMARY_BUTTON_CLASS,
                    ], merge: true),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas')
                        ->icon('heroicon-m-trash')
                        ->color('danger')
                        ->extraAttributes([
                            'class' => self::IOS_DANGER_BUTTON_CLASS,
                        ], merge: true),
                ]),
            ])
            ->striped();
    }

    private static function usd(mixed $amount): string
    {
        return 'USD$ '.number_format((float) $amount, 2, '.', ',');
    }

    private static function ves(mixed $amount): string
    {
        return 'VES '.number_format((float) $amount, 2, '.', ',');
    }
}
