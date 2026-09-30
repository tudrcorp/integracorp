<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'service_providers_name_unique';

    public function up(): void
    {
        if (! Schema::hasTable('service_providers') || Schema::hasIndex('service_providers', self::INDEX)) {
            return;
        }

        $duplicates = DB::table('service_providers')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('service_providers tiene nombres repetidos: '.$duplicates->implode(', ').'. Deben depurarse antes de crear el índice único.');
        }

        Schema::table('service_providers', function (Blueprint $table): void {
            $table->unique('name', self::INDEX);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('service_providers') && Schema::hasIndex('service_providers', self::INDEX)) {
            Schema::table('service_providers', function (Blueprint $table): void {
                $table->dropUnique(self::INDEX);
            });
        }
    }
};
