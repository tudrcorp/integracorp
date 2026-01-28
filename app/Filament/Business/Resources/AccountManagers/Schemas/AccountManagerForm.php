<?php

namespace App\Filament\Business\Resources\AccountManagers\Schemas;

use App\Models\AccountManager;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
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
                    ->icon('heroicon-s-user-group')
                    ->description('Datos principales del Ejecutivo o Account Manager')
                    ->schema([
                        TextInput::make('full_name')
                            ->label('Nombre Completo')
                            ->required(),
                        TextInput::make('ci')
                            ->label('Cedula de Identidad')
                            ->unique(
                                table: AccountManager::class,
                                column: 'ci'
                            )
                            ->required()
                            // Restringe la entrada de letras, espacios y puntos en tiempo real (Frontend)
                            ->extraAlpineAttributes([
                                'onkeydown' => "if([' ', '.'].includes(event.key) || /^[a-zA-Z]$/.test(event.key)) event.preventDefault()",
                                'onpaste' => "event.preventDefault(); const text = (event.clipboardData || window.clipboardData).getData('text'); event.target.value = text.replace(/[^0-9]/g, '')",
                            ])
                            // Validación de respaldo para permitir solo números (Backend)
                            ->regex('/^[0-9]*$/')
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                                'unique'    => 'Cedula de Identidad ya existe en en la tabla. Por favor intente con otro numero de cedula.',
                                'regex'     => 'La cédula solo debe contener números (sin letras, espacios ni puntos).',
                            ]),
                        DatePicker::make('birth_date')
                            ->label('Fecha de Nacimiento')
                            ->required()
                            ->format('d/m/Y')
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ]),
                        Select::make('country_code')
                            ->label('Código de país')
                            ->options(fn() => UtilsController::getCountries())
                            ->hiddenOn('edit')
                            ->default('+58')
                            ->live(onBlur: true),
                        TextInput::make('phone')
                            ->prefixIcon('heroicon-s-phone')
                            ->tel()
                            ->label('Número de teléfono')
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
                            ->unique(
                                table: AccountManager::class,
                                column: 'email'
                            )
                            ->required(),
                        TextInput::make('address')
                            ->label('Direccion'),
                    ])->columnSpanFull()->columns(3),
                    Hidden::make('created_by')->default(auth()->user()->name),
                    Hidden::make('updated_by'),
                ]);
    }
}