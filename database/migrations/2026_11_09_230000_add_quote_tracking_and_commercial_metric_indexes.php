<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precisión de las métricas comerciales de cotizaciones.
 *
 * - `individual_quotes.anulated_at`: cuándo se anuló (antes no se guardaba; las ya anuladas
 *   quedan sin fecha porque no hay de dónde reconstruirla).
 * - `quote_deletion_logs`: cotizaciones borradas. El borrado sigue siendo físico (los números de
 *   cotización se generan con max(id) y un borrado reversible los repetiría), pero desde ahora
 *   queda la huella: qué cotización era, su plan, su estado y quién la borró.
 * - Índices para contar por fecha y resolver plan y afiliación sin recorrer tablas enteras.
 *
 * Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('individual_quotes', 'anulated_at')) {
            Schema::table('individual_quotes', function (Blueprint $table): void {
                $table->timestamp('anulated_at')->nullable()->after('status');
            });
        }

        if (! Schema::hasTable('quote_deletion_logs')) {
            Schema::create('quote_deletion_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('quote_type', 20);
                $table->unsignedBigInteger('quote_id');
                $table->string('code')->nullable();
                $table->string('plan', 20)->nullable();
                $table->json('plan_ids')->nullable();
                $table->string('status', 40)->nullable();
                $table->timestamp('quote_created_at')->nullable();
                $table->unsignedBigInteger('deleted_by_id')->nullable();
                $table->string('deleted_by_name')->nullable();
                $table->timestamp('deleted_at');
                $table->timestamps();

                $table->index(['quote_type', 'quote_created_at'], 'qdl_type_created_idx');
                $table->index(['quote_type', 'deleted_at'], 'qdl_type_deleted_idx');
            });
        }

        $indexes = [
            ['detail_individual_quotes', ['individual_quote_id', 'plan_id'], 'diq_quote_plan_idx'],
            ['affiliations', ['individual_quote_id'], 'aff_individual_quote_idx'],
            ['affiliation_corporates', ['corporate_quote_id'], 'affc_corporate_quote_idx'],
            ['individual_quotes', ['created_at'], 'iq_created_at_idx'],
            ['corporate_quotes', ['created_at'], 'cq_created_at_idx'],
        ];

        foreach ($indexes as [$table, $columns, $name]) {
            if (Schema::hasTable($table) && ! Schema::hasIndex($table, $name)) {
                Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
                    $blueprint->index($columns, $name);
                });
            }
        }
    }

    public function down(): void
    {
        $indexes = [
            ['detail_individual_quotes', 'diq_quote_plan_idx'],
            ['affiliations', 'aff_individual_quote_idx'],
            ['affiliation_corporates', 'affc_corporate_quote_idx'],
            ['individual_quotes', 'iq_created_at_idx'],
            ['corporate_quotes', 'cq_created_at_idx'],
        ];

        foreach ($indexes as [$table, $name]) {
            if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
                Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                    $blueprint->dropIndex($name);
                });
            }
        }

        Schema::dropIfExists('quote_deletion_logs');

        if (Schema::hasColumn('individual_quotes', 'anulated_at')) {
            Schema::table('individual_quotes', function (Blueprint $table): void {
                $table->dropColumn('anulated_at');
            });
        }
    }
};
