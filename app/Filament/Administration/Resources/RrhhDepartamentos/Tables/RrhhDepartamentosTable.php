<?php

namespace App\Filament\Administration\Resources\RrhhDepartamentos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RrhhDepartamentosTable
{
    private const IOS_PRIMARY_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_DANGER_BUTTON_CLASS = 'aviso-btn-ios-danger shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Departamentos RRHH')
            ->description('Estructura organizacional para agrupar cargos y colaboradores.')
            ->emptyStateHeading('No hay departamentos registrados')
            ->emptyStateDescription('Crea un departamento para iniciar la estructura de RRHH.')
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->columns([
                TextColumn::make('description')
                    ->label('Departamento')
                    ->searchable(),
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
