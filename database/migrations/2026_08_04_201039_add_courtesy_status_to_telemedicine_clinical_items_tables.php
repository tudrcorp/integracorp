<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function clinicalTables(): array
    {
        return [
            'telemedicine_patient_medications',
            'telemedicine_patient_labs',
            'telemedicine_patient_studies',
            'telemedicine_patient_specialties',
        ];
    }

    public function up(): void
    {
        foreach ($this->clinicalTables() as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'courtesy_status')) {
                    $table->string('courtesy_status')->nullable()->after('status');
                }

                if (! Schema::hasColumn($tableName, 'courtesy_reason')) {
                    $table->text('courtesy_reason')->nullable()->after('courtesy_status');
                }

                if (! Schema::hasColumn($tableName, 'courtesy_updated_at')) {
                    $table->timestamp('courtesy_updated_at')->nullable()->after('courtesy_reason');
                }

                if (! Schema::hasColumn($tableName, 'courtesy_updated_by')) {
                    $table->string('courtesy_updated_by')->nullable()->after('courtesy_updated_at');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->clinicalTables() as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $columns = array_values(array_filter([
                    Schema::hasColumn($tableName, 'courtesy_status') ? 'courtesy_status' : null,
                    Schema::hasColumn($tableName, 'courtesy_reason') ? 'courtesy_reason' : null,
                    Schema::hasColumn($tableName, 'courtesy_updated_at') ? 'courtesy_updated_at' : null,
                    Schema::hasColumn($tableName, 'courtesy_updated_by') ? 'courtesy_updated_by' : null,
                ]));

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
