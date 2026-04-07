<?php

namespace App\Filament\Operations\Resources\OperationOnCallUsers\Tables;

use App\Models\OperationOnCallUser;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OperationOnCallUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Roles de guardia')
            ->description('Personal asignado a turnos de guardia, contacto y estado del turno.')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre y apellido')
                    ->icon('heroicon-o-user')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (OperationOnCallUser $record): string => trim((string) $record->name))
                    ->copyable()
                    ->copyMessage('Nombre copiado')
                    ->extraCellAttributes(fn (): array => [
                        'class' => 'min-w-48 sm:min-w-60 max-w-md align-top',
                    ]),
                TextColumn::make('date_OnCall')
                    ->label('Fecha de guardia')
                    ->icon('heroicon-m-calendar-days')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('hrs_init')
                    ->label('Horario')
                    ->icon('heroicon-m-clock')
                    ->formatStateUsing(fn (?string $state, OperationOnCallUser $record): string => trim(
                        ($record->hrs_init ?? '').' – '.($record->hrs_end ?? '')
                    ))
                    ->placeholder('—')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): string => $state !== null && $state !== '' ? $state : '—')
                    ->color(fn (?string $state): string => match (strtoupper(trim((string) ($state ?? '')))) {
                        'DE GUARDIA' => 'success',
                        'PROGRAMADA' => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn (?string $state): string => match (strtoupper(trim((string) ($state ?? '')))) {
                        'DE GUARDIA' => 'heroicon-m-check-circle',
                        'PROGRAMADA' => 'heroicon-m-clock',
                        default => 'heroicon-m-question-mark-circle',
                    }),
                TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->icon('heroicon-o-envelope')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->wrap()
                    ->lineClamp(1)
                    ->tooltip(fn (OperationOnCallUser $record): string => trim((string) $record->email))
                    ->extraCellAttributes(fn (): array => [
                        'class' => 'max-w-xs sm:max-w-sm align-top',
                    ]),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon('heroicon-o-phone')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->wrap()
                    ->lineClamp(1)
                    ->tooltip(fn (OperationOnCallUser $record): string => trim((string) $record->phone))
                    ->extraCellAttributes(fn (): array => [
                        'class' => 'max-w-[12rem] align-top',
                    ]),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable()
                    ->icon('heroicon-m-user')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable()
                    ->icon('heroicon-m-user')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (OperationOnCallUser $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (OperationOnCallUser $record): string => $record->updated_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options(fn (): array => OperationOnCallUser::query()
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
