<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Actions;

use App\Support\PlanGenerators\PlanGeneratorRateAdjustment;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * Ajuste global de tarifas de la matriz: un porcentaje de aumento o de descuento
 * sobre las tarifas de las columnas elegidas.
 *
 * El porcentaje es interno. La cotización —PDF y vista previa— solo muestra la
 * tarifa ya ajustada, así que el cliente nunca lo ve.
 *
 * Se registra en el componente del editor de matrices (`registerActions()`) para
 * que el botón se pinte dentro de la propia tabla de tarifas. Por eso `Get` y
 * `Set` del `action()` resuelven contra el formulario que contiene el editor,
 * sirviendo igual al formulario de Crear/Editar y a la modal que deriva una
 * cotización desde la tabla.
 */
final class AdjustRateAmountsAction
{
    public const NAME = 'adjustRateAmounts';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label('Ajustar tarifas %')
            ->icon(Heroicon::OutlinedReceiptPercent)
            ->color('warning')
            ->button()
            ->size('sm')
            ->modalWidth(Width::Large)
            ->modalHeading('Ajuste global de tarifas')
            ->modalDescription('Aumenta o descuenta todas las tarifas de las columnas elegidas. El porcentaje es interno: la cotización muestra solo la tarifa ya ajustada.')
            ->modalSubmitActionLabel('Aplicar ajuste')
            ->modalCancelActionLabel('Cancelar')
            ->disabled(fn (Get $schemaGet): bool => PlanGeneratorRateAdjustment::columnOptions(
                (array) ($schemaGet('columns') ?? []),
            ) === [])
            // Las columnas viajan dentro del estado del modal: adentro, `Get`
            // resuelve contra el schema del modal y no contra el formulario.
            ->fillForm(function (Get $schemaGet): array {
                $columns = (array) ($schemaGet('columns') ?? []);
                $options = PlanGeneratorRateAdjustment::columnOptions($columns);

                return [
                    'column_options' => $options,
                    'column_keys' => array_keys($options),
                    'percent' => null,
                ];
            })
            ->schema([
                Hidden::make('column_options')->default([]),
                TextInput::make('percent')
                    ->label('Porcentaje a aplicar')
                    ->numeric()
                    ->required()
                    ->suffix('%')
                    ->minValue(PlanGeneratorRateAdjustment::MIN_PERCENT)
                    ->maxValue(PlanGeneratorRateAdjustment::MAX_PERCENT)
                    ->step(0.01)
                    ->placeholder('Ej: -10 para descontar, 7.5 para aumentar')
                    ->helperText('Positivo aumenta la tarifa, negativo la descuenta. Escriba 0 para restaurar la tarifa original.'),
                CheckboxList::make('column_keys')
                    ->label('Columnas a ajustar')
                    ->options(fn (Get $get): array => (array) ($get('column_options') ?? []))
                    ->bulkToggleable()
                    ->required()
                    ->columns(['default' => 1, 'sm' => 2])
                    ->helperText('El ajuste se aplica a todos los rangos etarios de cada columna marcada.'),
            ])
            ->action(function (array $data, Get $schemaGet, Set $schemaSet): void {
                $result = PlanGeneratorRateAdjustment::apply(
                    (array) ($schemaGet('columns') ?? []),
                    (array) ($schemaGet('rate_rows') ?? []),
                    (float) ($data['percent'] ?? 0),
                    array_values(array_filter(
                        (array) ($data['column_keys'] ?? []),
                        static fn (mixed $key): bool => filled($key),
                    )),
                );

                $schemaSet('columns', $result['columns']);
                $schemaSet('rate_rows', $result['rate_rows']);

                $percent = PlanGeneratorRateAdjustment::clampPercent((float) ($data['percent'] ?? 0));

                if ($result['adjusted_cells'] === 0) {
                    Notification::make()
                        ->title('No había tarifas que ajustar')
                        ->body('Las columnas marcadas no tienen tarifas cargadas en ningún rango etario.')
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title($percent === 0.0 ? 'Tarifas restauradas' : 'Ajuste aplicado')
                    ->body($percent === 0.0
                        ? sprintf(
                            'Se restauró la tarifa original de %d tarifa(s) en %d columna(s).',
                            $result['adjusted_cells'],
                            $result['adjusted_columns'],
                        )
                        : sprintf(
                            'Se aplicó %s a %d tarifa(s) en %d columna(s). El porcentaje no aparece en la cotización.',
                            PlanGeneratorRateAdjustment::formatPercent($percent),
                            $result['adjusted_cells'],
                            $result['adjusted_columns'],
                        ))
                    ->success()
                    ->send();
            });
    }
}
