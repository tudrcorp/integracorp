<?php

declare(strict_types=1);

namespace App\Support\Birthday;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consultas de padrón para el envío diario de cumpleaños.
 * Centraliza filtros de negocio (PQQ, TDEC/TDEV, viajes).
 */
final class BirthdayRecipientQuery
{
    public const PQQ_UNIT = 'PQQ';

    /** Unidad «PLANES DE ASISTENCIA EN VIAJES». */
    public const TRAVEL_BUSINESS_UNIT_ID = 5;

    /**
     * Agentes de corretaje con línea TDEC y/o TDEV activa.
     */
    public static function agents(): Builder
    {
        return DB::table('agents')
            ->where(function (Builder $query): void {
                $query->where('tdec', 1)->orWhere('tdev', 1);
            });
    }

    /**
     * Afiliados individuales TDEC, excluyendo PQQ en afiliado o afiliación.
     */
    public static function affiliates(): Builder
    {
        $query = DB::table('affiliates')
            ->leftJoin('affiliations', 'affiliates.affiliation_id', '=', 'affiliations.id');

        return self::excludePqq($query, 'affiliates', 'affiliations');
    }

    /**
     * Afiliados corporativos TDEC, excluyendo PQQ en afiliado o afiliación.
     */
    public static function affiliateCorporates(): Builder
    {
        $query = DB::table('affiliate_corporates')
            ->leftJoin(
                'affiliation_corporates',
                'affiliate_corporates.affiliation_corporate_id',
                '=',
                'affiliation_corporates.id'
            );

        return self::excludePqq($query, 'affiliate_corporates', 'affiliation_corporates');
    }

    public static function collaborators(): Builder
    {
        return DB::table('rrhh_colaboradors');
    }

    public static function travelAgents(): Builder
    {
        return DB::table('travel_agents');
    }

    /**
     * Clientes TDEV: afiliados (ind./corp.) bajo la unidad de asistencia en viajes.
     *
     * @return list<object>
     */
    public static function tdevClientsRows(string $channel): array
    {
        $individuals = self::tdevClientAffiliates($channel)->get()->all();
        $corporates = self::tdevClientAffiliateCorporates($channel)->get()->all();

        return array_values([...$individuals, ...$corporates]);
    }

    public static function tdevClientAffiliates(string $channel): Builder
    {
        $query = self::affiliates()->where(function (Builder $nested): void {
            $nested->where('affiliates.business_unit_id', self::TRAVEL_BUSINESS_UNIT_ID);

            if (Schema::hasColumn('affiliations', 'business_unit_id')) {
                $nested->orWhere('affiliations.business_unit_id', self::TRAVEL_BUSINESS_UNIT_ID);
            }
        });

        return $channel === 'email'
            ? $query->select(
                'affiliates.id as id',
                'affiliates.full_name as name',
                'affiliates.email as email',
                'affiliates.phone as phone',
                'affiliates.birth_date as birth_date',
            )
            : $query->select(
                'affiliates.id as id',
                'affiliates.full_name as name',
                'affiliates.phone as phone',
                'affiliates.birth_date as birth_date',
                'affiliations.agent_id as agent_id',
            );
    }

    public static function tdevClientAffiliateCorporates(string $channel): Builder
    {
        $query = self::affiliateCorporates()->where(function (Builder $nested): void {
            $nested->where('affiliate_corporates.business_unit_id', self::TRAVEL_BUSINESS_UNIT_ID);

            if (Schema::hasColumn('affiliation_corporates', 'business_unit_id')) {
                $nested->orWhere('affiliation_corporates.business_unit_id', self::TRAVEL_BUSINESS_UNIT_ID);
            }
        });

        return $channel === 'email'
            ? $query->select(
                'affiliate_corporates.id as id',
                'affiliate_corporates.first_name as name',
                'affiliate_corporates.email as email',
                'affiliate_corporates.phone as phone',
                'affiliate_corporates.birth_date as birth_date',
            )
            : $query->select(
                'affiliate_corporates.id as id',
                'affiliate_corporates.first_name as name',
                'affiliate_corporates.phone as phone',
                'affiliate_corporates.birth_date as birth_date',
                'affiliation_corporates.agent_id as agent_id',
            );
    }

    /**
     * Aplica exclusión de unidad de negocio PQQ sobre tablas de afiliado y afiliación.
     */
    public static function excludePqq(Builder $query, string $affiliateTable, string $affiliationTable): Builder
    {
        if (Schema::hasColumn($affiliateTable, 'specific_business_unit')) {
            $query->where(function (Builder $nested) use ($affiliateTable): void {
                $nested->whereNull("{$affiliateTable}.specific_business_unit")
                    ->orWhere("{$affiliateTable}.specific_business_unit", '!=', self::PQQ_UNIT)
                    ->orWhere("{$affiliateTable}.specific_business_unit", '');
            });
        }

        if (Schema::hasColumn($affiliationTable, 'specific_business_unit')) {
            $query->where(function (Builder $nested) use ($affiliationTable): void {
                $nested->whereNull("{$affiliationTable}.specific_business_unit")
                    ->orWhere("{$affiliationTable}.specific_business_unit", '!=', self::PQQ_UNIT)
                    ->orWhere("{$affiliationTable}.specific_business_unit", '');
            });
        }

        return $query;
    }

    /**
     * Restricción Eloquent equivalente para el padrón Filament.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function applyEloquentNonPqq(\Illuminate\Database\Eloquent\Builder $query, string $dataType): \Illuminate\Database\Eloquent\Builder
    {
        $column = 'specific_business_unit';

        if ($dataType === 'affiliates' && Schema::hasColumn('affiliates', $column)) {
            $query->where(function (\Illuminate\Database\Eloquent\Builder $nested) use ($column): void {
                $nested->whereNull($column)
                    ->orWhere($column, '!=', self::PQQ_UNIT)
                    ->orWhere($column, '');
            });
        }

        if ($dataType === 'affiliate_corporates' && Schema::hasColumn('affiliate_corporates', $column)) {
            $query->where(function (\Illuminate\Database\Eloquent\Builder $nested) use ($column): void {
                $nested->whereNull($column)
                    ->orWhere($column, '!=', self::PQQ_UNIT)
                    ->orWhere($column, '');
            });
        }

        if ($dataType === 'tdev_clients') {
            $query->where('business_unit_id', self::TRAVEL_BUSINESS_UNIT_ID);
            if (Schema::hasColumn($query->getModel()->getTable(), $column)) {
                $query->where(function (\Illuminate\Database\Eloquent\Builder $nested) use ($column): void {
                    $nested->whereNull($column)
                        ->orWhere($column, '!=', self::PQQ_UNIT)
                        ->orWhere($column, '');
                });
            }
        }

        if ($dataType === 'agents') {
            $query->where(function (\Illuminate\Database\Eloquent\Builder $nested): void {
                $nested->where('tdec', 1)->orWhere('tdev', 1);
            });
        }

        return $query;
    }
}
