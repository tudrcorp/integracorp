<?php

namespace App\Filament\Operations\Resources\OperationInventoryUbications\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OperationInventoryUbicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Almacén')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nombre'),
                        TextEntry::make('state.definition')
                            ->label('Estado')
                            ->placeholder('—'),
                        TextEntry::make('address')
                            ->label('Dirección')
                            ->placeholder('—'),
                        TextEntry::make('is_active')
                            ->label('Estatus')
                            ->badge()
                            ->formatStateUsing(fn (?bool $state): string => $state ? 'Activo' : 'Inactivo')
                            ->color(fn (?bool $state): string => $state ? 'success' : 'danger'),
                        TextEntry::make('created_by')
                            ->label('Creado por')
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Creado')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('updated_at')
                            ->label('Actualizado')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ])
                    ->columns(2),
            ]);
    }
}
