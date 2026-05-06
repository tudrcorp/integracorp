<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('download_zones', function (Blueprint $table) {
            $table->unsignedInteger('position')->nullable()->after('zone_id');
            $table->index(['zone_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('download_zones', function (Blueprint $table) {
            $table->dropIndex(['zone_id', 'position']);
            $table->dropColumn('position');
        });
    }
};
