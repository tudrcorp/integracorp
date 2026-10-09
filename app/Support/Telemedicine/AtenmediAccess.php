<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\Supplier;
use App\Models\TelemedicineDoctor;
use App\Models\User;

/**
 * Quién es personal de ATENMEDI, por el proveedor y no por el texto `managed_by`.
 *
 * `managed_by` se escribe a mano y no coincide con «ATENMEDI» en ningún dato
 * (guarda el nombre comercial completo). El dato confiable es el proveedor del
 * usuario o de su médico: los fijados en `services.atenmedi.supplier_ids` o, si
 * no hay, los proveedores cuyo nombre contiene «(ATENMEDI)».
 */
final class AtenmediAccess
{
    /** @var list<int>|null */
    private static ?array $supplierIds = null;

    /** @var array<int, bool> */
    private static array $userCache = [];

    /**
     * @return list<int>
     */
    public static function supplierIds(): array
    {
        if (self::$supplierIds !== null) {
            return self::$supplierIds;
        }

        $configured = array_values(array_filter(
            array_map('intval', (array) config('services.atenmedi.supplier_ids', [])),
            static fn (int $id): bool => $id > 0,
        ));

        return self::$supplierIds = $configured !== []
            ? $configured
            : Supplier::query()
                ->where('name', 'like', '%(ATENMEDI)%')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all();
    }

    public static function isAtenmediSupplier(?int $supplierId): bool
    {
        return $supplierId !== null && in_array($supplierId, self::supplierIds(), true);
    }

    /**
     * Personal de ATENMEDI: usuario del proveedor, médico de ese proveedor o
     * departamento ATENMEDI.
     */
    public static function userIsAtenmedi(mixed $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        $key = (int) $user->getKey();

        if (array_key_exists($key, self::$userCache)) {
            return self::$userCache[$key];
        }

        $departments = array_map(
            static fn (mixed $department): string => mb_strtoupper(trim((string) $department)),
            is_array($user->departament) ? $user->departament : [],
        );

        $isAtenmedi = in_array('ATENMEDI', $departments, true)
            || self::isAtenmediSupplier($user->supplier_id !== null ? (int) $user->supplier_id : null)
            || ($user->doctor_id !== null && self::isAtenmediSupplier(self::doctorSupplierId((int) $user->doctor_id)));

        return self::$userCache[$key] = $isAtenmedi;
    }

    public static function flush(): void
    {
        self::$supplierIds = null;
        self::$userCache = [];
    }

    private static function doctorSupplierId(int $doctorId): ?int
    {
        $supplierId = TelemedicineDoctor::query()->whereKey($doctorId)->value('supplier_id');

        return $supplierId !== null ? (int) $supplierId : null;
    }
}
