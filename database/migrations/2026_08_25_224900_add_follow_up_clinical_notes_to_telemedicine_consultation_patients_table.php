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

        if (! Schema::hasColumn('telemedicine_consultation_patients', 'current_illness_history')) {
            Schema::table('telemedicine_consultation_patients', function (Blueprint $table): void {
                $table->longText('current_illness_history')->nullable()->after('cuestion_5');
            });
        }

        if (! Schema::hasColumn('telemedicine_consultation_patients', 'patient_evolution')) {
            Schema::table('telemedicine_consultation_patients', function (Blueprint $table): void {
                $after = Schema::hasColumn('telemedicine_consultation_patients', 'current_illness_history')
                    ? 'current_illness_history'
                    : 'cuestion_5';

                $table->longText('patient_evolution')->nullable()->after($after);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('telemedicine_consultation_patients')) {
            return;
        }

        Schema::table('telemedicine_consultation_patients', function (Blueprint $table): void {
            $columns = [];

            if (Schema::hasColumn('telemedicine_consultation_patients', 'patient_evolution')) {
                $columns[] = 'patient_evolution';
            }

            if (Schema::hasColumn('telemedicine_consultation_patients', 'current_illness_history')) {
                $columns[] = 'current_illness_history';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
