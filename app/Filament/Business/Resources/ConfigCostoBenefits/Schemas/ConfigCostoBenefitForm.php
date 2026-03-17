<?php

namespace App\Filament\Business\Resources\ConfigCostoBenefits\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConfigCostoBenefitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Configuración de costos por beneficio')
                    ->description('Define los porcentajes aplicados al PVP para calcular comisión, utilidad y acumulado adicional. Estos valores se usan al editar la estructura de costos en la tabla de beneficios.')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('porcen_comision')
                                    ->label('% Comisión')
                                    ->prefixIcon('heroicon-m-currency-dollar')
                                    ->suffix('%')
                                    ->placeholder('0.00')
                                    ->helperText('Porcentaje del PVP que corresponde a comisión.')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->required(),
                                TextInput::make('porcen_utilidad')
                                    ->label('% Utilidad')
                                    ->prefixIcon('heroicon-m-chart-bar')
                                    ->suffix('%')
                                    ->placeholder('0.00')
                                    ->helperText('Porcentaje del PVP que corresponde a utilidad.')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->required(),
                                TextInput::make('porcen_acu_adi')
                                    ->label('% Acumulado adicional')
                                    ->prefixIcon('heroicon-m-banknotes')
                                    ->suffix('%')
                                    ->placeholder('0.00')
                                    ->helperText('Porcentaje del PVP para acumulado adicional.')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->required(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
