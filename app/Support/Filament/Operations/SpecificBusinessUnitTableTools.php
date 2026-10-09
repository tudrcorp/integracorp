<?php

declare(strict_types=1);

namespace App\Support\Filament\Operations;

use Closure;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Columna y filtro «Unidad de negocio específica» de las tablas de Operaciones
 * (Cuentas por pagar, Cotizaciones por pagar).
 *
 * El dato es `telemedicine_patients.specific_business_unit` del paciente de la
 * coordinación, el mismo que muestra Órdenes de servicio: cada tabla indica la
 * ruta de relaciones hasta ese paciente y de dónde salen las opciones.
 */
final class SpecificBusinessUnitTableTools
{
    /** Opción del filtro para los registros sin unidad de negocio específica. */
    public const WITHOUT = '__sin_unidad__';

    public const WITHOUT_LABEL = 'Sin unidad específica';

    /**
     * @param  Closure(mixed): string  $state  valor de la fila ('—' si no hay)
     */
    public static function column(Closure $state, string $patientRelation): TextColumn
    {
        return TextColumn::make('specific_business_unit')
            ->label('U.N. específica')
            ->state($state)
            ->badge()
            ->color(fn (string $state): string => $state === '—' ? 'gray' : 'info')
            ->wrap()
            ->limit(36)
            ->tooltip(fn (string $state): ?string => $state !== '—' ? $state : null)
            ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                $patientRelation,
                fn (Builder $patient): Builder => $patient->where('specific_business_unit', 'like', '%'.$search.'%'),
            ))
            ->toggleable();
    }

    /**
     * @param  Closure(): array<string, string>  $options
     */
    public static function filter(Closure $options, string $patientRelation): SelectFilter
    {
        return SelectFilter::make('specific_business_unit')
            ->label('Unidad de negocio específica')
            ->placeholder('Todas')
            ->multiple()
            ->searchable()
            ->options($options)
            ->query(fn (Builder $query, array $data): Builder => self::apply($query, $data['values'] ?? [], $patientRelation));
    }

    /**
     * Opciones del filtro a partir de las unidades encontradas (sin vacíos ni
     * repetidos), más «Sin unidad específica».
     *
     * @param  iterable<mixed>  $units
     * @return array<string, string>
     */
    public static function options(iterable $units): array
    {
        $clean = collect($units)
            ->map(fn (mixed $unit): string => trim((string) $unit))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            ...array_combine($clean, $clean),
            self::WITHOUT => self::WITHOUT_LABEL,
        ];
    }

    /**
     * @param  list<mixed>  $values
     */
    public static function apply(Builder $query, array $values, string $patientRelation): Builder
    {
        $values = array_values(array_filter(array_map(fn (mixed $value): string => trim((string) $value), $values)));

        if ($values === []) {
            return $query;
        }

        $units = array_values(array_diff($values, [self::WITHOUT]));
        $includeWithout = in_array(self::WITHOUT, $values, true);

        return $query->where(function (Builder $query) use ($units, $includeWithout, $patientRelation): void {
            if ($units !== []) {
                $query->orWhereHas($patientRelation, fn (Builder $patient): Builder => $patient->whereIn('specific_business_unit', $units));
            }

            if ($includeWithout) {
                $query->orWhereDoesntHave($patientRelation, fn (Builder $patient): Builder => $patient
                    ->whereNotNull('specific_business_unit')
                    ->where('specific_business_unit', '!=', ''));
            }
        });
    }
}
