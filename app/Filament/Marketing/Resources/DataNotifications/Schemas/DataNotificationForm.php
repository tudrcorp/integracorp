<?php

namespace App\Filament\Marketing\Resources\DataNotifications\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DataNotificationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('event_id')
                    ->required()
                    ->numeric(),
                TextInput::make('fullName')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->required(),
                TextInput::make('message')
                    ->required(),
            ]);
    }
}
