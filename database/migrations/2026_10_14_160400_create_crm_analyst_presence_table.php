<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de cada analista en la bandeja: disponible, ocupado o ausente, y cuándo se le vio.
 * Una fila por persona. Sin la fila, el analista cuenta como disponible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_analyst_presence')) {
            return;
        }

        Schema::create('crm_analyst_presence', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('status', 16)->default('available');
            $table->timestamp('status_at')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_analyst_presence');
    }
};
