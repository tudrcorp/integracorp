<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «N° de recibo» en la factura de la orden de servicio y en su cuenta por pagar. La factura
 * puede traer número de factura, de recibo o ambos (al menos uno), así que el número de
 * factura de la cuenta por pagar deja de ser obligatorio.
 *
 * Idempotente: se puede aplicar con --path aunque ya se haya aplicado.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['operation_service_orders' => 'invoice_number', 'operation_accounts_payables' => 'invoice_number'] as $table => $after) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'invoice_receipt_number')) {
                Schema::table($table, function (Blueprint $blueprint) use ($after, $table): void {
                    $blueprint->string('invoice_receipt_number', 60)->nullable()->after($after);
                    $blueprint->index('invoice_receipt_number', $table.'_receipt_number_index');
                });
            }
        }

        if (Schema::hasTable('operation_accounts_payables') && Schema::hasColumn('operation_accounts_payables', 'invoice_number')) {
            Schema::table('operation_accounts_payables', function (Blueprint $blueprint): void {
                /** Mismo tipo y largo de antes; solo deja de ser obligatorio. */
                $blueprint->string('invoice_number', 60)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['operation_service_orders', 'operation_accounts_payables'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'invoice_receipt_number')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                    $blueprint->dropIndex($table.'_receipt_number_index');
                    $blueprint->dropColumn('invoice_receipt_number');
                });
            }
        }
    }
};
