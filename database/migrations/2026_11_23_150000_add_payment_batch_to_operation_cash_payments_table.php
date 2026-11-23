<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrupa los registros de un pago de contado masivo (una factura de varias
 * órdenes): se reversan todos juntos. Nulo en los pagos de una sola orden.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operation_cash_payments') || Schema::hasColumn('operation_cash_payments', 'payment_batch')) {
            return;
        }

        Schema::table('operation_cash_payments', function (Blueprint $table): void {
            $table->uuid('payment_batch')->nullable()->after('operation_accounts_payable_id');
            $table->index('payment_batch', 'operation_cash_payments_payment_batch_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('operation_cash_payments') || ! Schema::hasColumn('operation_cash_payments', 'payment_batch')) {
            return;
        }

        Schema::table('operation_cash_payments', function (Blueprint $table): void {
            $table->dropIndex('operation_cash_payments_payment_batch_index');
            $table->dropColumn('payment_batch');
        });
    }
};
