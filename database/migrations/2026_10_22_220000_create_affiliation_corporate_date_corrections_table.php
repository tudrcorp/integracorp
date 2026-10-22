<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrección de fecha de emisión y vigencia de afiliaciones corporativas.
 *
 * Cada corrección guarda la foto exacta, antes y después, de lo que movió (afiliación,
 * avisos POR PAGAR, planificación anual, renovaciones abiertas y fechas informativas de
 * los pagos). La reversión restituye la foto previa solo si nadie cambió esas filas.
 *
 * Aditiva e idempotente: se puede aplicar con `--path` aunque ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('affiliation_corporate_date_corrections')) {
            return;
        }

        Schema::create('affiliation_corporate_date_corrections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('affiliation_corporate_id');
            $table->string('affiliation_code', 100);
            $table->string('status', 20);
            $table->string('activated_at_before', 30)->nullable();
            $table->string('effective_date_before', 30)->nullable();
            $table->string('new_date', 30);
            $table->text('reason');
            $table->json('snapshot_before');
            $table->json('snapshot_after');
            $table->json('summary')->nullable();
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->string('applied_by_name', 255);
            $table->timestamp('applied_at');
            $table->unsignedBigInteger('rolled_back_by')->nullable();
            $table->string('rolled_back_by_name', 255)->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->text('rollback_note')->nullable();
            $table->json('rollback_summary')->nullable();
            $table->timestamps();

            $table->index(['affiliation_corporate_id', 'status'], 'acdc_affiliation_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliation_corporate_date_corrections');
    }
};
