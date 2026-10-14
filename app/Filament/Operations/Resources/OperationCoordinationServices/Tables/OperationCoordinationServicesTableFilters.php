<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Tables;

use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\CoordinationServiceCaseDeletion;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Filtros del cuadro de control de servicios médicos.
 *
 * El estatus manda sobre lo que carga la tabla: por defecto solo EN GESTION y
 * PENDIENTE (lo que el analista tiene que mover); cualquier otro estatus se ve
 * únicamente si el analista lo elige. Reemplaza a las antiguas pestañas por
 * estatus, con su misma semántica (ver {@see applyStatusFilter()}), y a la
 * pestaña ELIMINADOS, que ahora es un interruptor solo para quien puede
 * eliminar casos.
 */
final class OperationCoordinationServicesTableFilters
{
    public const STATUS_FILTER = 'status';

    /** @var list<string> */
    public const DEFAULT_STATUSES = ['EN GESTION', 'PENDIENTE'];

    public const FINALIZED_STATUS = 'FINALIZADO';

    /**
     * Opción del filtro → valores de `status` que agrupa (las antiguas
     * pestañas sumaban variantes con y sin «O», o con «ESTUDIO»).
     *
     * @var array<string, list<string>>
     */
    public const STATUS_GROUPS = [
        'EN GESTION' => ['EN GESTION'],
        'PENDIENTE' => ['PENDIENTE'],
        'PENDIENTE POR RESULTADOS' => ['PENDIENTE POR RESULTADOS'],
        'FINALIZADO' => ['FINALIZADO'],
        'CANCELADA' => ['CANCELADA', 'CANCELADO'],
        'NOVEDAD ADMON' => ['NOVEDAD ADMON', 'NOVEDAD ADMON ESTUDIO'],
    ];

    /** @var array<string, string> */
    public const AFFILIATION_TYPES = [
        'INDIVIDUAL' => 'Individual',
        'CORPORATIVO' => 'Corporativo',
    ];

    /**
     * @return list<BaseFilter>
     */
    public static function make(): array
    {
        return [
            self::registrationDateFilter(),
            SelectFilter::make(self::STATUS_FILTER)
                ->label('Estatus del servicio')
                ->multiple()
                ->options(array_combine(array_keys(self::STATUS_GROUPS), array_keys(self::STATUS_GROUPS)))
                ->default(self::DEFAULT_STATUSES)
                ->placeholder('Todos los estatus por gestionar')
                ->query(fn (Builder $query, array $data, mixed $livewire): Builder => self::applyStatusFilter(
                    $query,
                    self::selectedValues($data),
                    CoordinationServiceCaseDeletion::viewingDeletedTab($livewire),
                )),
            SelectFilter::make('servicie')
                ->label('Tipo de servicio')
                ->multiple()
                ->searchable()
                ->options(fn (): array => self::distinctOptions('servicie'))
                ->placeholder('Todos los servicios')
                ->query(fn (Builder $query, array $data): Builder => self::whereColumnIn($query, 'servicie', self::selectedValues($data))),
            SelectFilter::make('managed_by')
                ->label('Proveedor')
                ->multiple()
                ->searchable()
                ->options(fn (): array => self::distinctOptions('managed_by'))
                ->placeholder('Todos los proveedores')
                // Un proveedor ya ve solo lo suyo: el filtro solo tiene sentido para TDG.
                ->visible(fn (): bool => self::userCanFilterBySupplier())
                ->query(fn (Builder $query, array $data): Builder => self::whereColumnIn($query, 'managed_by', self::selectedValues($data))),
            SelectFilter::make('affiliation_type')
                ->label('Tipo de afiliado')
                ->options(self::AFFILIATION_TYPES)
                ->placeholder('Individuales y corporativos')
                ->query(fn (Builder $query, array $data): Builder => self::applyAffiliationTypeFilter($query, $data['value'] ?? null)),
            self::deletedCasesFilter(),
        ];
    }

    /**
     * Va en la consulta base, no en la de filtros: el scope de eliminados
     * quita un scope global, y dentro del `where` anidado donde Filament
     * aplica los filtros eso no tendría efecto. Se exige `isActive` explícito
     * porque Filament da por activo un filtro sin estado (p. ej. filtros
     * guardados en sesión antes de que este existiera).
     */
    private static function deletedCasesFilter(): Filter
    {
        return Filter::make(CoordinationServiceCaseDeletion::DELETED_TAB)
            ->label('Ver solo casos eliminados')
            ->toggle()
            ->columnSpanFull()
            ->visible(fn (): bool => CoordinationServiceCaseDeletion::userCanDeleteCases())
            ->baseQuery(fn (Builder $query, array $data): Builder => ($data['isActive'] ?? false) === true
                ? CoordinationServiceCaseDeletion::applyDeletedCasesScope($query)
                : $query);
    }

