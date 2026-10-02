<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\OperationInventory;
use App\Models\TelemedicinePatientMedications;
use Illuminate\Database\Eloquent\Builder;

final class TelemedicineMedicationCoverage
{
    /**
     * Si hay ítem de inventario, la cobertura proviene del inventario.
     * Sin inventario: respeta `is_covered` (cubierto de gestión Operaciones vs. no cubierto).
     */
    public static function isCovered(TelemedicinePatientMedications $record): bool
    {
        if (self::hasLinkedInventory($record)) {
            if ($record->operationInventory) {
                return (bool) ($record->operationInventory->is_covered ?? false);
            }

            return (bool) ($record->is_covered ?? false);
        }

        return (bool) ($record->is_covered ?? false);
    }

    /**
     * Versión SQL de {@see isCovered()}: misma regla para filtrar en base de datos.
     * Con inventario vinculado manda el inventario (y, si la fila de inventario ya
     * no existe, el `is_covered` del medicamento); sin inventario manda `is_covered`,
     * que es como el médico de un proveedor sin inventario indica que su proveedor lo cubre.
     *
     * @param  Builder<TelemedicinePatientMedications>  $medications
     * @return Builder<TelemedicinePatientMedications>
     */
    public static function whereCovered(Builder $medications): Builder
    {
        return $medications->where(function (Builder $covered): void {
            $covered
                ->where(function (Builder $fromInventory): void {
                    $fromInventory
                        ->whereNotNull('operation_inventory_id')
                        ->whereHas('operationInventory', fn (Builder $inventory): Builder => $inventory->where('is_covered', true));
                })
                ->orWhere(function (Builder $missingInventory): void {
                    $missingInventory
                        ->whereNotNull('operation_inventory_id')
                        ->whereDoesntHave('operationInventory')
                        ->where('is_covered', true);
                })
                ->orWhere(function (Builder $manual): void {
                    $manual
                        ->whereNull('operation_inventory_id')
                        ->where('is_covered', true);
                });
        });
    }

    public static function hasLinkedInventory(TelemedicinePatientMedications $record): bool
    {
        return filled($record->operation_inventory_id);
    }

    public static function isCoveredWithoutInventory(TelemedicinePatientMedications $record): bool
    {
        return ! self::hasLinkedInventory($record) && (bool) ($record->is_covered ?? false);
    }

    public static function isManualMedication(TelemedicinePatientMedications $record): bool
    {
        return ! self::hasLinkedInventory($record);
    }

    public static function coverageLabel(TelemedicinePatientMedications $record): string
    {
        if (self::isCoveredWithoutInventory($record)) {
            return 'Cubierto (gestión Operaciones)';
        }

        return self::isCovered($record) ? 'Cubierto' : 'No cubierto';
    }

    public static function originLabel(TelemedicinePatientMedications $record): string
    {
        if (self::hasLinkedInventory($record)) {
            return 'Inventario TDC';
        }

        if (self::isCoveredWithoutInventory($record)) {
            return 'Cubierto sin inventario';
        }

        return 'No cubierto';
    }

    /**
     * Etiqueta corta para el PDF que ve el paciente.
     *
     * @param  array<string, mixed>  $row
     */
    public static function pdfCoverageLabelFromRow(array $row, ?bool $inventoryIsCovered = null): string
    {
        if (isset($row['coverage']) && is_string($row['coverage']) && $row['coverage'] !== '') {
            return self::shortPdfCoverageLabel($row['coverage']);
        }

        if (array_key_exists('is_covered', $row)) {
            return filter_var($row['is_covered'], FILTER_VALIDATE_BOOLEAN) ? 'Cubierto' : 'No cubierto';
        }

        if (self::rowHasCoveredWithoutInventory($row)) {
            return 'Cubierto';
        }

        if (self::rowHasUncoveredManual($row) && ! self::rowHasInventory($row)) {
            return 'No cubierto';
        }

        if (self::rowHasInventory($row)) {
            return $inventoryIsCovered === true ? 'Cubierto' : 'No cubierto';
        }

        return 'No cubierto';
    }

    public static function shortPdfCoverageLabel(string $coverage): string
    {
        $normalized = mb_strtolower(trim($coverage));

        if (str_contains($normalized, 'no cubierto')) {
            return 'No cubierto';
        }

        if (str_starts_with($normalized, 'cubierto')) {
            return 'Cubierto';
        }

        return $coverage;
    }

    public static function coverageForPersist(?int $operationInventoryId, bool $coveredWithoutInventory = false): bool
    {
        if ($coveredWithoutInventory) {
            return true;
        }

        if (! filled($operationInventoryId)) {
            return false;
        }

        $inventory = OperationInventory::query()->find($operationInventoryId);

        return (bool) ($inventory?->is_covered ?? false);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function rowHasInventory(array $row): bool
    {
        return trim((string) ($row['operation_inventory_id'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function rowHasCoveredWithoutInventory(array $row): bool
    {
        return trim((string) ($row['covered_medicines'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function rowHasUncoveredManual(array $row): bool
    {
        return trim((string) ($row['medicines'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function exclusiveSourceError(array $row, int $rowNumber): ?string
    {
        $sources = (int) self::rowHasInventory($row)
            + (int) self::rowHasCoveredWithoutInventory($row)
            + (int) self::rowHasUncoveredManual($row);

        if ($sources > 1) {
            return "En la fila {$rowNumber} use solo una fuente: inventario TDC, cubierto (gestión Operaciones) o no cubierto.";
        }

        return null;
    }

    public static function providerInventoryError(int $rowNumber, string $coveredColumnLabel = 'Cubierto por el proveedor'): string
    {
        return "En la fila {$rowNumber} no puede usar el inventario TDC: como médico de un proveedor registre el medicamento en «{$coveredColumnLabel}» o en «No cubierto».";
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function medicineNameFromRow(array $row): string
    {
        $covered = trim((string) ($row['covered_medicines'] ?? ''));
        if ($covered !== '') {
            return $covered;
        }

        return trim((string) ($row['medicines'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{operation_inventory_id: int|null, medicine: string, is_covered: bool, should_deduct_inventory: bool}|null
     */
    public static function persistPayloadFromRow(array $row): ?array
    {
        $coveredWithoutInventory = self::rowHasCoveredWithoutInventory($row);
        $inventoryId = (! $coveredWithoutInventory && self::rowHasInventory($row))
            ? (int) $row['operation_inventory_id']
            : null;
        $medicine = self::medicineNameFromRow($row);

        if ($medicine === '' && $inventoryId === null) {
            return null;
        }

        if ($medicine === '' && $inventoryId !== null) {
            $medicine = trim((string) (OperationInventory::query()->whereKey($inventoryId)->value('name') ?? ''));
        }

        return [
            'operation_inventory_id' => $inventoryId,
            'medicine' => $medicine,
            'is_covered' => self::coverageForPersist($inventoryId, $coveredWithoutInventory),
            'should_deduct_inventory' => $inventoryId !== null,
        ];
    }
}
