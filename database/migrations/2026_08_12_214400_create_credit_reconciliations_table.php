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
        Schema::create('credit_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type')->default('white_company')->index();
            $table->foreignId('white_company_id')->nullable()->constrained('white_companies')->nullOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained('agencies')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('affiliation_kind')->nullable()->index();
            $table->foreignId('affiliation_id')->nullable()->constrained('affiliations')->nullOnDelete();
            $table->foreignId('affiliation_corporate_id')->nullable()->constrained('affiliation_corporates')->nullOnDelete();
            $table->string('affiliation_code')->nullable()->index();
            $table->text('affiliation_information')->nullable();
            $table->unsignedInteger('affiliates_count')->default(0);
            $table->decimal('annual_amount', 14, 2)->default(0);
            $table->decimal('total_to_pay', 14, 2)->default(0);
            $table->string('payment_frequency')->nullable();
            $table->string('collection_invoice_number')->nullable()->index();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('plan_type')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_reconciliations');
    }
};
