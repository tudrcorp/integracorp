<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de cambios del tema de Integracorp. El tema vigente es el de la
 * última fila; sin filas rige el tema original. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_theme_changes')) {
            return;
        }

        Schema::create('system_theme_changes', function (Blueprint $table): void {
            $table->id();
            $table->string('theme', 30);
            $table->string('previous_theme', 30);
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->string('changed_by_name', 150)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_theme_changes');
    }
};
