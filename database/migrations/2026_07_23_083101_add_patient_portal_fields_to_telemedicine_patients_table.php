<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemedicine_patients', function (Blueprint $table): void {
            if (! Schema::hasColumn('telemedicine_patients', 'patient_portal_password')) {
                $table->string('patient_portal_password')
                    ->default('12345678')
                    ->after('email_contact');
            }

            if (! Schema::hasColumn('telemedicine_patients', 'patient_portal_authorized')) {
                $table->boolean('patient_portal_authorized')
                    ->default(true)
                    ->after('patient_portal_password');
            }
        });
    }

    public function down(): void
    {
        Schema::table('telemedicine_patients', function (Blueprint $table): void {
            if (Schema::hasColumn('telemedicine_patients', 'patient_portal_authorized')) {
                $table->dropColumn('patient_portal_authorized');
            }

            if (Schema::hasColumn('telemedicine_patients', 'patient_portal_password')) {
                $table->dropColumn('patient_portal_password');
            }
        });
    }
};
