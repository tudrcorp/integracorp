<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('telemedicine_cases', 'telemedicine_doctor_id')) {
            Schema::table('telemedicine_cases', function (Blueprint $table): void {
                $table->integer('telemedicine_doctor_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('telemedicine_cases', 'telemedicine_doctor_id')) {
            Schema::table('telemedicine_cases', function (Blueprint $table): void {
                $table->integer('telemedicine_doctor_id')->nullable(false)->change();
            });
        }
    }
};
