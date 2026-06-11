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
        Schema::table('telemedicine_patient_medications', function (Blueprint $table): void {
            if (! Schema::hasColumn('telemedicine_patient_medications', 'is_covered')) {
                $table->boolean('is_covered')->nullable()->after('operation_inventory_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telemedicine_patient_medications', function (Blueprint $table): void {
            if (Schema::hasColumn('telemedicine_patient_medications', 'is_covered')) {
                $table->dropColumn('is_covered');
            }
        });
    }
};
