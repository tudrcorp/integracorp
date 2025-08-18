<?php

namespace App\Filament\Resources\AgeRanges\Schemas;

use App\Models\AgeRange;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class AgeRangeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('RANGO DE EDADES')
                    ->description('Formulario para el registro de los rangos dd edades. Campo Requerido(*)')
                    ->icon('heroicon-s-adjustments-vertical')
                    ->schema([
                        Grid::make(4)->schema([
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
                                ])
                        ])->columnSpanFull(),
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
                TextInput::make('status')
                            ->label('Estatus')
                            ->prefixIcon('heroicon-m-shield-check')
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255)
                            ->default('ACTIVO'),
                        // ->hiddenOn(Pages\EditAgeRange::class),
                        TextInput::make('created_by')
                            ->label('Creado Por:')
                            ->prefixIcon('heroicon-s-user-circle')
                            ->disabled()
                            ->dehydrated()
                            ->default(Auth::user()->name)
                            ->maxLength(255)
                        // ->hiddenOn(Pages\EditAgeRange::class),
                    ])->columnSpanFull()->columns(5),
            ]);
    }
}