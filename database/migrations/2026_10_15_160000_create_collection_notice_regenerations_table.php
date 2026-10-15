<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regeneraciones del aviso de cobro desde «Gestión de Cobranza».
 *
 * Lo que el analista edita (datos del cliente, fecha de emisión y período de cobro) vale solo
 * para el PDF: la cuota no se toca, porque alimenta Cobranza Por Mes y los reportes. Cada fila
 * deja constancia de quién regeneró, qué cambió respecto de la cuota, y precarga la siguiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('collection_notice_regenerations')) {
            return;
        }

        Schema::create('collection_notice_regenerations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('collections', indexName: 'coll_notice_regen_collection_fk')->cascadeOnDelete();
            $table->string('invoice_number', 60)->nullable();
            $table->json('client');
            $table->date('emission_date');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->json('changed_fields')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users', indexName: 'coll_notice_regen_user_fk')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->timestamps();

            $table->index(['collection_id', 'id'], 'coll_notice_regen_collection_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_notice_regenerations');
    }
};
