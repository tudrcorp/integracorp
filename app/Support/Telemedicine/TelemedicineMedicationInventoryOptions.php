<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\OperationInventory;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class TelemedicineMedicationInventoryOptions
{
    public const CATEGORY_MEDICAMENTO = 'Medicamento';

    public const BELONGS_TO_DIAGNOMOVIL = 'Diagnomovil';

    public const BELONGS_TO_CENTRO_3_FEBRERO = 'Centro Diagnostico 3 de Febrero';

    public const WAREHOUSE_DIAGNOMOVIL = 'DIAGNOMOVIL';

    public const WAREHOUSE_3_DE_FEBRERO = '3 DE FEBRERO';

    /**
     * @var array<string, string>
     */
    public const BELONGS_TO_WAREHOUSE_MAP = [
        self::BELONGS_TO_DIAGNOMOVIL => self::WAREHOUSE_DIAGNOMOVIL,
        self::BELONGS_TO_CENTRO_3_FEBRERO => self::WAREHOUSE_3_DE_FEBRERO,
    ];

    public static function warehouseNameForBelongsTo(?string $belongsTo): ?string
    {
        if ($belongsTo === null || trim($belongsTo) === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($belongsTo));

        foreach (self::BELONGS_TO_WAREHOUSE_MAP as $label => $warehouse) {
            if (mb_strtolower($label) === $normalized) {
                return $warehouse;
            }
        }

        if (self::ubicationMatchesWarehouse($belongsTo, self::WAREHOUSE_DIAGNOMOVIL)) {
            return self::WAREHOUSE_DIAGNOMOVIL;
        }

        if (self::ubicationMatchesWarehouse($belongsTo, self::WAREHOUSE_3_DE_FEBRERO)) {
            return self::WAREHOUSE_3_DE_FEBRERO;
        }

        return null;
    }

    public static function doctorIsTdg(?TelemedicineDoctor $doctor): bool
    {
        if ($doctor === null) {
            return false;
        }

        return mb_strtoupper(trim((string) $doctor->managed_by)) === 'TDG';
    }

    public static function doctorBelongsToProvider(?TelemedicineDoctor $doctor): bool
    {
        if ($doctor === null || self::doctorIsTdg($doctor)) {
            return false;
        }

        return true;
    }

    public static function shouldDeductInventory(?TelemedicineDoctor $doctor, ?TelemedicineCase $case): bool
    {
        if (! self::doctorIsTdg($doctor) || $case === null) {
            return false;
        }

        return self::warehouseNameForBelongsTo($case->belongs_to) !== null;
    }

    /**
     * Compara el nombre real del almacén (columna o relación) con la clave canónica.
     * Acepta variantes como «DIAGNO-MOVIL - MENE GRANDE» o el typo «FERBERO».
     */
    public static function ubicationMatchesWarehouse(?string $ubicationName, ?string $warehouseKey): bool
    {
        if ($ubicationName === null || $warehouseKey === null) {
            return false;
        }

        if (trim($ubicationName) === '' || trim($warehouseKey) === '') {
            return false;
        }

        $normalizedUbication = self::normalizeAlphanumeric($ubicationName);
        $normalizedKey = self::normalizeAlphanumeric($warehouseKey);

        if ($normalizedUbication === '' || $normalizedKey === '') {
            return false;
        }

        if ($normalizedUbication === $normalizedKey) {
            return true;
        }

        foreach (self::warehouseMatchNeedles($warehouseKey) as $needle) {
            if ($needle !== '' && str_contains($normalizedUbication, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int|string, string>
     */
    public static function optionsForCase(?TelemedicineCase $case, ?TelemedicineDoctor $doctor = null): array
    {
        $doctor ??= $case?->telemedicineDoctor;

        if (self::shouldDeductInventory($doctor, $case)) {
            return self::warehouseMedicationOptions(
                (string) self::warehouseNameForBelongsTo($case?->belongs_to)
            );
        }

        return self::uniqueMedicationCatalogOptions();
    }

    /**
     * @return array<int|string, string>
     */
    public static function searchOptionsForCase(
        ?TelemedicineCase $case,
        string $search,
        ?TelemedicineDoctor $doctor = null,
        int $limit = 80,
    ): array {
        $options = self::optionsForCase($case, $doctor);
        $needle = mb_strtoupper(trim($search));

        if ($needle === '') {
            return array_slice($options, 0, $limit, preserve_keys: true);
        }

        $filtered = [];

        foreach ($options as $id => $label) {
            if (str_contains(mb_strtoupper((string) $label), $needle)) {
                $filtered[$id] = $label;
            }

            if (count($filtered) >= $limit) {
                break;
            }
        }

        return $filtered;
    }

    /**
     * Inventario del almacén TDG: categoría Medicamento y existencia > 0.
     *
     * @return array<int|string, string>
     */
    public static function warehouseMedicationOptions(string $warehouseName): array
    {
        return self::medicamentoInventoriesQuery()
            ->where('existence', '>', 0)
            ->where(function (Builder $query) use ($warehouseName): void {
                self::constrainInventoryToWarehouse($query, $warehouseName);
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Catálogo único de medicamentos (sin duplicar producto entre almacenes).
     * Usado para doctores de proveedor: no descuenta inventario.
     *
     * @return array<int|string, string>
     */
    public static function uniqueMedicationCatalogOptions(): array
    {
        /** @var Collection<int, OperationInventory> $rows */
        $rows = self::medicamentoInventoriesQuery()
            ->orderBy('name')
            ->get(['id', 'name', 'operation_inventory_product_id']);

        return $rows
            ->unique(function (OperationInventory $inventory): string|int {
                return filled($inventory->operation_inventory_product_id)
                    ? 'product:'.$inventory->operation_inventory_product_id
                    : 'inventory:'.$inventory->id;
            })
            ->mapWithKeys(fn (OperationInventory $inventory): array => [
                $inventory->id => (string) $inventory->name,
            ])
            ->all();
    }

    /**
     * @return Builder<OperationInventory>
     */
    public static function medicamentoInventoriesQuery(): Builder
    {
        return OperationInventory::query()
            ->where(function (Builder $query): void {
                $query
                    ->where('is_active', true)
                    ->orWhereNull('is_active');
            })
            ->whereHas('product.category', function (Builder $category): void {
                $category->whereRaw('UPPER(name) LIKE ?', ['MEDICAMENTO%']);
            });
    }

    /**
     * @param  Builder<OperationInventory>  $query
     */
    public static function constrainInventoryToWarehouse(Builder $query, string $warehouseName): void
    {
        $normalized = mb_strtoupper(trim($warehouseName));
        $likePatterns = self::warehouseSqlLikePatterns($warehouseName);

        $query
            ->whereRaw('UPPER(TRIM(ubication)) = ?', [$normalized])
            ->orWhere(function (Builder $likes) use ($likePatterns): void {
                self::applyLikePatterns($likes, 'ubication', $likePatterns);
            })
            ->orWhereHas('ubicationRelation', function (Builder $ubication) use ($normalized, $likePatterns): void {
                $ubication
                    ->whereRaw('UPPER(TRIM(name)) = ?', [$normalized])
                    ->orWhere(function (Builder $likes) use ($likePatterns): void {
                        self::applyLikePatterns($likes, 'name', $likePatterns);
                    });
            });
    }

    public static function normalizeAlphanumeric(string $value): string
    {
        $upper = mb_strtoupper(trim($value));
        $withoutAccents = strtr($upper, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
        ]);

        return (string) preg_replace('/[^A-Z0-9]/', '', $withoutAccents);
    }

    /**
     * @return list<string>
     */
    public static function warehouseMatchNeedles(string $warehouseKey): array
    {
        $normalized = self::normalizeAlphanumeric($warehouseKey);

        if ($normalized === '') {
            return [];
        }

        if (
            str_contains($normalized, 'DIAGNOMOVIL')
            || str_contains($normalized, 'MENEGRANDE')
        ) {
            return ['DIAGNOMOVIL', 'MENEGRANDE'];
        }

        if (
            str_contains($normalized, '3DEFEBRERO')
            || str_contains($normalized, '3DEFERBERO')
            || str_contains($normalized, 'DIAGNOCENTER')
            || (str_contains($normalized, 'FEBRERO') && str_contains($normalized, '3'))
            || (str_contains($normalized, 'FERBERO') && str_contains($normalized, '3'))
        ) {
            return ['3DEFEBRERO', '3DEFERBERO', 'DIAGNOCENTER'];
        }

        return [$normalized];
    }

    /**
     * @return list<string>
     */
    public static function warehouseSqlLikePatterns(string $warehouseKey): array
    {
        $needles = self::warehouseMatchNeedles($warehouseKey);

        if (in_array('DIAGNOMOVIL', $needles, true)) {
            return ['%DIAGNO%MOVIL%', '%DIAGNOMOVIL%', '%MENE GRANDE%'];
        }

        if (
            in_array('DIAGNOCENTER', $needles, true)
            || in_array('3DEFEBRERO', $needles, true)
        ) {
            return ['%3 DE FEB%', '%3 DE FERB%', '%DIAGNO%CENTER%', '%DIAGNOCENTER%'];
        }

        $normalized = mb_strtoupper(trim($warehouseKey));

        return $normalized !== '' ? ['%'.$normalized.'%'] : [];
    }

    /**
     * @param  list<string>  $likePatterns
     */
    private static function applyLikePatterns(Builder $query, string $column, array $likePatterns): void
    {
        $qualifiedColumn = $column === 'name' ? 'name' : 'ubication';

        foreach ($likePatterns as $i => $pattern) {
            if ($i === 0) {
                $query->whereRaw("UPPER(TRIM({$qualifiedColumn})) LIKE ?", [$pattern]);

                continue;
            }

            $query->orWhereRaw("UPPER(TRIM({$qualifiedColumn})) LIKE ?", [$pattern]);
        }
    }
}
