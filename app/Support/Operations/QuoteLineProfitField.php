<?php

declare(strict_types=1);

namespace App\Support\Operations;

use Filament\Forms\Components\TextInput;

/**
 * % de utilidad de un ítem de la cotización. Vacío = sin utilidad (se cobra al costo).
 * Viene precargado con el % del catálogo o de la categoría del inventario cuando existe.
 */
final class QuoteLineProfitField
{
    public static function make(): TextInput
    {
        return TextInput::make('profit_percentage')
            ->label('% de utilidad')
            ->suffix('%')
            ->numeric()
            ->minValue(0)
            ->maxValue(QuoteLinePricing::MAX_PERCENTAGE)
            ->step(0.01)
            ->placeholder('Sin utilidad')
            ->nullable()
            ->live(debounce: 400)
            ->helperText('Déjelo vacío si el ítem no lleva utilidad.')
            ->validationMessages([
                'max' => 'El % de utilidad no puede pasar de '.QuoteLinePricing::MAX_PERCENTAGE.'.',
                'min' => 'El % de utilidad no puede ser negativo.',
            ]);
    }
}
