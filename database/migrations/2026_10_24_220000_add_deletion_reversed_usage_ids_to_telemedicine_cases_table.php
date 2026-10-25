<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Punto de restauración de los cupos clínicos de un caso eliminado.
 *
 * Al eliminar un caso se devuelven al afiliado los cupos que consumió. Aquí se
 * guardan los ids exactos de esos consumos, para que la restauración vuelva a
 * descontar justamente esos (y no otros revertidos por otras causas). Aditiva e
 * idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telemedicine_cases') || Schema::hasColumn('telemedicine_cases', 'deletion_reversed_usage_ids')) {
            return;
        }

        Schema::table('telemedicine_cases', function (Blueprint $table): void {
            $table->json('deletion_reversed_usage_ids')->nullable()->after('logically_deleted_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('telemedicine_cases') || ! Schema::hasColumn('telemedicine_cases', 'deletion_reversed_usage_ids')) {
            return;
        }

        Schema::table('telemedicine_cases', function (Blueprint $table): void {
            $table->dropColumn('deletion_reversed_usage_ids');
        });
    }
};
