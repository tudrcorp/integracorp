<?php

namespace App\Filament\Operations\Resources\OperationInventoryEntries\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OperationInventoryEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('operation_inventory_id')
                    ->required()
                    ->numeric(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric(),
                TextInput::make('unit')
                    ->required(),
                TextInput::make('type')
                    ->required(),
                TextInput::make('created_by')
                    ->required(),
            ]);
    }
}
