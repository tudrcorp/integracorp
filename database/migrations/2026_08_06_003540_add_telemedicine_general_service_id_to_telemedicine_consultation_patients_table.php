<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telemedicine_consultation_patients')) {
            return;
        }

        if (! Schema::hasColumn('telemedicine_consultation_patients', 'telemedicine_general_service_id')) {
            Schema::table('telemedicine_consultation_patients', function (Blueprint $table): void {
                $table->unsignedBigInteger('telemedicine_general_service_id')
                    ->nullable()
                    ->after('telemedicine_service_list_drift_id');
            });
        }

        Schema::table('telemedicine_consultation_patients', function (Blueprint $table): void {
            $table->foreign('telemedicine_general_service_id', 'tcp_general_service_fk')
                ->references('id')
                ->on('telemedicine_general_services')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('telemedicine_consultation_patients')) {
            return;
        }

        if (! Schema::hasColumn('telemedicine_consultation_patients', 'telemedicine_general_service_id')) {
            return;
        }

        Schema::table('telemedicine_consultation_patients', function (Blueprint $table): void {
            $table->dropForeign('tcp_general_service_fk');
            $table->dropColumn('telemedicine_general_service_id');
        });
    }
};
