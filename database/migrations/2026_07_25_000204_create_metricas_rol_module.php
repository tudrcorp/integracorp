<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('rols')
            ->where('name', 'METRICAS')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('rols')->insert([
            'name' => 'METRICAS',
            'description' => 'Métricas y KPI',
            'created_by' => 'INTEGRACORP',
            'updated_by' => 'INTEGRACORP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('rols')
            ->where('name', 'METRICAS')
            ->delete();
    }
};
