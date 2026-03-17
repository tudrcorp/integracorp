<?php

namespace App\Filament\Business\Resources\Zones\Schemas;

use App\Models\Zone;
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
                    ->description('Define el nombre, código y orden. La posición determina el orden de las pestañas en Zona de descarga.')
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
                                        TextInput::make('position')
                                            ->label('Posición')
                                            ->numeric()
                                            ->required()
                                            ->minValue(1)
                                            ->default(1)
                                            ->unique(table: Zone::class, column: 'position')
                                            ->helperText('Orden de izquierda a derecha en las pestañas. Menor número = más a la izquierda. No puede repetirse.')
                                            ->validationMessages([
                                                'unique' => 'Esa posición ya está asignada a otra zona. Elija otro número.',
                                            ])
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
