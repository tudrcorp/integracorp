<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operation_accounts_payables')) {
            return;
        }

        if (Schema::hasColumn('operation_accounts_payables', 'payment_receipt_path')) {
            return;
        }

        Schema::table('operation_accounts_payables', function (Blueprint $table): void {
            $table->string('payment_receipt_path')->nullable()->after('payment_amount_ves');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('operation_accounts_payables')) {
            return;
        }

        if (! Schema::hasColumn('operation_accounts_payables', 'payment_receipt_path')) {
            return;
        }

        Schema::table('operation_accounts_payables', function (Blueprint $table): void {
            $table->dropColumn('payment_receipt_path');
        });
    }
};
