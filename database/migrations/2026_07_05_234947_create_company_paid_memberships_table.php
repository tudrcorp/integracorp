<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_paid_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_generator_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('pay_amount_usd', 14, 2)->default(0);
            $table->decimal('pay_amount_ves', 14, 2)->default(0);
            $table->string('document_usd')->nullable();
            $table->string('document_ves')->nullable();
            $table->string('payment_method');
            $table->string('payment_method_usd')->default('N/A');
            $table->string('payment_method_ves')->default('N/A');
            $table->string('reference_payment_usd')->default('N/A');
            $table->string('reference_payment_ves')->default('N/A');
            $table->string('bank_usd')->default('N/A');
            $table->string('bank_ves')->default('N/A');
            $table->string('payment_frequency')->default('ANUAL');
            $table->string('payment_date')->nullable();
            $table->string('prox_payment_date')->nullable();
            $table->string('renewal_date')->nullable();
            $table->text('observations_payment')->nullable();
            $table->string('status')->default('PENDIENTE');
            $table->string('type_roll')->nullable();
            $table->decimal('tasa_bcv', 18, 6)->nullable();
            $table->string('created_by')->nullable();
            $table->string('aproved_by')->nullable();
            $table->string('name_ti_usd')->nullable();
            $table->string('date_payment_voucher')->nullable();
            $table->string('invoice_number')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_paid_memberships');
    }
};
