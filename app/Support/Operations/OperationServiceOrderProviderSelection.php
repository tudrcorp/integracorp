<?php

declare(strict_types=1);

namespace App\Support\Operations;

final class OperationServiceOrderProviderSelection
{
    public static function validationMessage(array $data): ?string
    {
        $unregisteredError = OperationServiceOrderUnregisteredProviderRegistrar::validationMessage($data);

        if ($unregisteredError !== null) {
            return $unregisteredError;
        }

        $count = self::selectedCount($data);

        if ($count === 0) {
            return 'Debe indicar exactamente un proveedor: natural, jurídico o no convenido.';
        }

        if ($count > 1) {
            return 'Solo puede registrar un proveedor por orden de servicio. Deje los otros campos vacíos.';
        }

        return null;
    }

    /**
     * @return array{doctor_nurse_id: ?int, supplier_id: ?int, supplier_external: ?string}
     */
    public static function resolveProviders(array $data): array
    {
        if (OperationServiceOrderUnregisteredProviderRegistrar::isRegistrationRequested($data)) {
            return OperationServiceOrderUnregisteredProviderRegistrar::registerFromFormData($data);
        }

        return self::normalizeExistingSelection($data);
    }

    /**
     * @return array{doctor_nurse_id: ?int, supplier_id: ?int, supplier_external: ?string}
     */
    public static function normalizeFromFormData(array $data): array
    {
        return self::resolveProviders($data);
    }

    /**
     * @return array{doctor_nurse_id: ?int, supplier_id: ?int, supplier_external: ?string}
     */
    private static function normalizeExistingSelection(array $data): array
    {
        $doctorNurseId = filled($data['doctor_nurse_id'] ?? null)
            ? (int) $data['doctor_nurse_id']
            : null;

        $supplierId = filled($data['supplier_id'] ?? null)
            ? (int) $data['supplier_id']
            : null;

        $supplierExternal = trim((string) ($data['supplier_external'] ?? ''));
        $supplierExternal = $supplierExternal !== '' ? $supplierExternal : null;

        if ($doctorNurseId !== null) {
            return [
                'doctor_nurse_id' => $doctorNurseId,
                'supplier_id' => null,
                'supplier_external' => null,
            ];
        }

        if ($supplierId !== null) {
            return [
                'doctor_nurse_id' => null,
                'supplier_id' => $supplierId,
                'supplier_external' => null,
            ];
        }

        return [
            'doctor_nurse_id' => null,
            'supplier_id' => null,
            'supplier_external' => $supplierExternal,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function selectedCount(array $data): int
    {
        $count = 0;

        if (filled($data['doctor_nurse_id'] ?? null)) {
            $count++;
        }

        if (filled($data['supplier_id'] ?? null)) {
            $count++;
        }

        if (OperationServiceOrderUnregisteredProviderRegistrar::isRegistrationRequested($data)) {
            $count++;
        }

        if (filled(trim((string) ($data['supplier_external'] ?? '')))) {
            $count++;
        }

        return $count;
    }
}
