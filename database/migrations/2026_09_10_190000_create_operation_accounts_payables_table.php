<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operation_accounts_payables')) {
            return;
        }

        Schema::create('operation_accounts_payables', function (Blueprint $table): void {
            $table->id();

            // Documento
            $table->date('invoice_date');
            $table->date('invoice_registration_date');
            $table->string('invoice_number', 60);
            $table->string('invoice_control_number', 60)->nullable();

            /*
             * El proveedor se congela en la factura: si mañana se corrige la ficha
             * del proveedor, la factura ya emitida debe seguir mostrando el nombre
             * y el RIF con los que se registró.
             */
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('supplier_name');
            $table->string('supplier_rif', 40);

            $table->unsignedBigInteger('business_unit_id')->nullable();

            $table->decimal('invoice_amount', 15, 4);
            $table->string('invoice_currency', 3)->default('USD');

            // Pago
            $table->string('payment_status', 30)->default('PENDIENTE');
            $table->string('payment_reference', 120)->nullable();
            $table->date('payment_date')->nullable();
            $table->string('national_bank')->nullable();
            $table->string('international_bank')->nullable();
            $table->decimal('payment_amount_usd', 15, 4)->nullable();
            $table->decimal('payment_amount_ves', 15, 4)->nullable();

            $table->text('observations')->nullable();

            $table->string('created_by');
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->index('invoice_date');
            $table->index('invoice_registration_date');
            $table->index('invoice_number');
            $table->index('invoice_control_number');
            $table->index('payment_status');
            $table->index('payment_date');
            $table->index('supplier_id');
            $table->index('business_unit_id');
            $table->index('supplier_rif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_accounts_payables');
    }
};
