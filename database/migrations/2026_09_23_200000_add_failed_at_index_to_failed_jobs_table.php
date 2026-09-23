<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colas y errores filtra, cuenta y limpia los fallidos por fecha: sin índice,
 * cada consulta recorre la tabla completa (payload y excepción incluidos).
 */
return new class extends Migration
{
    private const INDEX = 'failed_jobs_failed_at_index';

    public function up(): void
    {
        if (! Schema::hasTable('failed_jobs') || Schema::hasIndex('failed_jobs', self::INDEX)) {
            return;
        }

        Schema::table('failed_jobs', function (Blueprint $table): void {
            $table->index('failed_at', self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('failed_jobs') || ! Schema::hasIndex('failed_jobs', self::INDEX)) {
            return;
        }

        Schema::table('failed_jobs', function (Blueprint $table): void {
            $table->dropIndex(self::INDEX);
        });
    }
};
