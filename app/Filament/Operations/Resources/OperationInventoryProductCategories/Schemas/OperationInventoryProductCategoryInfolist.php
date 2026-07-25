<?php

namespace App\Filament\Operations\Resources\OperationInventoryProductCategories\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OperationInventoryProductCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Categoría')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nombre'),
                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        IconEntry::make('is_active')
                            ->label('Activa')
                            ->boolean(),
                        TextEntry::make('products_count')
                            ->label('Productos')
                            ->state(fn ($record): int => $record->products()->count()),
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
