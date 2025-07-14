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
        Schema::table('commission_payrolls', function (Blueprint $table) {
            $table->string('local_beneficiary_name')->nullable();
            $table->string('local_beneficiary_rif')->nullable();
            $table->string('local_beneficiary_account_number')->nullable();
            $table->string('local_beneficiary_account_bank')->nullable();
            $table->string('local_beneficiary_account_type')->nullable();
            $table->string('local_beneficiary_phone_pm')->nullable();
            $table->string('extra_beneficiary_name')->nullable();
            $table->string('extra_beneficiary_ci_rif')->nullable();
            $table->string('extra_beneficiary_account_number')->nullable();
            $table->string('extra_beneficiary_account_bank')->nullable();
            $table->string('extra_beneficiary_account_type')->nullable();
            $table->string('extra_beneficiary_route')->nullable();
            $table->string('extra_beneficiary_zelle')->nullable();
            $table->string('extra_beneficiary_ach')->nullable();
            $table->string('extra_beneficiary_swift')->nullable();
            $table->string('extra_beneficiary_aba')->nullable();
            $table->string('extra_beneficiary_address')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commission_payrolls', function (Blueprint $table) {
            //
        });
    }
};