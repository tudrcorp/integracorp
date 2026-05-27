<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\DoctorNurse;
use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final class OperationServiceOrderUnregisteredProviderRegistrar
{
    private const STATUS_CONVENIO = 'NO CONVENIDO';

    private const STATUS_SISTEMA = 'PENDIENTE';

    public static function isRegistrationRequested(array $data): bool
    {
        return (bool) ($data['register_unregistered_provider'] ?? false);
    }

    public static function validationMessage(array $data): ?string
    {
        if (! self::isRegistrationRequested($data)) {
            return null;
        }

        $type = (string) ($data['unregistered_provider_type'] ?? '');

        if (! in_array($type, ['juridico', 'natural'], true)) {
            return 'Seleccione si el proveedor no convenido es jurídico o natural.';
        }

        if (! filled(trim((string) ($data['unregistered_name'] ?? '')))) {
            return 'Indique el nombre o razón social del proveedor no convenido.';
        }

        if (! filled(trim((string) ($data['unregistered_rif'] ?? '')))) {
            return 'Indique el C.I. o R.I.F. del proveedor no convenido.';
        }

        return null;
    }

    /**
     * @return array{doctor_nurse_id: ?int, supplier_id: ?int, supplier_external: ?string}
     */
    public static function registerFromFormData(array $data): array
    {
        $type = (string) ($data['unregistered_provider_type'] ?? '');
        $userName = Auth::user()?->name ?? 'Sistema';
        $name = Str::upper(trim((string) ($data['unregistered_name'] ?? '')));
        $rif = Str::upper(trim((string) ($data['unregistered_rif'] ?? '')));

        $shared = [
            'name' => $name,
            'rif' => $rif,
            'razon_social' => $name,
            'personal_phone' => self::resolvePhone($data),
            'local_phone' => null,
            'correo_principal' => self::nullableString($data['unregistered_correo_principal'] ?? null),
            'ubicacion_principal' => self::nullableString($data['unregistered_ubicacion_principal'] ?? null),
            'status_convenio' => self::STATUS_CONVENIO,
            'status_sistema' => self::STATUS_SISTEMA,
            'created_by' => $userName,
            'updated_by' => $userName,
        ];

        if ($type === 'natural') {
            $doctorNurse = DoctorNurse::query()->create($shared);

            return [
                'doctor_nurse_id' => $doctorNurse->id,
                'supplier_id' => null,
                'supplier_external' => null,
            ];
        }

        $supplier = Supplier::query()->create([
            ...$shared,
            'observaciones' => 'Registro rápido desde orden de servicio (proveedor no convenido).',
        ]);

        return [
            'doctor_nurse_id' => null,
            'supplier_id' => $supplier->id,
            'supplier_external' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function resolvePhone(array $data): ?string
    {
        $phone = self::nullableString($data['unregistered_phone'] ?? null);

        if ($phone !== null) {
            return $phone;
        }

        return self::nullableString($data['unregistered_personal_phone'] ?? null)
            ?? self::nullableString($data['unregistered_local_phone'] ?? null);
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return $string !== '' ? $string : null;
    }
}
