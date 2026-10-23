<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «CONSULTA PRESENCIAL» como servicio estándar de nivel 1 en Telemedicina. Idempotente:
 * si ya existe, no se duplica.
 */
return new class extends Migration
{
    private const NAME = 'CONSULTA PRESENCIAL';

    public function up(): void
    {
        if (! Schema::hasTable('telemedicine_service_lists')) {
            return;
        }

        $exists = DB::table('telemedicine_service_lists')
            ->where('name', self::NAME)
            ->where('level', '1')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('telemedicine_service_lists')->insert([
            'name' => self::NAME,
            'description' => 'Consulta presencial',
            'type' => 'MACRO',
            'level' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('telemedicine_service_lists')) {
            return;
        }

        $id = DB::table('telemedicine_service_lists')
            ->where('name', self::NAME)
            ->where('level', '1')
            ->value('id');

        if ($id === null) {
            return;
        }

        $references = [
            'telemedicine_consultation_patients' => ['telemedicine_service_list_id', 'telemedicine_service_list_drift_id'],
            'affiliate_clinical_service_usages' => ['telemedicine_service_list_id'],
            'benefit_clinical_settings' => ['telemedicine_service_list_id'],
            'plan_benefit_clinical_settings' => ['telemedicine_service_list_id'],
            'clinical_service_override_challenges' => ['telemedicine_service_list_id'],
        ];

        $inUse = false;

        foreach ($references as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column) && DB::table($table)->where($column, $id)->exists()) {
                    $inUse = true;
                }
            }
        }

        if (! $inUse) {
            DB::table('telemedicine_service_lists')->where('id', $id)->delete();
        }
    }
};
