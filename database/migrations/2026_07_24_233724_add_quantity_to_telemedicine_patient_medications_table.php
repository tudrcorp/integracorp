<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemedicine_patient_medications', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable()->after('duration');
        });
    }

    public function down(): void
    {
        Schema::table('telemedicine_patient_medications', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
