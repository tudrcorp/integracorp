<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_service_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('operation_service_orders', 'status_payment')) {
                $table->string('status_payment')->nullable()->after('payment_method');
            }
        });

        if (Schema::hasColumn('operation_service_orders', 'status_payment')) {
            DB::table('operation_service_orders')
                ->whereNull('status_payment')
                ->whereNotNull('payment_method')
                ->where('payment_method', '!=', '')
                ->update(['status_payment' => 'PAGADO']);

            DB::table('operation_service_orders')
                ->whereNull('status_payment')
                ->update(['status_payment' => 'PENDIENTE']);
        }
    }

    public function down(): void
    {
        Schema::table('operation_service_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('operation_service_orders', 'status_payment')) {
                $table->dropColumn('status_payment');
            }
        });
    }
};
