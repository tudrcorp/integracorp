<?php

namespace App\Filament\Operations\Resources\TelemedicineDoctors\Schemas;

use App\Http\Controllers\UtilsController;
use App\Models\TelemedicineDoctor;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

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
                            ->unique(
                                table: TelemedicineDoctor::class,
                                column: 'nro_identificacion',
                            )
                            ->required()
                            ->numeric()
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                                'unique' => 'El número de identificación ya existe',
                            ]),
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
                            ->unique(table: TelemedicineDoctor::class, column: 'email')
                            ->required()
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                                'unique' => 'El correo electrónico ya existe',
                            ]),
                        TextInput::make('address')
                            ->label('Direccion'),
                        TextInput::make('code_cm')
                            ->regex('/^[0-9]+$/')
                            ->validationMessages([
                                'regex' => 'El campo solo debe contener números (0-9), sin espacios, letras ni caracteres especiales.',
                                'required'  => 'Campo Requerido',
                            ])
                            ->label('Codigo CM')
                            ->unique(
                                table: TelemedicineDoctor::class,
                                column: 'code_cm',
                            )
                            ->required()
                            ->numeric()
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                                'unique' => 'El código CM ya existe',
                                'numeric' => 'El campo solo debe contener números',
                            ]),
                        TextInput::make('code_mpps')
                            ->regex('/^[0-9]+$/')
                            ->validationMessages([
                                'regex' => 'El campo solo debe contener números (0-9), sin espacios, letras ni caracteres especiales.',
                                'required'  => 'Campo Requerido',
                            ])
                            ->label('Codigo MPPS')
                            ->unique(
                                table: TelemedicineDoctor::class,
                                column: 'code_mpps',
                            )
                            ->required()
                            ->numeric()
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                                'unique' => 'El código MPPS ya existe',
                                'numeric' => 'El campo solo debe contener números',
                            ]),
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