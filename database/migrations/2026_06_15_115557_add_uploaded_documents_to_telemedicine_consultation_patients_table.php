<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('telemedicine_consultation_patients', 'uploaded_documents')) {
            return;
        }

        Schema::table('telemedicine_consultation_patients', function (Blueprint $table) {
            $table->json('uploaded_documents')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('telemedicine_consultation_patients', 'uploaded_documents')) {
            return;
        }

        Schema::table('telemedicine_consultation_patients', function (Blueprint $table) {
            $table->dropColumn('uploaded_documents');
        });
    }
};
