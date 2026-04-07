<?php

namespace App\Filament\Operations\Resources\OperationTypeServices\Tables;

use App\Models\OperationTypeService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OperationTypeServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Tipos de servicio')
            ->description('Catálogo usado para clasificar servicios en operaciones.')
            ->defaultSort('description')
            ->columns([
                TextColumn::make('description')
                    ->label('Descripción')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (OperationTypeService $record): string => trim((string) $record->description))
                    ->extraCellAttributes(fn (): array => [
                        'class' => 'min-w-48 sm:min-w-64 max-w-xl align-top',
                    ]),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): string => $state !== null && $state !== '' ? $state : '—')
                    ->color(fn (?string $state): string => match (strtoupper(trim((string) ($state ?? '')))) {
                        'ACTIVO' => 'success',
                        'INACTIVO' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (?string $state): string => match (strtoupper(trim((string) ($state ?? '')))) {
                        'ACTIVO' => 'heroicon-m-check-circle',
                        'INACTIVO' => 'heroicon-m-x-circle',
                        default => 'heroicon-m-question-mark-circle',
                    }),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable()
                    ->icon('heroicon-m-user')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable()
                    ->icon('heroicon-m-user')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (OperationTypeService $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (OperationTypeService $record): string => $record->updated_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(fn (): array => OperationTypeService::query()
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
