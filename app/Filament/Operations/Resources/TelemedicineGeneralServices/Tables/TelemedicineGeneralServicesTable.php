<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineGeneralServices\Tables;

use App\Filament\Operations\Resources\TelemedicineGeneralServices\Actions\DeleteTelemedicineGeneralServiceAction;
use App\Models\TelemedicineGeneralService;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TelemedicineGeneralServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Servicios de Consulta General')
            ->description('Catálogo usado cuando el médico selecciona CONSULTA GENERAL en la consulta inicial.')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->placeholder('—')
                    ->wrap()
                    ->lineClamp(2)
                    ->toggleable(),
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
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (TelemedicineGeneralService $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (TelemedicineGeneralService $record): string => $record->updated_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'ACTIVO' => 'ACTIVO',
                        'INACTIVO' => 'INACTIVO',
                    ]),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteTelemedicineGeneralServiceAction::make(),
            ])
            ->toolbarActions([
                // Sin bulk delete: la eliminación exige motivo individual en auditoría.
            ]);
    }
}
