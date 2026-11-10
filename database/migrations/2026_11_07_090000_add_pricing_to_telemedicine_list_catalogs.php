<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Costo y % de utilidad en los catálogos de laboratorios, estudios y especialistas: precio al
 * paciente = costo × (1 + % / 100) cuando el servicio NO está cubierto. NULL = sin definir.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['telemedicine_list_laboratories', 'telemedicine_list_studies', 'telemedicine_list_specialists'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'cost')) {
                    $table->decimal('cost', 12, 2)->nullable();
                }

                if (! Schema::hasColumn($tableName, 'profit_percentage')) {
                    $table->decimal('profit_percentage', 8, 2)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            foreach (['cost', 'profit_percentage'] as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
    }
};
