<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El diagnóstico de la consulta (longtext) se copia a la coordinación.
 * VARCHAR(255) rechaza impresiones clínicas de más de 255 caracteres y
 * revierte el alta del servicio (1406 Data too long).
 */
return new class extends Migration
{
    private const TABLE = 'operation_coordination_services';

    private const COLUMN = 'symptoms_diagnosis';

    private const COMMENT = 'Síntomas y diagnóstico';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        if ($this->isTextColumn()) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->text(self::COLUMN)
                ->comment(self::COMMENT)
                ->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        if (! $this->isTextColumn()) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->string(self::COLUMN)
                ->comment(self::COMMENT)
                ->change();
        });
    }

    private function isTextColumn(): bool
    {
        return in_array(
            strtolower(Schema::getColumnType(self::TABLE, self::COLUMN)),
            ['text', 'mediumtext', 'longtext'],
            true,
        );
    }
};
