<?php

declare(strict_types=1);

namespace App\Support\Rrhh;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

final class RrhhColaboradorConceptoForm
{
    /**
     * @return array<int, mixed>
     */
    public static function components(string $nombreLabel, string $descripcionHelper): array
    {
        return [
            TextInput::make('name')
                ->label($nombreLabel)
                ->required()
                ->maxLength(255)
                ->prefixIcon('heroicon-m-tag')
                ->columnSpanFull(),
            Textarea::make('description')
                ->label('Descripción')
                ->required()
                ->rows(3)
                ->helperText($descripcionHelper)
                ->columnSpanFull(),
            ToggleButtons::make('tipo_valor')
                ->label('¿Cómo se calcula?')
                ->options(RrhhValorCalculo::tipoOptions())
                ->icons([
                    RrhhValorCalculo::TIPO_MONTO => Heroicon::OutlinedBanknotes,
                    RrhhValorCalculo::TIPO_PORCENTAJE => Heroicon::OutlinedReceiptPercent,
                ])
                ->inline()
                ->live()
                ->default(RrhhValorCalculo::TIPO_MONTO)
                ->required()
                ->afterStateUpdated(function (mixed $state, Set $set): void {
                    if ($state === RrhhValorCalculo::TIPO_MONTO) {
                        $set('porcentaje', null);
                    }

                    if ($state === RrhhValorCalculo::TIPO_PORCENTAJE) {
                        $set('monto', null);
                    }
                })
                ->helperText('Monto fijo sobre el sueldo total, o porcentaje sobre el sueldo base.')
                ->columnSpanFull(),
            TextInput::make('monto')
                ->label('Monto fijo')
                ->numeric()
                ->minValue(0)
                ->step(0.01)
                ->prefix('US$')
                ->placeholder('0.00')
                ->visible(fn (Get $get): bool => $get('tipo_valor') === RrhhValorCalculo::TIPO_MONTO)
                ->required(fn (Get $get): bool => $get('tipo_valor') === RrhhValorCalculo::TIPO_MONTO)
                ->columnSpanFull(),
            TextInput::make('porcentaje')
                ->label('Porcentaje sobre sueldo base')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->step(0.01)
                ->suffix('%')
                ->placeholder('0.00')
                ->prefixIcon('heroicon-m-receipt-percent')
                ->visible(fn (Get $get): bool => $get('tipo_valor') === RrhhValorCalculo::TIPO_PORCENTAJE)
                ->required(fn (Get $get): bool => $get('tipo_valor') === RrhhValorCalculo::TIPO_PORCENTAJE)
                ->columnSpanFull(),
        ];
    }
}
