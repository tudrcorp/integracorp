<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operation_service_orders')) {
            return;
        }

        Schema::table('operation_service_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('operation_service_orders', 'appointment_at')) {
                $table->timestamp('appointment_at')->nullable()->after('approved_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('operation_service_orders')) {
            return;
        }

        Schema::table('operation_service_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('operation_service_orders', 'appointment_at')) {
                $table->dropColumn('appointment_at');
            }
        });
    }
};
