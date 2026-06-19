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
        Schema::create('operation_accounts_receivables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operation_coordination_service_id');
            $table->unsignedBigInteger('telemedicine_patient_id')->nullable();
            $table->unsignedBigInteger('telemedicine_case_id')->nullable();
            $table->unsignedBigInteger('operation_quote_generator_id')->nullable();
            $table->unsignedBigInteger('operation_service_order_id')->nullable();
            $table->string('quote_number')->nullable();
            $table->string('service_order_number')->nullable();
            $table->decimal('quote_amount_usd', 15, 2)->nullable();
            $table->decimal('quote_amount_ves', 15, 2)->nullable();
            $table->decimal('bcv_rate', 15, 4)->nullable();
            $table->text('reassignment_reason')->nullable();
            $table->unsignedBigInteger('reassignment_supplier_id')->nullable();
            $table->string('reassignment_supplier_name')->nullable();
            $table->unsignedBigInteger('reassigned_by_user_id')->nullable();
            $table->string('reassigned_by_analyst_name')->nullable();
            $table->string('status')->default('PENDIENTE_GESTION_TDG');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->index('operation_coordination_service_id', 'op_accounts_recv_coord_svc_idx');
            $table->index('status', 'op_accounts_recv_status_idx');
            $table->index('reassignment_supplier_id', 'op_accounts_recv_supplier_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_accounts_receivables');
    }
};
