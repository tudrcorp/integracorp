<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('farmadoc_orders')) {
            return;
        }

        Schema::table('farmadoc_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('farmadoc_orders', 'farmadoc_service_order_id')) {
                $table->unsignedBigInteger('farmadoc_service_order_id')->nullable()->after('farmadoc_order_id');
            }

            if (! Schema::hasColumn('farmadoc_orders', 'service_order_number')) {
                $table->string('service_order_number', 100)->nullable()->after('farmadoc_service_order_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmadoc_orders')) {
            return;
        }

        Schema::table('farmadoc_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('farmadoc_orders', 'service_order_number')) {
                $table->dropColumn('service_order_number');
            }

            if (Schema::hasColumn('farmadoc_orders', 'farmadoc_service_order_id')) {
                $table->dropColumn('farmadoc_service_order_id');
            }
        });
    }
};
