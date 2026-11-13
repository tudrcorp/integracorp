<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos a los analistas TDG de Operaciones cuando el médico receta medicamentos
 * NO CUBIERTOS a un afiliado con Plan Especial de 30K, 40K o 50K, y quién cerró
 * cada aviso con «OK». Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operation_non_covered_medication_alerts')) {
            Schema::create('operation_non_covered_medication_alerts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('operation_coordination_service_id')->unique('oncma_coordination_unique');
                $table->unsignedBigInteger('telemedicine_case_id')->nullable();
                $table->unsignedBigInteger('telemedicine_patient_id')->nullable();
                $table->unsignedBigInteger('plan_id');
                $table->unsignedBigInteger('coverage_id');
                $table->decimal('coverage_amount', 12, 2);
                $table->string('patient_name', 255)->nullable();
                $table->string('patient_document', 60)->nullable();
                $table->string('plan_name', 150)->nullable();
                $table->json('medication_ids');
                $table->string('status', 12)->default('PENDIENTE');
                $table->unsignedBigInteger('managed_by_id')->nullable();
                $table->string('managed_by_name', 150)->nullable();
                $table->timestamp('managed_at')->nullable();
                $table->json('managed_medication_ids')->nullable();
                $table->string('created_by_name', 150)->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at'], 'oncma_status_created_idx');
            });
        }

        if (! Schema::hasTable('operation_non_covered_medication_alert_dismissals')) {
            Schema::create('operation_non_covered_medication_alert_dismissals', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('operation_non_covered_medication_alert_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamp('dismissed_at');

                $table->unique(['operation_non_covered_medication_alert_id', 'user_id'], 'oncmad_alert_user_unique');
                $table->index('user_id', 'oncmad_user_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_non_covered_medication_alert_dismissals');
        Schema::dropIfExists('operation_non_covered_medication_alerts');
    }
};
