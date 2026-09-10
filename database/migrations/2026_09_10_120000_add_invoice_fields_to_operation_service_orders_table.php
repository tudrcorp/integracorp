<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_service_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('operation_service_orders', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('status_payment');
            }

            if (! Schema::hasColumn('operation_service_orders', 'invoice_date')) {
                $table->date('invoice_date')->nullable()->after('invoice_number');
            }

            if (! Schema::hasColumn('operation_service_orders', 'invoice_amount_usd')) {
                $table->decimal('invoice_amount_usd', 15, 4)->nullable()->after('invoice_date');
            }

            if (! Schema::hasColumn('operation_service_orders', 'invoice_amount_ves')) {
                $table->decimal('invoice_amount_ves', 15, 4)->nullable()->after('invoice_amount_usd');
            }

            if (! Schema::hasColumn('operation_service_orders', 'invoice_file_path')) {
                $table->string('invoice_file_path')->nullable()->after('invoice_amount_ves');
            }

            if (! Schema::hasColumn('operation_service_orders', 'invoice_uploaded_by')) {
                $table->string('invoice_uploaded_by')->nullable()->after('invoice_file_path');
            }

            if (! Schema::hasColumn('operation_service_orders', 'invoice_uploaded_at')) {
                $table->timestamp('invoice_uploaded_at')->nullable()->after('invoice_uploaded_by');
            }
        });

        if (
            Schema::hasColumn('operation_service_orders', 'invoice_number')
            && ! Schema::hasIndex('operation_service_orders', 'operation_service_orders_invoice_number_index')
        ) {
            Schema::table('operation_service_orders', function (Blueprint $table): void {
                $table->index('invoice_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('operation_service_orders', 'operation_service_orders_invoice_number_index')) {
            Schema::table('operation_service_orders', function (Blueprint $table): void {
                $table->dropIndex('operation_service_orders_invoice_number_index');
            });
        }

        Schema::table('operation_service_orders', function (Blueprint $table): void {
            foreach ([
                'invoice_number',
                'invoice_date',
                'invoice_amount_usd',
                'invoice_amount_ves',
                'invoice_file_path',
                'invoice_uploaded_by',
                'invoice_uploaded_at',
            ] as $column) {
                if (Schema::hasColumn('operation_service_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