    /**
     * Sin estatus elegido se ve todo lo que está por gestionar (la antigua
     * pestaña «Todas»). Los estatus de trabajo excluyen los servicios con todos
     * sus ítems cerrados; FINALIZADO trae justo esos, como su antigua pestaña.
     * En ELIMINADOS el filtro va directo contra la columna: ahí no hay trabajo
     * pendiente que separar.
     *
     * @param  list<string>  $statuses
     */
    public static function applyStatusFilter(Builder $query, array $statuses, bool $viewingDeletedTab = false): Builder
    {
        $statuses = array_values(array_intersect($statuses, array_keys(self::STATUS_GROUPS)));

        if ($viewingDeletedTab) {
            return $statuses === []
                ? $query
                : $query->whereIn('status', self::statusValues($statuses));
        }

        if ($statuses === []) {
            return OperationCoordinationServicesTable::applyHideFullyFinalizedScope($query);
        }

        $workStatuses = array_values(array_diff($statuses, [self::FINALIZED_STATUS]));
        $includesFinalized = in_array(self::FINALIZED_STATUS, $statuses, true);

        return $query->where(function (Builder $byStatus) use ($workStatuses, $includesFinalized): void {
            if ($workStatuses !== []) {
                $byStatus->where(fn (Builder $open): Builder => OperationCoordinationServicesTable::applyHideFullyFinalizedScope($open)
                    ->whereIn('status', self::statusValues($workStatuses)));
            }

            if ($includesFinalized) {
                $byStatus->orWhere(fn (Builder $finalized): Builder => OperationCoordinationServicesTable::applyFullyFinalizedScope($finalized));
            }
        });
    }

    public static function applyAffiliationTypeFilter(Builder $query, mixed $type): Builder
    {
        $type = is_string($type) ? mb_strtoupper(trim($type)) : '';

        if (! array_key_exists($type, self::AFFILIATION_TYPES)) {
            return $query;
        }

        return $query->whereHas(
            'telemedicinePatient',
            fn (Builder $patient): Builder => $patient->whereRaw('UPPER(TRIM(type_affiliation)) = ?', [$type]),
        );
    }

    /**
     * Rango por fecha de registro del servicio. Se compara contra el inicio y
     * el fin del día en lugar de `whereDate`, que no puede usar índice.
     *
     * @param  array<string, mixed>  $data
     */
    public static function applyRegistrationDateFilter(Builder $query, array $data): Builder
    {
        $from = self::parseDate($data['desde'] ?? null);
        $until = self::parseDate($data['hasta'] ?? null);

        if ($from !== null && $until !== null && $from->greaterThan($until)) {
            [$from, $until] = [$until, $from];
        }

        $column = $query->getModel()->qualifyColumn('created_at');

        return $query
            ->when($from, fn (Builder $query, Carbon $date): Builder => $query->where($column, '>=', $date->copy()->startOfDay()))
            ->when($until, fn (Builder $query, Carbon $date): Builder => $query->where($column, '<=', $date->copy()->endOfDay()));
    }

    /**
     * @param  list<string>  $statuses
     * @return list<string>
     */
    public static function statusValues(array $statuses): array
    {
        $values = [];

        foreach ($statuses as $status) {
            array_push($values, ...(self::STATUS_GROUPS[$status] ?? []));
        }

        return array_values(array_unique($values));
    }

    private static function registrationDateFilter(): Filter
    {
        return Filter::make('created_at')
            ->label('Fecha de registro')
            ->schema([
                DatePicker::make('desde')
                    ->label('Registrado desde')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(now()),
                DatePicker::make('hasta')
                    ->label('Registrado hasta')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(now())
                    ->minDate(fn (Get $get): mixed => $get('desde')),
            ])
            ->columns(2)
            ->columnSpanFull()
            ->query(fn (Builder $query, array $data): Builder => self::applyRegistrationDateFilter($query, $data))
            ->indicateUsing(function (array $data): array {
                $indicators = [];

                if (($from = self::parseDate($data['desde'] ?? null)) !== null) {
                    $indicators[] = Indicator::make('Registrado desde '.$from->format('d/m/Y'))->removeField('desde');
                }

                if (($until = self::parseDate($data['hasta'] ?? null)) !== null) {
                    $indicators[] = Indicator::make('Registrado hasta '.$until->format('d/m/Y'))->removeField('hasta');
                }

                return $indicators;
            });
    }

    /**
     * Valores distintos de una columna de texto libre, dentro de lo que el
     * usuario puede ver. La clave es el valor exacto (para que el filtro
     * coincida); la etiqueta, el valor con los espacios y saltos normalizados.
     *
     * @return array<string, string>
     */
    private static function distinctOptions(string $column): array
    {
        return OperationsSupplierScope::coordinationServiceQuery()
            ->toBase()
            ->whereNotNull($column)
            ->whereRaw("TRIM({$column}) <> ''")
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->mapWithKeys(fn (mixed $value): array => [
                (string) $value => mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', (string) $value))),
            ])
            ->all();
    }

    /**
     * @param  list<string>  $values
     */
    private static function whereColumnIn(Builder $query, string $column, array $values): Builder
    {
        return $values === [] ? $query : $query->whereIn($column, $values);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function selectedValues(array $data): array
    {
        $values = $data['values'] ?? [];

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $values),
            static fn (string $value): bool => $value !== '',
        ));
    }

    public static function userCanFilterBySupplier(): bool
    {
        $departments = Auth::user()?->departament;

        return OperationsSupplierScope::authenticatedUserIsTdgAnalyst()
            && ! in_array('ATENMEDI', is_array($departments) ? $departments : [], true);
    }

    private static function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
