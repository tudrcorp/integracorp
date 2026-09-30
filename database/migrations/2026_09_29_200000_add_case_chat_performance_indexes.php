<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices del chat de seguimiento de casos: la lista filtra por estado y ordena por última
 * actividad (y por proveedor en Telemedicina); la conversación pide los últimos mensajes por id.
 */
return new class extends Migration
{
    /**
     * @return array<string, array{table: string, columns: list<string>}>
     */
    private function indexes(): array
    {
        return [
            'telemedicine_cases_status_updated_at_index' => ['table' => 'telemedicine_cases', 'columns' => ['status', 'updated_at']],
            'telemedicine_cases_status_supplier_id_index' => ['table' => 'telemedicine_cases', 'columns' => ['status', 'supplier_id']],
            'telemedicine_case_messages_case_id_id_index' => ['table' => 'telemedicine_case_messages', 'columns' => ['telemedicine_case_id', 'id']],
        ];
    }

    public function up(): void
    {
        foreach ($this->indexes() as $name => $definition) {
            if (! Schema::hasTable($definition['table']) || Schema::hasIndex($definition['table'], $name)) {
                continue;
            }

            Schema::table($definition['table'], function (Blueprint $table) use ($name, $definition): void {
                $table->index($definition['columns'], $name);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes() as $name => $definition) {
            if (! Schema::hasTable($definition['table']) || ! Schema::hasIndex($definition['table'], $name)) {
                continue;
            }

            Schema::table($definition['table'], function (Blueprint $table) use ($name): void {
                $table->dropIndex($name);
            });
        }
    }
};
