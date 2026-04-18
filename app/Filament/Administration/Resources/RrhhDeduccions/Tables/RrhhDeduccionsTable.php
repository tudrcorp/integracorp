<?php

namespace App\Filament\Administration\Resources\RrhhDeduccions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RrhhDeduccionsTable
{
    private const IOS_PRIMARY_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_DANGER_BUTTON_CLASS = 'aviso-btn-ios-danger shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Deducciones RRHH')
            ->description('Gestión de deducciones monetarias asociadas a cargos del colaborador.')
            ->emptyStateHeading('No hay deducciones registradas')
            ->emptyStateDescription('Crea una deducción para controlar descuentos por cargo.')
            ->emptyStateIcon('heroicon-o-minus-circle')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable(),
                TextColumn::make('monto')
                    ->label('Monto US$')
                    ->color('warning')
                    ->searchable(),
                TextColumn::make('cargo.description')
                    ->label('Cargo')
                    ->badge()
                    ->icon('heroicon-o-clipboard-document')
                    ->searchable(),
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
}
