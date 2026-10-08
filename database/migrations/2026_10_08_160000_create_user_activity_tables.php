<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actividad de usuarios (Negocios → Actividad de usuarios).
 *
 * - `user_activity_days`: un resumen por usuario y día (se conserva 12 meses).
 *   `minute_states` guarda la barra del día minuto a minuto (1440 caracteres:
 *   a activo, i inactivo, b en otra pestaña, . desconectado) y se vacía a los 90 días.
 * - `user_activity_events`: el recorrido paso a paso (se conserva 90 días).
 *
 * Idempotente: se puede aplicar con `--path` aunque una tabla ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_activity_days')) {
            Schema::create('user_activity_days', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->date('activity_date');
                $table->unsignedSmallInteger('first_minute')->nullable();
                $table->unsignedSmallInteger('last_minute')->nullable();
                $table->unsignedSmallInteger('online_minutes')->default(0);
                $table->unsignedSmallInteger('active_minutes')->default(0);
                $table->unsignedSmallInteger('idle_minutes')->default(0);
                $table->unsignedSmallInteger('background_minutes')->default(0);
                $table->unsignedInteger('page_views')->default(0);
                $table->unsignedInteger('actions')->default(0);
                $table->unsignedInteger('downloads')->default(0);
                $table->json('hourly_active')->nullable();
                $table->text('minute_states')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'activity_date'], 'uad_user_date_unique');
                $table->index('activity_date', 'uad_date_idx');
            });
        }

        if (! Schema::hasTable('user_activity_events')) {
            Schema::create('user_activity_events', function (Blueprint $table): void {
                $table->id();
                $table->char('event_key', 36);
                $table->unsignedBigInteger('user_id');
                $table->dateTime('occurred_at');
                $table->string('type', 20);
                $table->string('label', 255);
                $table->string('panel', 60)->nullable();
                $table->string('page', 255)->nullable();
                $table->string('path', 300)->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->unsignedSmallInteger('status')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->unique('event_key', 'uae_event_key_unique');
                $table->index(['user_id', 'occurred_at'], 'uae_user_occurred_idx');
                $table->index('occurred_at', 'uae_occurred_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_events');
        Schema::dropIfExists('user_activity_days');
    }
};
