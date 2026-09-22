<?php

declare(strict_types=1);

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\WhiteCompany;
use App\Support\WhiteCompanies\WhiteCompanyOwnership;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `affiliations.white_company_id` y su gemelo corporativo quedaron con
 * referencias a empresas aliadas que ya no existen (se borró la ficha y se
 * recreó con otro id). Este vínculo decide la marca de los documentos, el
 * reporte de ventas y la conciliación de crédito, así que se reapunta a la
 * empresa que hoy resuelve la jerarquía comercial de la agencia emisora y se
 * anula cuando no hay ninguna.
 *
 * No se agrega clave foránea: la columna es `int` y `white_companies.id` es
 * `bigint unsigned`; igualar el tipo reescribiría la tabla de afiliaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        WhiteCompanyOwnership::flush();

        foreach (['affiliations', 'affiliation_corporates'] as $table) {
            if (! Schema::hasColumn($table, 'white_company_id')) {
                continue;
            }

            if (! Schema::hasIndex($table, $table.'_white_company_id_index')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->index('white_company_id');
                });
            }
        }

        $existingIds = WhiteCompany::query()->pluck('id')->map(intval(...))->all();

        foreach ([Affiliation::class, AffiliationCorporate::class] as $model) {
            $orphans = $model::query()
                ->whereNotNull('white_company_id')
                ->when($existingIds !== [], fn ($query) => $query->whereNotIn('white_company_id', $existingIds))
                ->get(['id', 'code_agency']);

            /** @var array<string, list<int>> $byTarget */
            $byTarget = [];

            foreach ($orphans as $record) {
                $target = WhiteCompanyOwnership::companyIdForAgencyCode(
                    is_string($record->code_agency) ? $record->code_agency : null
                );

                $byTarget[(string) ($target ?? '')][] = (int) $record->id;
            }

            foreach ($byTarget as $target => $ids) {
                $model::query()
                    ->whereIn('id', $ids)
                    ->update(['white_company_id' => $target === '' ? null : (int) $target]);
            }
        }
    }

    public function down(): void
    {
        foreach (['affiliations', 'affiliation_corporates'] as $table) {
            if (Schema::hasIndex($table, $table.'_white_company_id_index')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropIndex(['white_company_id']);
                });
            }
        }
    }
};
