<?php

namespace App\Filament\Administration\Resources\RrhhPrestamos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RrhhPrestamosTable
{
    private const IOS_PRIMARY_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_DANGER_BUTTON_CLASS = 'aviso-btn-ios-danger shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Préstamos RRHH')
            ->description('Control de préstamos, cuotas y estado por colaborador.')
            ->emptyStateHeading('No hay préstamos registrados')
            ->emptyStateDescription('Registra un préstamo para iniciar su control de cuotas.')
            ->emptyStateIcon('heroicon-o-credit-card')
            ->columns([
                TextColumn::make('colaborador_id')
                    ->label('ID Colaborador')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->searchable(),
                TextColumn::make('monto')
                    ->label('Monto')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('nro_cuotas')
                    ->label('Nro. Cuotas')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-m-pencil-square')
                    ->color('primary')
                    ->extraAttributes([
                        'class' => self::IOS_PRIMARY_BUTTON_CLASS,
                    ], merge: true),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->icon('heroicon-m-trash')
                        ->color('danger')
                        ->extraAttributes([
                            'class' => self::IOS_DANGER_BUTTON_CLASS,
                        ], merge: true),
                ]),
            ])
            ->striped();
    }
}
