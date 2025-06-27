<?php

namespace App\Filament\Resources\Logs\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class LogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('action')
                    ->required(),
                Textarea::make('response')
                    ->columnSpanFull(),
                TextInput::make('method'),
                TextInput::make('route'),
                TextInput::make('ip'),
                TextInput::make('user_agent'),
            ]);
    }
}
