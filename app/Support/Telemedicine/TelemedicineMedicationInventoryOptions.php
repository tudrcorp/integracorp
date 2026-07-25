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
     * Inventario del almacén TDG: categoría Medicamento y existencia > 0.
     *
     * @return array<int|string, string>
     */
    public static function warehouseMedicationOptions(string $warehouseName): array
    {
        return self::medicamentoInventoriesQuery()
            ->where('existence', '>', 0)
            ->whereHas('ubicationRelation', function (Builder $ubication) use ($warehouseName): void {
                $ubication->whereRaw('UPPER(TRIM(name)) = ?', [mb_strtoupper(trim($warehouseName))]);
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
}
