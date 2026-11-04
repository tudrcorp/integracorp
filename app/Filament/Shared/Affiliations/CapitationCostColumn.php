<?php

declare(strict_types=1);

namespace App\Filament\Shared\Affiliations;

use App\Support\Business\CapitationPlans\CapitationCostResult;
use Closure;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use WeakMap;

/**
 * Columna «Capitado por afiliado» de las tablas de afiliados (individuales, corporativos y
 * Nuevos Negocios) en Operaciones y Negocios.
 *
 * El resultado se calcula una sola vez por fila (estado, descripción y tooltip lo comparten)
 * y un fallo inesperado nunca rompe la tabla: se muestra 0,00 con el aviso.
 */
final class CapitationCostColumn
{
    public const NAME = 'capitation_cost_per_affiliate';

    /**
     * Acción masiva para la barra de seleccionados de la tabla.
     */
    public static function bulkAction(): \Filament\Actions\BulkAction
    {
        return CapitationOverrideActions::bulk();
    }

    /**
     * @param  Closure(Model, mixed): CapitationCostResult  $resolver  recibe el registro y el componente Livewire
     */
    public static function make(Closure $resolver): TextColumn
    {
        /** @var WeakMap<Model, CapitationCostResult> $cache */
        $cache = new WeakMap;

        $result = static function (Model $record, mixed $livewire) use ($resolver, $cache): CapitationCostResult {
            if (! isset($cache[$record])) {
                try {
                    $cache[$record] = $resolver($record, $livewire);
                } catch (Throwable $exception) {
                    report($exception);
                    $cache[$record] = CapitationCostResult::none('No se pudo calcular; avise a sistemas');
                }
            }

            return $cache[$record];
        };

        return TextColumn::make(self::NAME)
            ->label('Capitado por afiliado')
            ->state(fn (Model $record, mixed $livewire): float => $result($record, $livewire)->amount)
            ->money('USD')
            ->alignEnd()
            ->color(fn (Model $record, mixed $livewire): ?string => match (true) {
                $result($record, $livewire)->isOverridden() => 'warning',
                $result($record, $livewire)->applies() => null,
                default => 'gray',
            })
            ->icon(fn (Model $record, mixed $livewire): ?Heroicon => $result($record, $livewire)->isOverridden() ? Heroicon::OutlinedPencilSquare : null)
            ->iconPosition(IconPosition::After)
            ->action(CapitationOverrideActions::cell($result))
            ->description(fn (Model $record, mixed $livewire): string => $result($record, $livewire)->summary())
            ->tooltip(fn (Model $record, mixed $livewire): string => $result($record, $livewire)->isOverridden()
                ? 'Monto negociado para este afiliado. Pulse para ver el historial o volver al monto del plan.'
                : 'Costo por afiliado del plan de capitado del proveedor Outsourcing de la afiliación, tal cual está configurado. Es 0,00 si el afiliado no está activo, no tiene proveedor Outsourcing o el proveedor no tiene plan vigente para su plan.')
            ->toggleable();
    }
}
