<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\Supplier;
use App\Models\TelemedicineDoctor;
use App\Models\User;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * A qué proveedor pertenece un médico registrado desde Operaciones.
 *
 * El analista de TDG elige el proveedor (o lo deja vacío para un médico de TDG).
 * El usuario de un proveedor no elige: su médico queda siempre en su proveedor.
 * La regla se aplica en el servidor, no solo bloqueando el campo en la UI.
 */
final class TelemedicineDoctorSupplierAssignment
{
    public const TDG = 'TDG';

    public static function canChooseSupplier(): bool
    {
        return OperationsSupplierScope::authenticatedUserIsTdgAnalyst();
    }

    /**
     * Valor inicial de «Pertenece a» al registrar un médico.
     */
    public static function defaultManagedBy(): ?string
    {
        $supplierId = OperationsSupplierScope::currentSupplierId();

        if ($supplierId !== null) {
            return self::supplierName($supplierId);
        }

        return self::canChooseSupplier() ? self::TDG : null;
    }

    /**
     * «Pertenece a» que corresponde al proveedor elegido: su nombre, o TDG si no hay proveedor.
     */
    public static function managedByForSupplier(mixed $supplierId): string
    {
        if (! filled($supplierId)) {
            return self::TDG;
        }

        return self::supplierName((int) $supplierId) ?? self::TDG;
    }

    /**
     * Acota la lista a los proveedores con «Gestión de Procesos en Integracorp» activa.
     *
     * Se conserva visible el proveedor que el médico ya tiene y el del usuario conectado,
     * aunque hayan apagado la gestión, para no dejar el campo en blanco al editar.
     *
     * @param  Builder<Supplier>  $query
     * @return Builder<Supplier>
     */
    public static function scopeAssignableSuppliers(Builder $query, ?TelemedicineDoctor $record = null): Builder
    {
        $keepIds = array_values(array_filter([
            $record?->supplier_id,
            OperationsSupplierScope::currentSupplierId(),
        ]));

        return $query->where(function (Builder $query) use ($keepIds): void {
            $query->where('gestion_integracorp', true);

            if ($keepIds !== []) {
                $query->orWhereIn($query->getModel()->getQualifiedKeyName(), $keepIds);
            }
        });
    }

    /**
     * Si el proveedor puede asignarse a un médico: con gestión activa, o el que el médico ya tenía.
     */
    public static function isAssignable(mixed $supplierId, ?TelemedicineDoctor $record = null): bool
    {
        if (! filled($supplierId)) {
            return true;
        }

        if ($record !== null && (int) $record->supplier_id === (int) $supplierId) {
            return true;
        }

        return Supplier::query()
            ->whereKey((int) $supplierId)
            ->where('gestion_integracorp', true)
            ->exists();
    }

    public static function isTdg(mixed $managedBy): bool
    {
        return mb_strtoupper(trim((string) $managedBy)) === self::TDG;
    }

    /**
     * Fija `supplier_id` y normaliza `managed_by` antes de guardar.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function enforce(array $data, ?TelemedicineDoctor $record = null): array
    {
        if (self::canChooseSupplier()) {
            $data['supplier_id'] = filled($data['supplier_id'] ?? null) ? (int) $data['supplier_id'] : null;
        } else {
            $data['supplier_id'] = $record !== null
                ? $record->supplier_id
                : OperationsSupplierScope::currentSupplierId();
        }

        if (array_key_exists('managed_by', $data)) {
            $data['managed_by'] = mb_strtoupper(trim((string) $data['managed_by']));
        }

        return $data;
    }

    /**
     * Lleva el proveedor del médico a su cuenta de acceso, que es la que acota lo que ve en Operaciones.
     */
    public static function syncDoctorUser(TelemedicineDoctor $doctor): void
    {
        User::query()
            ->where('doctor_id', $doctor->getKey())
            ->where(function ($query) use ($doctor): void {
                $doctor->supplier_id === null
                    ? $query->whereNotNull('supplier_id')
                    : $query->whereNull('supplier_id')->orWhere('supplier_id', '!=', $doctor->supplier_id);
            })
            ->update(['supplier_id' => $doctor->supplier_id]);
    }

    private static function supplierName(int $supplierId): ?string
    {
        $name = Supplier::query()->whereKey($supplierId)->value('name');

        return filled($name) ? mb_strtoupper(trim((string) $name)) : null;
    }
}
