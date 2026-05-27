<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_service_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('operation_service_orders', 'doctor_nurse_id')) {
                $table->foreignId('doctor_nurse_id')
                    ->nullable()
                    ->after('supplier_id')
                    ->constrained('doctor_nurses')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('operation_service_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('operation_service_orders', 'doctor_nurse_id')) {
                $table->dropConstrainedForeignId('doctor_nurse_id');
            }
        });
    }
};
