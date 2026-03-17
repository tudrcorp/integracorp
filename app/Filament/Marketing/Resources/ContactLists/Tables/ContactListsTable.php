<?php

namespace App\Filament\Marketing\Resources\ContactLists\Tables;

use App\Models\ContactList;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactListsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Contactos')
            ->description('Lista de contactos por grupo. El color de cada fila corresponde al color asignado al grupo.')
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->columns([
                TextColumn::make('full_name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->icon('heroicon-o-user'),
                TextColumn::make('group')
                    ->label('Grupo')
                    ->badge()
                    ->color(fn (ContactList $record): string => self::badgeColorForGroupColor($record->getAttribute('group_color')))
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-tag'),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->icon('heroicon-o-envelope')
                    ->toggleable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->icon('heroicon-o-phone'),
                TextColumn::make('owner__full_name')
                    ->label('Propietario')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-user-circle')
                    ->toggleable(),
                TextColumn::make('owner_phone')
                    ->label('Tel. propietario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('owner_email')
                    ->label('Correo propietario')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match (strtoupper((string) $state)) {
                        'ACTIVO' => 'success',
                        'INACTIVO' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst(mb_strtolower($state)))
                    ->sortable()
                    ->toggleable(),
                ColorColumn::make('group_color')
                    ->label('Color')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordClasses(fn (ContactList $record): array => [
                self::rowClassForGroupColor($record->getAttribute('group_color')),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton(),
                EditAction::make()
                    ->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function rowClassForGroupColor(mixed $groupColor): string
    {
        if (empty($groupColor)) {
            return 'bg-gray-500/5 dark:bg-gray-500/15 border-l-4 border-gray-400 dark:border-gray-500';
        }

        $color = (string) $groupColor;

        if (str_starts_with($color, '#')) {
            $hex = substr($color, 1);
            if (ctype_xdigit($hex) && in_array(strlen($hex), [3, 6], true)) {
                return '';
            }
        }

        return match ($color) {
            'primary' => 'bg-primary-500/10 dark:bg-primary-500/20 border-l-4 border-primary-500',
            'success' => 'bg-green-500/10 dark:bg-green-500/20 border-l-4 border-green-500',
            'warning' => 'bg-amber-500/10 dark:bg-amber-500/20 border-l-4 border-amber-500',
            'danger' => 'bg-red-500/10 dark:bg-red-500/20 border-l-4 border-red-500',
            'info' => 'bg-sky-500/10 dark:bg-sky-500/20 border-l-4 border-sky-500',
            'gray' => 'bg-gray-500/5 dark:bg-gray-500/15 border-l-4 border-gray-400 dark:border-gray-500',
            default => 'bg-gray-500/5 dark:bg-gray-500/15 border-l-4 border-gray-400 dark:border-gray-500',
        };
    }

    private static function badgeColorForGroupColor(mixed $groupColor): string
    {
        if (empty($groupColor) || str_starts_with((string) $groupColor, '#')) {
            return 'gray';
        }

        $color = (string) $groupColor;

        return in_array($color, ['primary', 'success', 'warning', 'danger', 'info', 'gray'], true)
            ? $color
            : 'gray';
    }
}
