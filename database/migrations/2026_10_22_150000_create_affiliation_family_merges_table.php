<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Punto de restauración de la unificación de grupos familiares individuales.
 *
 * Cada unificación guarda la foto exacta, antes y después, de cada fila que tocó
 * (afiliaciones, afiliados, cuotas, renovaciones y pacientes de telemedicina).
 * El rollback restituye la foto previa solo si nadie cambió esas filas después.
 *
 * Aditiva e idempotente: se puede aplicar con `--path` aunque ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('affiliation_family_merges')) {
            return;
        }

        Schema::create('affiliation_family_merges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('target_affiliation_id');
            $table->string('target_code', 100);
            $table->json('source_affiliation_ids');
            $table->json('source_codes');
            $table->unsignedBigInteger('titular_affiliate_id');
            $table->text('reason');
            $table->string('status', 20);
            $table->json('snapshot_before');
            $table->json('snapshot_after');
            $table->json('summary')->nullable();
            $table->unsignedBigInteger('merged_by')->nullable();
            $table->string('merged_by_name', 255);
            $table->timestamp('merged_at');
            $table->unsignedBigInteger('rolled_back_by')->nullable();
            $table->string('rolled_back_by_name', 255)->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->text('rollback_note')->nullable();
            $table->json('rollback_summary')->nullable();
            $table->timestamps();

            $table->index(['target_affiliation_id', 'status'], 'afm_target_status_idx');
            $table->index(['status', 'merged_at'], 'afm_status_merged_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliation_family_merges');
    }
};
