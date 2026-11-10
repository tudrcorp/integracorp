<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una cuenta por cobrar puede nacer de una factura de medicamentos al paciente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operation_accounts_receivables')
            || Schema::hasColumn('operation_accounts_receivables', 'operation_medication_invoice_id')) {
            return;
        }

        Schema::table('operation_accounts_receivables', function (Blueprint $table): void {
            $table->foreignId('operation_medication_invoice_id')->nullable()->after('operation_service_order_id')->index('oar_medication_invoice_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('operation_accounts_receivables', 'operation_medication_invoice_id')) {
            return;
        }

        Schema::table('operation_accounts_receivables', function (Blueprint $table): void {
            $table->dropIndex('oar_medication_invoice_idx');
            $table->dropColumn('operation_medication_invoice_id');
        });
    }
};
