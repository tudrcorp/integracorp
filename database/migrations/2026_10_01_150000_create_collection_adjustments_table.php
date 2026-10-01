<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de ajustes manuales de cuotas de cobranza («Ajustar cuota» en Gestión de
 * Cobranza): quién cambió qué, cuándo, desde dónde y por qué.
 *
 * Sin llave foránea a `collections` a propósito: si una cuota se borra (por ejemplo
 * al eliminar su venta), su historial de ajustes debe sobrevivir. Por eso guarda
 * también el código de afiliación y el número de aviso.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('collection_adjustments')) {
            return;
        }

        Schema::create('collection_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('collection_id')->index();
            $table->string('affiliation_code')->nullable()->index();
            $table->string('collection_invoice_number')->nullable();
            $table->uuid('batch_uuid')->nullable()->index();
            $table->string('mode', 20);
            $table->json('changes');
            $table->text('reason');
            $table->unsignedBigInteger('performed_by_id')->nullable()->index();
            $table->string('performed_by_name')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_adjustments');
    }
};
