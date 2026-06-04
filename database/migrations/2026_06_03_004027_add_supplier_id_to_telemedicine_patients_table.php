<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemedicine_patients', function (Blueprint $table): void {
            if (! Schema::hasColumn('telemedicine_patients', 'supplier_id')) {
                $table->foreignId('supplier_id')
                    ->nullable()
                    ->after('managed_by')
                    ->constrained('suppliers')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('telemedicine_patients', function (Blueprint $table): void {
            if (Schema::hasColumn('telemedicine_patients', 'supplier_id')) {
                $table->dropConstrainedForeignId('supplier_id');
            }
        });
    }
};
