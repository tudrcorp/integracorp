<?php

namespace App\Filament\Resources\TelemedicineDoctors\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TelemedicineDoctorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')
                    ->required(),
                TextInput::make('last_name')
                    ->required(),
                TextInput::make('nro_identificacion')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('code_cm'),
                TextInput::make('code_mpps'),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('specialty')
                    ->required()
                    ->default('MÉDICO GENERAL'),
                TextInput::make('address'),
                FileUpload::make('image')
                    ->image(),
                TextInput::make('signature'),
            ]);
    }
}
