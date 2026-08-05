<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operation_service_orders') && ! Schema::hasColumn('operation_service_orders', 'is_courtesy')) {
            Schema::table('operation_service_orders', function (Blueprint $table): void {
                $table->boolean('is_courtesy')->default(false)->after('status');
            });
        }

        if (Schema::hasTable('operation_quote_generators') && ! Schema::hasColumn('operation_quote_generators', 'is_courtesy')) {
            Schema::table('operation_quote_generators', function (Blueprint $table): void {
                $table->boolean('is_courtesy')->default(false)->after('is_cash');
            });
        }

        if (Schema::hasTable('operation_accounts_receivables') && ! Schema::hasColumn('operation_accounts_receivables', 'is_courtesy')) {
            Schema::table('operation_accounts_receivables', function (Blueprint $table): void {
                $table->boolean('is_courtesy')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('operation_service_orders') && Schema::hasColumn('operation_service_orders', 'is_courtesy')) {
            Schema::table('operation_service_orders', function (Blueprint $table): void {
                $table->dropColumn('is_courtesy');
            });
        }

        if (Schema::hasTable('operation_quote_generators') && Schema::hasColumn('operation_quote_generators', 'is_courtesy')) {
            Schema::table('operation_quote_generators', function (Blueprint $table): void {
                $table->dropColumn('is_courtesy');
            });
        }

        if (Schema::hasTable('operation_accounts_receivables') && Schema::hasColumn('operation_accounts_receivables', 'is_courtesy')) {
            Schema::table('operation_accounts_receivables', function (Blueprint $table): void {
                $table->dropColumn('is_courtesy');
            });
        }
    }
};
