<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Asignación de una coordinación completa a analistas de un proveedor (TDG → proveedor).
 */
final class AssignCoordinationServiceToSupplier
{
    public const OBSERVATION_PREFIX = 'Asignación de coordinación a proveedor por TDG.';

    public static function buildBitacoraDescription(Supplier $supplier, string $reason): string
    {
        $supplierName = filled($supplier->name)
            ? (string) $supplier->name
            : (filled($supplier->razon_social) ? (string) $supplier->razon_social : 'Proveedor #'.$supplier->id);

        return self::OBSERVATION_PREFIX
            ."\n".'Proveedor: '.$supplierName
            ."\n".'Motivo: '.trim($reason);
    }

    /**
     * @return array{coordination: OperationCoordinationService, supplier: Supplier}
     */
    public static function execute(
        OperationCoordinationService $coordination,
        int $supplierId,
        string $reason,
        ?User $user = null,
    ): array {
        $user ??= Auth::user();

        if ($user === null || ! OperationsSupplierScope::authenticatedUserIsTdgAnalyst()) {
            throw new InvalidArgumentException('Solo un analista TDG puede asignar coordinaciones a un proveedor.');
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('El motivo de la asignación debe tener al menos 10 caracteres.');
        }

        $supplier = Supplier::query()->find($supplierId);

        if ($supplier === null) {
            throw new InvalidArgumentException('El proveedor seleccionado no existe.');
        }

        $bitacoraDescription = self::buildBitacoraDescription($supplier, $reason);
        $userName = filled($user->name) ? (string) $user->name : 'SISTEMA';
        $userId = $user->id;

        DB::transaction(function () use (
            $coordination,
            $supplier,
            $bitacoraDescription,
            $userName,
            $userId,
        ): void {
            $previousObservations = trim((string) ($coordination->observations ?? ''));

            $coordination->supplier_id = $supplier->id;
            $coordination->assigned_to_supplier_by_tdg = true;
            $coordination->assigned_to_supplier_by_tdg_at = now();
            $coordination->assigned_to_supplier_by_tdg_by = $userName;
            $coordination->observations = $previousObservations !== ''
                ? $previousObservations."\n\n".$bitacoraDescription
                : $bitacoraDescription;
            $coordination->updated_by = $userName;
            $coordination->save();

            if (filled($coordination->telemedicine_case_id)) {
                ObservationCase::query()->create([
                    'telemedicine_case_id' => $coordination->telemedicine_case_id,
                    'description' => $bitacoraDescription,
                    'created_by' => $userId !== null ? (string) $userId : null,
                ]);
            }
        });

        return [
            'coordination' => $coordination->fresh() ?? $coordination,
            'supplier' => $supplier,
        ];
    }
}
