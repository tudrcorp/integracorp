<?php

declare(strict_types=1);

namespace App\Support\Business\CapitationPlans;

use App\Models\CapitationPlan;
use App\Models\DoctorNurse;
use App\Models\Supplier;
use App\Support\Operations\OutsourcingMedicalDepartment;
use App\Support\Operations\ProviderDeletion;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Proveedores que pueden tener planes de capitado: jurídicos y naturales con el interruptor
 * «Pertenece al Departamento Médico Outsourcing» activo. El valor del selector es
 * «supplier:12» o «doctor_nurse:7».
 */
final class CapitationProviders
{
    private const SEARCH_LIMIT = 50;

    /**
     * @return array<string, string>
     */
    public static function search(string $search = ''): array
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($search)).'%';
        $options = [];

        foreach ([CapitationPlan::PROVIDER_SUPPLIER => Supplier::class, CapitationPlan::PROVIDER_DOCTOR_NURSE => DoctorNurse::class] as $type => $model) {
            $providers = $model::query()
                ->where(OutsourcingMedicalDepartment::FLAG_COLUMN, true)
                ->when(trim($search) !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('rif', 'like', $like)))
                ->orderBy('name')
                ->limit(self::SEARCH_LIMIT)
                ->get(['id', 'name', 'rif']);

            foreach ($providers as $provider) {
                $options[$type.':'.$provider->id] = self::prefix($type).$provider->name.(filled($provider->rif) ? ' · '.$provider->rif : '');
            }
        }

        return $options;
    }

    public static function exists(): bool
    {
        return Supplier::query()->where(OutsourcingMedicalDepartment::FLAG_COLUMN, true)->exists()
            || DoctorNurse::query()->where(OutsourcingMedicalDepartment::FLAG_COLUMN, true)->exists();
    }

    /**
     * @return array{type: string, id: int}
     */
    public static function parse(mixed $key): array
    {
        if (is_string($key) && preg_match('/^(supplier|doctor_nurse):(\d+)$/', $key, $matches) === 1) {
            return ['type' => $matches[1], 'id' => (int) $matches[2]];
        }

        throw new InvalidArgumentException('Elija el proveedor al que pertenece el plan.');
    }

    /**
     * Proveedor del selector, aunque ya no esté en Outsourcing o esté eliminado (para mostrar
     * el de un plan existente).
     */
    public static function find(string $type, int $id): ?Model
    {
        $model = match ($type) {
            CapitationPlan::PROVIDER_SUPPLIER => Supplier::class,
            CapitationPlan::PROVIDER_DOCTOR_NURSE => DoctorNurse::class,
            default => null,
        };

        return $model === null ? null : $model::query()->withTrashed()->find($id);
    }

    public static function label(mixed $key): ?string
    {
        try {
            $parsed = self::parse($key);
        } catch (InvalidArgumentException) {
            return null;
        }

        $provider = self::find($parsed['type'], $parsed['id']);

        return $provider === null ? null : self::prefix($parsed['type']).ProviderDeletion::displayName($provider);
    }

    public static function isOutsourcing(?Model $provider): bool
    {
        return $provider !== null
            && ! (method_exists($provider, 'trashed') && $provider->trashed())
            && (bool) $provider->getAttribute(OutsourcingMedicalDepartment::FLAG_COLUMN);
    }

    public static function typeLabel(string $type): string
    {
        return $type === CapitationPlan::PROVIDER_SUPPLIER ? 'Jurídico' : 'Natural';
    }

    private static function prefix(string $type): string
    {
        return self::typeLabel($type).' · ';
    }
}
