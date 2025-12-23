<?php

namespace App\Filament\Operations\Resources\TelemedicineDoctors\Schemas;

use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use App\Http\Controllers\UtilsController;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Utilities\Get;

class TelemedicineDoctorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informacion Personal del Doctor')
                    ->description('...')
                    ->schema([
                        TextInput::make('full_name')
                            ->label('Nombre y Apellido')
                            ->required(),
                        TextInput::make('nro_identificacion')
                            ->label('Numero de Identificación')
                            ->required(),
                        Select::make('country_code')
                            ->label('Código de país')
                            ->options(fn() => UtilsController::getCountries())
                            ->searchable()
                            ->default('+58')
                            ->live(onBlur: true)
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ])
                            ->hiddenOn('edit'),
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
                            ->required(),
                        TextInput::make('address')
                            ->label('Direccion'),
                        TextInput::make('code_cm')
                            ->regex('/^[0-9]+$/')
                            ->validationMessages([
                                'regex' => 'El campo solo debe contener números (0-9), sin espacios, letras ni caracteres especiales.',
                                'required'  => 'Campo Requerido',
                            ])
                            ->label('Codigo CM')
                            ->required(),
                        TextInput::make('code_mpps')
                            ->regex('/^[0-9]+$/')
                            ->validationMessages([
                                'regex' => 'El campo solo debe contener números (0-9), sin espacios, letras ni caracteres especiales.',
                                'required'  => 'Campo Requerido',
                            ])
                            ->label('Codigo MPPS')
                            ->required(),
                        TextInput::make('specialty')
                            ->required()
                            ->default('MÉDICO GENERAL'),
                            Grid::make()->schema([
                                FileUpload::make('signature')
                                    ->label('Sello Digital')
                                    ->directory('firmas-medicos')
                                    ->image(),
                                
                            ])->columnSpanFull()->columns(4),
                        
                        Hidden::make('created_by')->default(Auth::user()->name),
                        Hidden::make('updated_by')->default(Auth::user()->name)->hiddenOn('create'),
                        
                    ])->columnSpanFull()->columns(4),
            ]);
    }
}