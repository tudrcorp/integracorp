<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('annual_collections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->string('include_date');
            $table->string('owner_code');
            $table->string('code_agency');
            $table->unsignedInteger('agent_id')->nullable();
            $table->string('collection_invoice_number');
            $table->string('quote_number');
            $table->string('affiliation_code')->nullable();
            $table->string('affiliate_full_name')->nullable();
            $table->string('affiliate_contact')->nullable();
            $table->string('affiliate_ci_rif')->nullable();
            $table->string('affiliate_phone')->nullable();
            $table->string('affiliate_email')->nullable();
            $table->string('affiliate_status')->nullable();
            $table->unsignedInteger('plan_id')->nullable();
            $table->unsignedInteger('coverage_id')->nullable();
            $table->string('service')->nullable();
            $table->string('persons');
            $table->string('type');
            $table->string('reference')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_frequency')->nullable();
            $table->string('next_payment_date')->nullable();
            $table->decimal('total_amount', 8, 2)->default(0.00);
            $table->string('expiration_date')->nullable();
            $table->string('status')->default('POR PAGAR');
            $table->integer('days')->default(0);
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->decimal('pay_amount_usd', 8, 2)->default(0.00)->nullable();
            $table->decimal('pay_amount_ves', 8, 2)->default(0.00)->nullable();
            $table->string('bank_usd', 100)->nullable();
            $table->string('bank_ves', 100)->nullable();
            $table->date('filter_next_payment_date')->nullable();
            $table->boolean('month_1')->default(false);
            $table->boolean('month_2')->default(false);
            $table->boolean('month_3')->default(false);
            $table->boolean('month_4')->default(false);
            $table->boolean('month_5')->default(false);
            $table->boolean('month_6')->default(false);
            $table->boolean('month_7')->default(false);
            $table->boolean('month_8')->default(false);
            $table->boolean('month_9')->default(false);
            $table->boolean('month_10')->default(false);
            $table->boolean('month_11')->default(false);
            $table->boolean('month_12')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('annual_collections');
    }
};
