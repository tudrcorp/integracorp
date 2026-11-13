<?php

namespace App\Filament\Marketing\Resources\Zones\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ZonesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Carpetas / Zonas')
            ->description('Arrastra las carpetas para definir el orden. Este orden define cómo se muestran las pestañas en Zona de descarga.')
            ->defaultSort('position', 'asc')
            ->reorderable('position')
            ->reorderRecordsTriggerAction(
                fn (Action $action, bool $isReordering): Action => $action
                    ->button()
                    ->label($isReordering ? 'Terminar de ordenar' : 'Reordenar carpetas')
            )
            ->striped()
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->icon('heroicon-o-tag')
                    ->placeholder('-'),
                TextColumn::make('zone')
                    ->label('Nombre de la zona')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->icon('heroicon-o-folder')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match (strtoupper((string) $state)) {
                        'ACTIVA', 'ACTIVO' => 'success',
                        'INACTIVA', 'INACTIVO' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst(mb_strtolower($state)))
                    ->sortable()
                    ->icon('heroicon-o-flag')
                    ->placeholder('-'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
