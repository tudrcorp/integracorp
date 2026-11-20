<?php

declare(strict_types=1);

namespace App\Support\ApiPartners;

use App\Models\Supplier;
use Illuminate\Support\Str;

/**
 * Búsqueda de Proveedores jurídicos para vincularlos a un aliado API. Busca en el servidor
 * (hay cientos de proveedores) por nombre o RIF y nunca carga la lista completa.
 */
final class ApiPartnerSupplierOptions
{
    public const SEARCH_LIMIT = 50;

    /**
     * @return array<int, string>
     */
    public static function search(string $search): array
    {
        $search = trim($search);

        if (mb_strlen($search) < 2) {
            return [];
        }

        $like = '%'.addcslashes($search, '%_\\').'%';

        return Supplier::query()
            ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('rif', 'like', $like))
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT)
            ->get(['id', 'name', 'rif'])
            ->mapWithKeys(fn (Supplier $supplier): array => [(int) $supplier->getKey() => self::format($supplier->name, $supplier->rif)])
            ->all();
    }

    public static function label(mixed $supplierId): ?string
    {
        if (! is_numeric($supplierId)) {
            return null;
        }

        $supplier = Supplier::withTrashed()->find((int) $supplierId, ['id', 'name', 'rif', 'deleted_at']);

        if ($supplier === null) {
            return null;
        }

        return self::format($supplier->name, $supplier->rif).($supplier->trashed() ? ' (eliminado)' : '');
    }

    /**
     * Nombre en una línea (algunos registros traen saltos de línea) y RIF si lo tiene.
     */
    public static function format(?string $name, ?string $rif): string
    {
        $name = Str::squish((string) $name);
        $rif = Str::squish((string) $rif);

        return $rif !== '' ? $name.' · '.$rif : $name;
    }
}
