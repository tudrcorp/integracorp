<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Analistas que ya cerraron el aviso de «pedido aceptado por Farmadoc». Cada uno
 * cierra el suyo: el aviso sigue apareciendo a los demás.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('farmadoc_order_acceptance_dismissals')) {
            return;
        }

        Schema::create('farmadoc_order_acceptance_dismissals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('farmadoc_order_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('dismissed_at')->nullable();

            $table->unique(['farmadoc_order_id', 'user_id'], 'fdoad_order_user_unique');
            $table->index('user_id', 'fdoad_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmadoc_order_acceptance_dismissals');
    }
};
