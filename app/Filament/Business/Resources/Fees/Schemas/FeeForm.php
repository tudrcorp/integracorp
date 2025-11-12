<?php

namespace App\Filament\Business\Resources\Fees\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Models\Fee;
use App\Models\AgeRange;
use App\Models\Coverage;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

class FeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
        ->components([
                Section::make('ESTRUCTURA DE PLANES, EDADES Y COBERTURAS')
                    ->description('Este formulario funciona como un configurar de planes. Puede Crear Rango de edades y Coberturas asociadas a un plan creado previamente. Campo Requerido(*)')
                    ->icon('heroicon-m-book-open')
                    ->schema([
                        Grid::make()->schema([
                            TextInput::make('code')
                                ->label('Código')
                                ->prefixIcon('heroicon-m-clipboard-document-check')
                                ->default(function () {
                                    if (Fee::max('id') == null) {
                                        $parte_entera = 0;
                                    } else {
                                        $parte_entera = Fee::max('id');
                                    }
                                    return 'TDEC-FA-000' . $parte_entera + 1;
                                })
                                ->required()
                                ->disabled()
                                ->dehydrated()
                                ->maxLength(255),
                        ])->columnSpanFull()->columns(4),
                        Select::make('age_range_id')
                            ->label('Rango de edad')
                            ->relationship('ageRange', 'range')
                            ->createOptionForm([
                                Section::make('RANGO DE EDADES')
                                    ->description('Formulario para el registro de los rangos dd edades. Campo Requerido(*)')
                                    ->icon('heroicon-s-adjustments-vertical')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextInput::make('code')
                                                ->label('Código')
                                                ->prefixIcon('heroicon-m-clipboard-document-check')
                                                ->default(function () {
                                                    if (AgeRange::max('id') == null) {
                                                        $parte_entera = 0;
                                                    } else {
                                                        $parte_entera = AgeRange::max('id');
                                                    }
                                                    return 'TDEC-RE-000' . $parte_entera + 1;
                                                })
                                                ->required()
                                                ->disabled()
                                                ->dehydrated()
                                                ->maxLength(255),
                                            Select::make('plan_id')
                                                ->relationship('plan', 'description')
                                                ->label('Plan')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Campo requerido',
                                                ]),
                                            TextInput::make('range')
                                                ->label('Rango de edad')
                                                ->helperText('Debe colocar la descripción del rango de edad en texto plano. Ej: 1 a 10, 25 a 35, 45 a 80.')
                                                ->prefixIcon('heroicon-m-shield-check')
                                                ->maxLength(255),
                                            TextInput::make('age_init')
                                                ->label('Edad de Inicio')
                                                ->numeric()
                                                ->validationMessages([
                                                    'required' => 'Campo requerido',
                                                    'numeric'  => 'Debe ser un número entero',
                                                    'regex'    => 'Rango de edad inválido',
                                                ])
                                                ->regex('/^\d{1,3}$/')
                                                ->prefixIcon('heroicon-m-shield-check')
                                                ->helperText('Edad Inicio: Rango de edad en años. Debe ser un número entero.')
                                                ->maxLength(255),
                                            TextInput::make('age_end')
                                                ->label('Edad Fin')
                                                ->helperText('Edad Fin: Rango de edad en años. Debe ser un número entero.')
                                                ->numeric()
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Campo requerido',
                                                    'numeric'  => 'Debe ser un número entero',
                                                    'regex'    => 'Rango de edad inválido',
                                                ])
                                                ->regex('/^\d{1,3}$/')
                                                ->prefixIcon('heroicon-m-shield-check')
                                                ->maxLength(255),
                                            Hidden::make('status')->default('ACTIVO'),
                                            Hidden::make('created_by')->default(Auth::user()->name)
                                        ])->columnSpanFull(),
                                    ])->columnSpanFull()->columns(3),
                            ])
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('coverage_id')
                            ->label('Covertura')
                            ->relationship('coverage', 'price')
                            ->preload()
                            ->searchable()
                            ->createOptionForm([
                                Section::make('COBERTURA')
                                    ->description('Formulario para el registro de las coberturas asociadas a los beneficios. Campo Requerido(*)')
                                    ->icon('heroicon-s-document-currency-dollar')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextInput::make('code')
                                                ->label('Código')
                                                ->prefixIcon('heroicon-m-clipboard-document-check')
                                                ->default(function () {
                                                    if (Coverage::max('id') == null) {
                                                        $parte_entera = 0;
                                                    } else {
                                                        $parte_entera = Coverage::max('id');
                                                    }
                                                    return 'TDEC-CO-000' . $parte_entera + 1;
                                                })
                                                ->required()
                                                ->disabled()
                                                ->dehydrated()
                                                ->maxLength(255),
                                            TextInput::make('price')
                                                ->label('Precio US$')
                                                ->helperText('Precio de la cobertura en dólares estadounidenses. Utilice separador decimal(.)')
                                                ->prefix('US$')
                                                ->required()
                                                ->numeric()
                                                ->validationMessages([
                                                    'required' => 'El precio debe ser numérico.',
                                                ]),
                                            Select::make('plan_id')
                                                ->relationship('plan', 'description')
                                                ->label('Plan')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Campo requerido',
                                                ]),
                                            Hidden::make('status')->default('ACTIVO'),
                                            Hidden::make('created_by')->default(Auth::user()->name)
                                        ])->columnSpanFull(),
                                        hidden::make('status')->default('ACTIVO'),
                                        hidden::make('created_by')->default(Auth::user()->name)
                                    ])->columnSpanFull()->columns(3),
                            ]),

                        TextInput::make('price')
                            ->label('Precio US$')
                            ->helperText('Precio del beneficio en dólares estadounidenses. Utilice separador decimal(.)')
                            ->required()
                            ->numeric()
                            ->prefix('$'),
                        TextInput::make('status')
                            ->label('Estatus')
                            ->prefixIcon('heroicon-m-shield-check')
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255)
                            ->default('ACTIVO'),
                        TextInput::make('created_by')
                            ->label('Creado Por:')
                            ->prefixIcon('heroicon-s-user-circle')
                            ->disabled()
                            ->dehydrated()
                            ->default(Auth::user()->name)
                            ->maxLength(255),

                    ])->columnSpanFull()->columns(3),
            ]);
    }
}