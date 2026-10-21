<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traza de pagos pendientes eliminados desde «Historial de pagos».
 *
 * Guarda la foto completa de la fila borrada (`snapshot`), dónde quedó cada
 * comprobante archivado (`files`), la nota obligatoria y quién lo hizo. Con
 * esto el pago se puede reconstruir. Solo se inserta.
 *
 * Idempotente: se puede aplicar con `--path` aunque ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deleted_payment_vouchers')) {
            return;
        }

        Schema::create('deleted_payment_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->string('payment_type', 20);
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('affiliation_id')->nullable();
            $table->string('affiliation_code', 60)->nullable();
            $table->json('snapshot');
            $table->json('files')->nullable();
            $table->text('note');
            $table->string('panel', 40)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['payment_type', 'payment_id'], 'dpv_payment_idx');
            $table->index(['payment_type', 'affiliation_id', 'id'], 'dpv_affiliation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deleted_payment_vouchers');
    }
};
