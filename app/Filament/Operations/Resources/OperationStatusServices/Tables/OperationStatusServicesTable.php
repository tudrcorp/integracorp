<?php

namespace App\Filament\Operations\Resources\OperationStatusServices\Tables;

use App\Models\OperationStatusService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OperationStatusServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Estatus de servicios')
            ->description('Catálogo de estatus utilizados en órdenes de servicio.')
            ->defaultSort('description')
            ->columns([
                TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => (string) ($state ?? '-'))
                    ->weight('semibold')
                    ->icon(fn (?string $state): string => match ($state) {
                        'EN GESTION' => 'heroicon-m-arrow-path',
                        'CANCELADO', 'NOVEDAD ADMON' => 'heroicon-m-x-circle',
                        'FINALIZADO' => 'heroicon-m-check-circle',
                        'PENDIENTE', 'PENDIENTE POR RESULTADOS' => 'heroicon-m-clock',
                        default => 'heroicon-m-tag',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'EN GESTION' => 'warning',
                        'CANCELADO', 'NOVEDAD ADMON' => 'danger',
                        'FINALIZADO' => 'success',
                        'PENDIENTE', 'PENDIENTE POR RESULTADOS' => 'warning',
                        default => 'gray',
                    })
                    ->copyable()
                    ->copyMessage('Estatus copiado'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable()
                    ->icon('heroicon-m-user')
                    ->toggleable(),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable()
                    ->icon('heroicon-m-user')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn ($record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Filtrar por estado')
                    ->options(fn (): array => OperationStatusService::query()
                        ->whereNotNull('status')
                        ->orderBy('status')
                        ->pluck('status', 'status')
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
                EditAction::make()->label('Editar'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Eliminar seleccionados'),
                ]),
            ]);
    }
}
