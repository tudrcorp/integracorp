<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice (caso, id) en consultas.
 *
 * El dashboard de telemedicina resuelve «la última consulta del caso» con
 * `max(id) … where telemedicine_case_id = …` en cada refresco (cada 5 s) para
 * ordenar por próximo seguimiento; sin índice la subconsulta recorría la tabla.
 */
return new class extends Migration
{
    private const TABLE = 'telemedicine_consultation_patients';

    private const INDEX = 'tcp_case_id_index';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, 'telemedicine_case_id')) {
            return;
        }

        if (Schema::hasIndex(self::TABLE, self::INDEX) || Schema::hasIndex(self::TABLE, ['telemedicine_case_id', 'id'])) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index(['telemedicine_case_id', 'id'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasIndex(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropIndex(self::INDEX);
        });
    }
};
