<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\PortalHelpContacts\Tables;

use App\Filament\Operations\Resources\PortalHelpContacts\Actions\DeletePortalHelpContactAction;
use App\Models\PortalHelpContact;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PortalHelpContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Contactos de ayuda del portal del paciente')
            ->description('Teléfonos publicados en la vista Ayuda del portal para que el paciente solicite soporte por WhatsApp.')
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable()
                    ->alignCenter()
                    ->width('5rem'),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->icon('heroicon-o-user')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon('heroicon-o-phone')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado'),
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
                    ->description(fn (PortalHelpContact $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (PortalHelpContact $record): string => $record->updated_at?->diffForHumans() ?? '')
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
                DeletePortalHelpContactAction::make(),
            ])
            ->toolbarActions([
                // Sin bulk delete: la eliminación exige motivo individual en auditoría.
            ]);
    }
}
