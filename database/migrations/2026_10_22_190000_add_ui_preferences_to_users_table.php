<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preferencias de interfaz por usuario (por ahora: abrir registros en vista rápida).
 * Se guardan en el usuario y no en el navegador, para que lo acompañen en cualquier equipo.
 *
 * Aditiva e idempotente: se puede aplicar con `--path` aunque ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'ui_preferences')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->json('ui_preferences')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'ui_preferences')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('ui_preferences');
        });
    }
};
