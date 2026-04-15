<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tdev_reports', function (Blueprint $table) {
            $table->longText('observaciones_proceso')->nullable()->after('observaciones');
        });
    }

    public function down(): void
    {
        Schema::table('tdev_reports', function (Blueprint $table) {
            $table->dropColumn('observaciones_proceso');
        });
    }
};
