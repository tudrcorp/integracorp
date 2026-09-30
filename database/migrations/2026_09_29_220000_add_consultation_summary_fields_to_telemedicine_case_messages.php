<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensajes del sistema en el chat de casos: el resumen que INTEGRACORP publica al registrar una
 * consulta. `telemedicine_consultation_patient_id` es único para que un reintento nunca lo duplique.
 */
return new class extends Migration
{
    private const TABLE = 'telemedicine_case_messages';

    private const UNIQUE = 'telemedicine_case_messages_consultation_unique';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table): void {
            if (! Schema::hasColumn(self::TABLE, 'kind')) {
                $table->string('kind', 40)->default('message')->after('body');
            }

            if (! Schema::hasColumn(self::TABLE, 'telemedicine_consultation_patient_id')) {
                $table->unsignedBigInteger('telemedicine_consultation_patient_id')->nullable()->after('kind');
            }

            if (! Schema::hasColumn(self::TABLE, 'meta')) {
                $table->json('meta')->nullable()->after('telemedicine_consultation_patient_id');
            }
        });

        if (! Schema::hasIndex(self::TABLE, self::UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique('telemedicine_consultation_patient_id', self::UNIQUE);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex(self::TABLE, self::UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::UNIQUE);
            });
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            foreach (['meta', 'telemedicine_consultation_patient_id', 'kind'] as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
