<?php

namespace App\Filament\Business\Resources\AccountManagers\Schemas;

use App\Http\Controllers\UtilsController;
use App\Models\AccountManager;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class AccountManagerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos personales')
                    ->icon('heroicon-s-user-circle')
                    ->description('Identificación y datos básicos del ejecutivo o account manager.')
                    ->schema([
                        Grid::make(['default' => 1, 'lg' => 3])
                            ->schema([
                                TextInput::make('full_name')
                                    ->label('Nombre completo')
                                    ->prefixIcon('heroicon-m-user')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('ci')
                                    ->label('Cédula de identidad')
                                    ->prefixIcon('heroicon-m-identification')
                                    ->unique(
                                        table: AccountManager::class,
                                        column: 'ci',
                                    )
                                    ->required()
                                    ->extraAlpineAttributes([
                                        'onkeydown' => "if([' ', '.'].includes(event.key) || /^[a-zA-Z]$/.test(event.key)) event.preventDefault()",
                                        'onpaste' => "event.preventDefault(); const text = (event.clipboardData || window.clipboardData).getData('text'); event.target.value = text.replace(/[^0-9]/g, '')",
                                    ])
                                    ->regex('/^[0-9]*$/')
                                    ->validationMessages([
                                        'required' => 'Campo requerido',
                                        'unique' => 'Esta cédula ya está registrada. Usa otro número.',
                                        'regex' => 'La cédula solo debe contener números (sin letras, espacios ni puntos).',
                                    ]),
                                DatePicker::make('birth_date')
                                    ->label('Fecha de nacimiento')
                                    ->required()
                                    ->format('d/m/Y')
                                    ->validationMessages([
                                        'required' => 'Campo requerido',
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Contacto')
                    ->icon('heroicon-s-phone')
                    ->description('Medios de contacto y ubicación.')
                    ->schema([
                        Grid::make(['default' => 1, 'lg' => 2])
                            ->schema([
                                Select::make('country_code')
                                    ->label('Código de país')
                                    ->prefixIcon('heroicon-m-flag')
                                    ->options(fn () => UtilsController::getCountries())
                                    ->hiddenOn('edit')
                                    ->default('+58')
                                    ->live(onBlur: true),
                                TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->prefixIcon('heroicon-s-phone')
                                    ->tel()
                                    ->maxLength(32)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set, Get $get): void {
                                        $countryCode = $get('country_code');
                                        if ($countryCode) {
                                            $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', (string) $state), '0');
                                            $set('phone', $countryCode.$cleanNumber);
                                        }
                                    })
                                    ->validationMessages([
                                        'required' => 'Campo requerido',
                                    ]),
                            ]),
                        TextInput::make('email')
                            ->label('Correo electrónico')
                            ->prefixIcon('heroicon-m-envelope')
                            ->email()
                            ->unique(
                                table: AccountManager::class,
                                column: 'email',
                            )
                            ->required()
                            ->maxLength(255),
                        Textarea::make('address')
                            ->label('Dirección')
                            ->rows(3)
                            ->columnSpanFull()
                            ->maxLength(500),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Hidden::make('created_by')->default(fn (): ?string => Auth::user()?->name),
                Hidden::make('updated_by'),
            ]);
    }
}
