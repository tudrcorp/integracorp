<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telemedicine_consultation_patients')) {
            return;
        }

        if (! Schema::hasColumn('telemedicine_consultation_patients', 'uploaded_documents')) {
            Schema::table('telemedicine_consultation_patients', function (Blueprint $table): void {
                $table->json('uploaded_documents')->nullable();
            });

            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE telemedicine_consultation_patients MODIFY uploaded_documents JSON NULL');

            return;
        }

        Schema::table('telemedicine_consultation_patients', function (Blueprint $table): void {
            $table->json('uploaded_documents')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('telemedicine_consultation_patients')) {
            return;
        }

        if (! Schema::hasColumn('telemedicine_consultation_patients', 'uploaded_documents')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE telemedicine_consultation_patients MODIFY uploaded_documents VARCHAR(100) NULL');

            return;
        }

        Schema::table('telemedicine_consultation_patients', function (Blueprint $table): void {
            $table->string('uploaded_documents', 100)->nullable()->change();
        });
    }
};
