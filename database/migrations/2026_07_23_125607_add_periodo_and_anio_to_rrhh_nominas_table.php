<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rrhh_nominas', function (Blueprint $table) {
            if (! Schema::hasColumn('rrhh_nominas', 'anio')) {
                $table->unsignedSmallInteger('anio')->nullable()->after('id');
            }

            if (! Schema::hasColumn('rrhh_nominas', 'periodo')) {
                $table->unsignedTinyInteger('periodo')->nullable()->after('anio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rrhh_nominas', function (Blueprint $table) {
            foreach (['periodo', 'anio'] as $column) {
                if (Schema::hasColumn('rrhh_nominas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
