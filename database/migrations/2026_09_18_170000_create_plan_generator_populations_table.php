<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Padrón de la pre-afiliación corporativa de un plan generado.
 *
 * Replica el patrón que ya existe en Negocios: el padrón se importa contra la
 * cotización (`corporate_quote_data`) y al crear la afiliación se copia a
 * `affiliate_corporates`. Acá la cotización es el plan generado, así que la
 * población vive colgada de él: sobrevive al formulario de afiliación, se puede
 * revisar y reimportar, y queda auditable.
 *
 * `plan_generators.population_import_id` apunta al último import encolado para
 * poder mostrar su progreso y no dejar crear la afiliación a medio poblar.
 *
 * Mismos tipos laxos (varchar) que `corporate_quote_data`: el CSV del cliente
 * llega con formatos de fecha y cédula muy variados y el parseo vive en el
 * importador, no en el esquema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_generator_populations')) {
            Schema::create('plan_generator_populations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('plan_generator_id');
                $table->unsignedBigInteger('import_id')->nullable();
                $table->string('last_name');
                $table->string('first_name');
                $table->string('nro_identificacion');
                $table->string('birth_date')->nullable();
                $table->string('age')->nullable();
                $table->string('sex')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('condition_medical')->nullable();
                $table->string('initial_date')->nullable();
                $table->string('position_company')->nullable();
                $table->string('address')->nullable();
                $table->string('full_name_emergency')->nullable();
                $table->string('phone_emergency')->nullable();
                $table->timestamps();

                $table->index('plan_generator_id');
                $table->index('import_id');
            });
        }

        if (! Schema::hasColumn('plan_generators', 'population_import_id')) {
            Schema::table('plan_generators', function (Blueprint $table): void {
                $table->unsignedBigInteger('population_import_id')->nullable()->after('plan_page_number');
                $table->index('population_import_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('plan_generators', 'population_import_id')) {
            Schema::table('plan_generators', function (Blueprint $table): void {
                $table->dropIndex(['population_import_id']);
                $table->dropColumn('population_import_id');
            });
        }

        Schema::dropIfExists('plan_generator_populations');
    }
};
