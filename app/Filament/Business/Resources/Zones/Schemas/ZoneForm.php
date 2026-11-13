<?php

namespace App\Filament\Business\Resources\Zones\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de la carpeta / zona')
                    ->description('Define el nombre y código. El orden se ajusta arrastrando las carpetas en el listado.')
                    ->icon('heroicon-o-folder')
                    ->schema([
                        Fieldset::make()
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('code')
                                            ->label('Código')
                                            ->placeholder('Ej. METODOS-PAGO')
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText('Identificador corto de la zona')
                                            ->columnSpan(1),
                                        TextInput::make('zone')
                                            ->label('Nombre de la zona')
                                            ->placeholder('Ej. Métodos de pago')
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText('Nombre que verán los usuarios en las pestañas')
                                            ->columnSpan(1),
                                        Select::make('status')
                                            ->label('Estado')
                                            ->options([
                                                'ACTIVA' => 'Activa',
                                                'INACTIVA' => 'Inactiva',
                                            ])
                                            ->default('ACTIVA')
                                            ->required()
                                            ->disabled()
                                            ->dehydrated()
                                            ->native(false)
                                            ->columnSpan(1),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
