<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('agent_id')
                    ->numeric(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
                TextInput::make('code_agency'),
                TextInput::make('agency_type'),
                Toggle::make('is_admin')
                    ->required(),
                Toggle::make('is_agent')
                    ->required(),
                Toggle::make('is_subagent'),
                Toggle::make('is_agency')
                    ->required(),
                Textarea::make('link_agency')
                    ->columnSpanFull(),
                TextInput::make('code_agent'),
                Textarea::make('link_agent')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->default('ACTIVO'),
                TextInput::make('phone')
                    ->tel(),
            ]);
    }
}
