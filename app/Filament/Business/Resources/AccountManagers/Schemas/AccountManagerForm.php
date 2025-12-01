<?php

namespace App\Filament\Business\Resources\AccountManagers\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use App\Http\Controllers\UtilsController;
use Filament\Schemas\Components\Utilities\Get;

class AccountManagerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informacion Principal')
                    ->schema([
                        TextInput::make('full_name')
                            ->label('Nombre Completo')
                            ->required(),
                        Select::make('country_code')
                            ->label('Código de país')
                            ->options(fn() => UtilsController::getCountries())
                            ->hiddenOn('edit')
                            ->required()
                            ->default('+58')
                            ->live(onBlur: true),
                        TextInput::make('phone')
                            ->prefixIcon('heroicon-s-phone')
                            ->tel()
                            ->label('Número de teléfono')
                            ->required()
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ])
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                $countryCode = $get('country_code');
                                if ($countryCode) {
                                    $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                    $set('phone', $countryCode . $cleanNumber);
                                }
                            }),
                        TextInput::make('email')
                            ->label('Correo Electronico')
                            ->email()
                            ->required(),
                        TextInput::make('address')
                            ->columnSpan(2)
                            ->label('Direccion'),
                    ])->columnSpanFull()->columns(4),
                    Hidden::make('created_by')->default(auth()->user()->name),
                    Hidden::make('updated_by'),
                ]);
    }
}