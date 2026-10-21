<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traza del uso clínico de los planes (Negocios → Planes → Uso clínico).
 *
 * Una fila por hecho: mapa guardado (con el antes/después de cada beneficio),
 * copia aplicada desde otro plan, beneficio vinculado o creado, servicio macro
 * creado. Es solo de escritura: nada la actualiza ni la borra.
 *
 * Idempotente: se puede aplicar con `--path` aunque ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plan_clinical_usage_events')) {
            return;
        }

        Schema::create('plan_clinical_usage_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedBigInteger('source_plan_id')->nullable();
            $table->string('event', 60);
            $table->text('description');
            $table->json('changes')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['plan_id', 'id'], 'pcue_plan_idx');
            $table->index(['event', 'created_at'], 'pcue_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_clinical_usage_events');
    }
};
