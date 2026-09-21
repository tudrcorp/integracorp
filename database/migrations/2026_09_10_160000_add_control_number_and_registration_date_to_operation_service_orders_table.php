<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_service_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('operation_service_orders', 'invoice_control_number')) {
                $table->string('invoice_control_number', 60)->nullable()->after('invoice_number');
            }

            if (! Schema::hasColumn('operation_service_orders', 'invoice_registration_date')) {
                $table->date('invoice_registration_date')->nullable()->after('invoice_date');
            }
        });

        if (
            Schema::hasColumn('operation_service_orders', 'invoice_control_number')
            && ! Schema::hasIndex('operation_service_orders', 'operation_service_orders_invoice_control_number_index')
        ) {
            Schema::table('operation_service_orders', function (Blueprint $table): void {
                $table->index('invoice_control_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('operation_service_orders', 'operation_service_orders_invoice_control_number_index')) {
            Schema::table('operation_service_orders', function (Blueprint $table): void {
                $table->dropIndex('operation_service_orders_invoice_control_number_index');
            });
        }

        Schema::table('operation_service_orders', function (Blueprint $table): void {
            foreach (['invoice_control_number', 'invoice_registration_date'] as $column) {
                if (Schema::hasColumn('operation_service_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
