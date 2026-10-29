<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imagen de la tarjeta del upgrade en el cotizador individual (Negocios). Opcional: sin imagen la
 * tarjeta usa un fondo de color.
 *
 * Idempotente: se aplica con --path y puede correrse dos veces sin daño.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('upgrades') && ! Schema::hasColumn('upgrades', 'image_path')) {
            Schema::table('upgrades', function (Blueprint $table): void {
                $table->string('image_path', 500)->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('upgrades') && Schema::hasColumn('upgrades', 'image_path')) {
            Schema::table('upgrades', function (Blueprint $table): void {
                $table->dropColumn('image_path');
            });
        }
    }
};
