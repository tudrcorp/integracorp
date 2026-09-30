<?php

declare(strict_types=1);

namespace App\Support\Operations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Conteos de los encabezados de Afiliados (Individuales, Corporativos y Nuevos Negocios) en Operaciones.
 *
 * Reciben la consulta base del recurso para heredar el filtro por proveedor
 * ({@see SupplierAffiliateVisibility}) y resuelven todo en una sola consulta agregada.
 */
final class AffiliateListHeaderSummary
{
    /**
     * @param  Builder<\App\Models\Affiliate|\App\Models\AffiliateCorporate>  $query
     * @return array{total: int, active: int, pre_approved: int, excluded: int, today: int, companies: int}
     */
    public static function forAffiliates(Builder $query, ?string $companyColumn = null): array
    {
        $table = $query->getModel()->getTable();
        $companies = $companyColumn !== null ? "COUNT(DISTINCT {$table}.{$companyColumn})" : '0';

        $row = $query->toBase()
            ->selectRaw(
                'COUNT(*) AS total_count, '
                ."SUM(CASE WHEN {$table}.status = ? THEN 1 ELSE 0 END) AS active_count, "
                ."SUM(CASE WHEN {$table}.status = ? THEN 1 ELSE 0 END) AS pre_approved_count, "
                ."SUM(CASE WHEN {$table}.status = ? THEN 1 ELSE 0 END) AS excluded_count, "
                ."SUM(CASE WHEN {$table}.status = ? AND {$table}.created_at >= ? THEN 1 ELSE 0 END) AS today_count, "
                ."{$companies} AS companies_count",
                ['ACTIVO', 'PRE-APROBADA', 'EXCLUIDO', 'ACTIVO', self::startOfToday()],
            )
            ->first();

        return [
            'total' => (int) ($row->total_count ?? 0),
            'active' => (int) ($row->active_count ?? 0),
            'pre_approved' => (int) ($row->pre_approved_count ?? 0),
            'excluded' => (int) ($row->excluded_count ?? 0),
            'today' => (int) ($row->today_count ?? 0),
            'companies' => (int) ($row->companies_count ?? 0),
        ];
    }

    /**
     * @param  Builder<\App\Models\CompanyAssociate>  $query
     * @return array{total: int, active: int, without_voucher: int, cancelled: int, companies: int}
     */
    public static function forCompanyAssociates(Builder $query): array
    {
        $table = $query->getModel()->getTable();

        $row = $query->withoutEagerLoads()->toBase()
            ->selectRaw(
                'COUNT(*) AS total_count, '
                ."SUM(CASE WHEN {$table}.status = ? THEN 1 ELSE 0 END) AS active_count, "
                ."SUM(CASE WHEN {$table}.status = ? THEN 1 ELSE 0 END) AS without_voucher_count, "
                ."SUM(CASE WHEN {$table}.status = ? THEN 1 ELSE 0 END) AS cancelled_count, "
                ."COUNT(DISTINCT {$table}.company_id) AS companies_count",
                ['ACTIVO', 'ACTIVO-SIN-VAUCHER-ILS', 'ANULADO'],
            )
            ->first();

        return [
            'total' => (int) ($row->total_count ?? 0),
            'active' => (int) ($row->active_count ?? 0),
            'without_voucher' => (int) ($row->without_voucher_count ?? 0),
            'cancelled' => (int) ($row->cancelled_count ?? 0),
            'companies' => (int) ($row->companies_count ?? 0),
        ];
    }

    private static function startOfToday(): string
    {
        return Carbon::now((string) config('app.timezone'))->startOfDay()->toDateTimeString();
    }
}
