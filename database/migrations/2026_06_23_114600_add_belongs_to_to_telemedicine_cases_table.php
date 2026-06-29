<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('telemedicine_cases', 'belongs_to')) {
            return;
        }

        Schema::table('telemedicine_cases', function (Blueprint $table) {
            $table->string('belongs_to')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('telemedicine_cases', 'belongs_to')) {
            return;
        }

        Schema::table('telemedicine_cases', function (Blueprint $table) {
            $table->dropColumn('belongs_to');
        });
    }
};
