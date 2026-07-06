<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $tables = [
        'telemedicine_patient_labs',
        'telemedicine_patient_studies',
        'telemedicine_patient_specialties',
    ];

    /**
     * @var array<int, string>
     */
    private array $columns = [
        'telemedicine_case_id',
        'telemedicine_doctor_id',
        'telemedicine_consultation_patient_id',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                foreach ($this->columns as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->integer($column)->nullable()->change();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                foreach ($this->columns as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->integer($column)->nullable(false)->change();
                    }
                }
            });
        }
    }
};
