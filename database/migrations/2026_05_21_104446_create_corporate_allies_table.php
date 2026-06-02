<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corporate_allies', function (Blueprint $table) {
            $table->id();
            $table->integer('country_id');
            $table->string('country_code', 8);
            $table->integer('state_id');
            $table->integer('city_id');
            $table->string('supplier_category')->nullable();
            $table->string('type_agreement')->nullable();
            $table->string('status_agreement')->nullable();
            $table->string('rif')->nullable();
            $table->string('company_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('people_contact')->nullable();
            $table->string('email')->nullable();
            $table->text('social_networks')->nullable();
            $table->text('address')->nullable();
            $table->text('services')->nullable();
            $table->string('payment_term')->nullable();
            $table->string('supplier_payment')->nullable();
            $table->string('local_beneficiary_name')->nullable();
            $table->string('local_beneficiary_rif')->nullable();
            $table->string('local_beneficiary_account_number')->nullable();
            $table->string('local_beneficiary_account_bank')->nullable();
            $table->string('local_beneficiary_account_type')->nullable();
            $table->string('local_beneficiary_phone_pm')->nullable();
            $table->string('local_beneficiary_account_number_mon_inter')->nullable();
            $table->string('local_beneficiary_account_bank_mon_inter')->nullable();
            $table->string('local_beneficiary_account_type_mon_inter')->nullable();
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
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('corporate_ally_observacions', function (Blueprint $table) {
            $table->id();
            $table->integer('corporate_ally_id');
            $table->text('observation');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_ally_observacions');
        Schema::dropIfExists('corporate_allies');
    }
};