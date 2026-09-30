<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\Supplier;
use App\Models\User;
use App\Support\Filament\Operations\SupplierIntegracorpManagement;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Un usuario de proveedor (con `supplier_id`) solo ve a los afiliados que su proveedor atiende:
 * aquellos cuya afiliación (o empresa, en Nuevos Negocios) lleva el alias del proveedor en
 * «Proveedor(es) de Servicios». Sin gestión activa o sin alias no ve ninguno.
 * Los analistas internos (sin `supplier_id`) no tienen restricción.
 */
final class SupplierAffiliateVisibility
{
    /**
     * Resultado de {@see restriction()} cuando el usuario no tiene restricción.
     */
    public const UNRESTRICTED = null;

    /**
     * Resultado de {@see restriction()} cuando el usuario no debe ver ningún afiliado.
     */
    public const DENY_ALL = '';

    /**
     * Alias del proveedor del usuario, {@see DENY_ALL} o {@see UNRESTRICTED}.
     */
    public static function restriction(?Authenticatable $user = null): ?string
    {
        $user ??= Auth::user();

        if (! $user instanceof User || blank($user->supplier_id)) {
            return self::UNRESTRICTED;
        }

        $supplierId = (int) $user->supplier_id;

        /** `once()` memoriza por las variables capturadas: un solo SELECT por proveedor y petición. */
        return once(static function () use ($supplierId): string {
            $supplier = Supplier::query()
                ->whereKey($supplierId)
                ->first(['id', 'gestion_integracorp', 'integracorp_alias']);

            if ($supplier === null || ! $supplier->gestion_integracorp) {
                return self::DENY_ALL;
            }

            return SupplierIntegracorpManagement::normalizeAlias($supplier->integracorp_alias) ?? self::DENY_ALL;
        });
    }

    public static function isRestricted(?Authenticatable $user = null): bool
    {
        return self::restriction($user) !== self::UNRESTRICTED;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query  consulta sobre `affiliations` o `affiliation_corporates`
     * @return Builder<TModel>
     */
    public static function applyToAffiliations(Builder $query, ?Authenticatable $user = null): Builder
    {
        return self::applyServiceProvidersCondition($query, self::restriction($user));
    }

    /**
     * @param  Builder<\App\Models\Affiliate>  $query
     * @return Builder<\App\Models\Affiliate>
     */
    public static function applyToAffiliates(Builder $query, ?Authenticatable $user = null): Builder
    {
        return self::applyThroughRelation($query, 'affiliation', self::restriction($user));
    }

    /**
     * @param  Builder<\App\Models\AffiliateCorporate>  $query
     * @return Builder<\App\Models\AffiliateCorporate>
     */
    public static function applyToAffiliateCorporates(Builder $query, ?Authenticatable $user = null): Builder
    {
        return self::applyThroughRelation($query, 'affiliationCorporate', self::restriction($user));
    }

    /**
     * @param  Builder<\App\Models\CompanyAssociate>  $query
     * @return Builder<\App\Models\CompanyAssociate>
     */
    public static function applyToCompanyAssociates(Builder $query, ?Authenticatable $user = null): Builder
    {
        return self::applyThroughRelation($query, 'company', self::restriction($user));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private static function applyThroughRelation(Builder $query, string $relation, ?string $restriction): Builder
    {
        if ($restriction === self::UNRESTRICTED) {
            return $query;
        }

        if ($restriction === self::DENY_ALL) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            $relation,
            fn (Builder $related): Builder => self::applyServiceProvidersCondition($related, $restriction),
        );
    }

    /**
     * `service_providers` es texto JSON (`["ATENMEDI","ILS"]`); hay filas históricas vacías o no
     * válidas, por eso se valida antes de buscar para no romper la consulta completa. La comparación
     * es exacta: el campo del formulario ya guarda los nombres en MAYÚSCULAS, y un UPPER() sobre el
     * texto JSON corrompería los escapes `\u00cd` de los acentos.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private static function applyServiceProvidersCondition(Builder $query, ?string $restriction): Builder
    {
        if ($restriction === self::UNRESTRICTED) {
            return $query;
        }

        if ($restriction === self::DENY_ALL) {
            return $query->whereRaw('1 = 0');
        }

        $column = $query->getModel()->qualifyColumn('service_providers');
        $wrapped = $query->getQuery()->getGrammar()->wrap($column);

        if ($query->getModel()->getConnection()->getDriverName() === 'sqlite') {
            return $query->whereRaw(
                "exists (select 1 from json_each(case when json_valid({$wrapped}) then {$wrapped} else '[]' end) where json_each.value = ?)",
                [$restriction],
            );
        }

        return $query->whereRaw(
            "JSON_CONTAINS(IF(JSON_VALID({$wrapped}), {$wrapped}, '[]'), ?)",
            [json_encode($restriction, JSON_UNESCAPED_UNICODE)],
        );
    }
}
