<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cotización de lo NO CUBIERTO que genera factura al paciente (10/11/2026).
 *
 * - La cotización guarda si genera factura y el responsable de pago elegido por el analista
 *   (paciente o grupo corporativo), con sus datos congelados al crearla.
 * - La factura nace al aprobar la cotización: guarda la cotización de origen y copia el
 *   responsable de pago. Una cotización solo tiene una factura.
 *
 * Aditiva e idempotente: se aplica con --path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_quote_generators', function (Blueprint $table): void {
            if (! Schema::hasColumn('operation_quote_generators', 'generates_invoice')) {
                $table->boolean('generates_invoice')->default(false)->after('is_courtesy');
            }

            if (! Schema::hasColumn('operation_quote_generators', 'invoice_payer_type')) {
                $table->string('invoice_payer_type', 20)->nullable()->after('generates_invoice');
            }

            if (! Schema::hasColumn('operation_quote_generators', 'invoice_payer_name')) {
                $table->string('invoice_payer_name', 255)->nullable()->after('invoice_payer_type');
            }

            if (! Schema::hasColumn('operation_quote_generators', 'invoice_payer_identification')) {
                $table->string('invoice_payer_identification', 60)->nullable()->after('invoice_payer_name');
            }

            if (! Schema::hasColumn('operation_quote_generators', 'invoice_payer_address')) {
                $table->string('invoice_payer_address', 500)->nullable()->after('invoice_payer_identification');
            }

            if (! Schema::hasColumn('operation_quote_generators', 'operation_medication_invoice_id')) {
                $table->unsignedBigInteger('operation_medication_invoice_id')->nullable()->after('invoice_payer_address');
                $table->index('operation_medication_invoice_id', 'oqg_invoice_idx');
            }
        });

        Schema::table('operation_medication_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('operation_medication_invoices', 'operation_quote_generator_id')) {
                $table->unsignedBigInteger('operation_quote_generator_id')->nullable()->after('operation_service_order_id');
                $table->unique('operation_quote_generator_id', 'omi_quote_unique');
            }

            if (! Schema::hasColumn('operation_medication_invoices', 'payer_type')) {
                $table->string('payer_type', 20)->nullable()->after('case_code');
            }

            if (! Schema::hasColumn('operation_medication_invoices', 'payer_name')) {
                $table->string('payer_name', 255)->nullable()->after('payer_type');
            }

            if (! Schema::hasColumn('operation_medication_invoices', 'payer_identification')) {
                $table->string('payer_identification', 60)->nullable()->after('payer_name');
            }

            if (! Schema::hasColumn('operation_medication_invoices', 'payer_address')) {
                $table->string('payer_address', 500)->nullable()->after('payer_identification');
            }
        });
    }

    public function down(): void
    {
        Schema::table('operation_medication_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('operation_medication_invoices', 'operation_quote_generator_id')) {
                $table->dropUnique('omi_quote_unique');
                $table->dropColumn('operation_quote_generator_id');
            }

            foreach (['payer_type', 'payer_name', 'payer_identification', 'payer_address'] as $column) {
                if (Schema::hasColumn('operation_medication_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('operation_quote_generators', function (Blueprint $table): void {
            if (Schema::hasColumn('operation_quote_generators', 'operation_medication_invoice_id')) {
                $table->dropIndex('oqg_invoice_idx');
                $table->dropColumn('operation_medication_invoice_id');
            }

            foreach (['generates_invoice', 'invoice_payer_type', 'invoice_payer_name', 'invoice_payer_identification', 'invoice_payer_address'] as $column) {
                if (Schema::hasColumn('operation_quote_generators', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
