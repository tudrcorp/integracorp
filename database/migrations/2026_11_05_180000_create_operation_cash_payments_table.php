<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos de contado de facturas de proveedores (Operaciones → Cuentas por pagar): el pago
 * registrado al cargar la factura de una orden de servicio. Deja la cuenta por pagar PAGADA;
 * al reversarlo se marca REVERSADO (nunca se borra) y la cuenta vuelve a como estaba, gracias
 * a la foto guardada en `payable_snapshot`.
 *
 * Idempotente: se puede aplicar con --path aunque la tabla ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operation_cash_payments')) {
            return;
        }

        Schema::create('operation_cash_payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('operation_service_order_id');
            $table->unsignedBigInteger('operation_accounts_payable_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('supplier_rif', 40)->nullable();
            $table->string('invoice_number', 60)->nullable();
            $table->string('invoice_receipt_number', 60)->nullable();
            $table->decimal('invoice_amount', 15, 4);
            $table->string('invoice_currency', 3);
            $table->date('payment_date');
            $table->string('payment_reference', 120);
            $table->string('payment_currency', 3);
            $table->decimal('payment_amount', 15, 4);
            $table->decimal('bcv_rate', 16, 4)->nullable();
            $table->decimal('amount_in_invoice_currency', 15, 4);
            $table->string('bank_scope', 15);
            $table->string('bank');
            $table->string('receipt_path');
            $table->string('status', 15);
            $table->json('payable_snapshot');
            $table->unsignedBigInteger('registered_by_user_id')->nullable();
            $table->string('registered_by_name')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversed_by_user_id')->nullable();
            $table->string('reversed_by_name')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->index(['operation_accounts_payable_id', 'status'], 'ocp_payable_status_idx');
            $table->index(['operation_service_order_id'], 'ocp_order_idx');
            $table->index(['status', 'payment_date'], 'ocp_status_date_idx');
            $table->index(['supplier_id'], 'ocp_supplier_idx');
            $table->index(['payment_reference'], 'ocp_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_cash_payments');
    }
};
