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
        Schema::table('telemedicine_doctors', function (Blueprint $table) {
            $table->string('managed_by')->default('TDG')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telemedicine_doctors', function (Blueprint $table) {
            $table->dropColumn('managed_by');
        });
    }
};
