<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sobres de handoff que n8n entrega al CRM. Una fila por caso, sin relación
 * con afiliaciones ni con la cola de trabajos del resto del sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_handoff_envelopes')) {
            return;
        }

        Schema::create('crm_handoff_envelopes', function (Blueprint $table) {
            $table->id();
            $table->string('handoff_id', 32)->unique();
            $table->string('phone', 20);
            $table->string('area', 40)->nullable();
            $table->text('motivo')->nullable();
            $table->json('payload');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index('phone');
            $table->index(['area', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_handoff_envelopes');
    }
};
