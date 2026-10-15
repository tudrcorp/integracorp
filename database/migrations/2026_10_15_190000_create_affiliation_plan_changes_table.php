<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cambios de plan de afiliaciones individuales (plan, cobertura, frecuencia y rangos de edad).
 *
 * Cada fila guarda el estado anterior completo (afiliación, afiliados, cuotas, tarifa de la
 * empresa aliada) y lo que se anuló y creó en la cobranza: trazabilidad para Negocios y
 * Administración, y base para revertir el cambio con exactitud si hiciera falta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('affiliation_plan_changes')) {
            return;
        }

        Schema::create('affiliation_plan_changes', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_uuid')->index();
            $table->foreignId('affiliation_id')->constrained('affiliations', indexName: 'aff_plan_changes_affiliation_fk')->restrictOnDelete();
            $table->string('affiliation_code', 60)->nullable()->index();
            $table->unsignedBigInteger('previous_plan_id')->nullable();
            $table->unsignedBigInteger('new_plan_id');
            $table->unsignedBigInteger('previous_coverage_id')->nullable();
            $table->unsignedBigInteger('new_coverage_id')->nullable();
            $table->string('previous_frequency', 20)->nullable();
            $table->string('new_frequency', 20);
            $table->decimal('previous_fee_anual', 12, 2)->default(0);
            $table->decimal('new_fee_anual', 12, 2)->default(0);
            $table->decimal('previous_total_amount', 12, 2)->default(0);
            $table->decimal('new_total_amount', 12, 2)->default(0);
            $table->json('affiliates');
            $table->json('cancelled_collections')->nullable();
            $table->json('created_collections')->nullable();
            $table->json('updated_collections')->nullable();
            $table->json('snapshot');
            $table->string('reason', 500)->nullable();
            $table->foreignId('performed_by_id')->nullable()->constrained('users', indexName: 'aff_plan_changes_user_fk')->nullOnDelete();
            $table->string('performed_by_name')->nullable();
            $table->string('performed_from', 30)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliation_plan_changes');
    }
};
