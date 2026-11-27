<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use Closure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\HtmlString;

/**
 * «Precios unitarios por ítem» en formato tabla: una fila por ítem con cantidad (solo
 * medicamentos, la escribe el analista), precio unitario del proveedor, % de utilidad, precio en
 * Bs. y el total de la línea. La usan la gestión de ítems y la edición de la cotización.
 */
final class QuoteLineItemsTable
{
    /**
     * @param  string  $bcvRatePath  ruta de la tasa BCV vista desde una fila (p. ej. `../../manage_quote_bcv_rate`)
     * @param  (Closure(Get, Set): void)|null  $afterLineChanged  se ejecuta al cambiar cantidad o precio
     */
    public static function make(string $name, string $bcvRatePath, ?Closure $afterLineChanged = null): Repeater
    {
        $changed = function (Get $get, Set $set) use ($afterLineChanged): void {
            if ($afterLineChanged !== null) {
                $afterLineChanged($get, $set);
            }
        };

        return Repeater::make($name)
            ->label('Precios unitarios por ítem')
            ->helperText('La cantidad solo aplica a medicamentos y la indica el analista; los demás ítems cuentan como 1. Deje el % de utilidad vacío si el ítem no lleva utilidad.')
            ->addable(false)
            ->deletable(false)
            ->reorderable(false)
            ->table([
                TableColumn::make('Ítem'),
                TableColumn::make('Cantidad')->width('8rem'),
                TableColumn::make('Precio unitario (US$)')->width('11rem')->markAsRequired(),
                TableColumn::make('% de utilidad')->width('9rem'),
                TableColumn::make('Precio unitario (Bs.)')->width('11rem'),
                TableColumn::make('Total de la línea')->width('10rem')->alignment(Alignment::End),
            ])
            ->schema([
                Hidden::make('key')
                    ->dehydrated(),
                Hidden::make('category')
                    ->dehydrated(),
                TextInput::make('label')
                    ->label('Ítem')
                    ->disabled()
                    ->dehydrated(),
                TextInput::make(QuoteLinePricing::QUANTITY_KEY)
                    ->label('Cantidad')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(QuoteLinePricing::MAX_QUANTITY)
                    ->step(1)
                    ->placeholder(fn (Get $get): string => self::rowIsMedication($get) ? 'Ej. 2' : '1')
                    ->required(fn (Get $get): bool => self::rowIsMedication($get))
                    ->disabled(fn (Get $get): bool => ! self::rowIsMedication($get))
                    ->dehydrated(fn (Get $get): bool => self::rowIsMedication($get))
                    ->live(debounce: 400)
                    ->afterStateUpdated($changed)
                    ->validationMessages([
                        'required' => 'Indique cuántas unidades de este medicamento se cotizan.',
                        'integer' => 'La cantidad debe ser un número entero.',
                        'min' => 'La cantidad debe ser al menos 1.',
                        'max' => 'La cantidad no puede pasar de '.QuoteLinePricing::MAX_QUANTITY.'.',
                    ]),
                TextInput::make('unit_price_usd')
                    ->label('Precio unitario del proveedor (US$)')
                    ->prefix('US$')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->live(debounce: 400)
                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state) use ($bcvRatePath, $changed): void {
                        $rate = OperationCoordinationServicesTable::decimalOrNull($get($bcvRatePath));
                        $usd = OperationCoordinationServicesTable::decimalOrNull($state);
                        $set('unit_price_ves', ($rate !== null && $usd !== null) ? round($usd * $rate, 2) : null);
                        $changed($get, $set);
                    }),
                QuoteLineProfitField::make()
                    ->helperText(null),
                TextInput::make('unit_price_ves')
                    ->label('Precio unitario del proveedor (Bs.)')
                    ->prefix('Bs.')
                    ->numeric()
                    ->readOnly()
                    ->dehydrated()
                    ->extraAttributes(['class' => 'fi-manage-quote-readonly-field']),
                Placeholder::make('line_total')
                    ->label('Total de la línea')
                    ->content(fn (Get $get): HtmlString => self::lineTotalCell($get)),
            ])
            ->extraAttributes(['class' => 'fi-quote-line-items-table'])
            ->columnSpanFull();
    }

    private static function rowIsMedication(Get $get): bool
    {
        return QuoteLinePricing::quantityApplies($get('key'));
    }

    /**
     * Precio de venta de la línea y, debajo, su costo (unitario × cantidad).
     */
    private static function lineTotalCell(Get $get): HtmlString
    {
        $line = [
            'key' => $get('key'),
            'unit_price_usd' => $get('unit_price_usd'),
            'profit_percentage' => $get('profit_percentage'),
            QuoteLinePricing::QUANTITY_KEY => self::rowIsMedication($get) ? $get(QuoteLinePricing::QUANTITY_KEY) : 1,
        ];

        $price = QuoteLinePricing::lineSalePrice($line);
        $cost = QuoteLinePricing::lineCost($line);

        if ($price === null || $cost === null) {
            $missing = QuoteLinePricing::quantity($line) === null ? 'Falta la cantidad' : 'Falta el precio';

            return new HtmlString('<span class="fi-quote-line-total-pending">'.e($missing).'</span>');
        }

        return new HtmlString(
            '<div class="fi-quote-line-total">'
            .'<span class="fi-quote-line-total-price">US$ '.e(number_format($price, 2, ',', '.')).'</span>'
            .'<span class="fi-quote-line-total-cost">Costo US$ '.e(number_format($cost, 2, ',', '.')).'</span>'
            .'</div>'
        );
    }
}
