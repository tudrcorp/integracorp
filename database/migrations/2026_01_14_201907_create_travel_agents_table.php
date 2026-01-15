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
        Schema::create('travel_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->string('cargo');
            $table->string('fechaNacimiento');

            //contacto secundario
            $table->string('nameSecundario');
            $table->string('emailSecundario');
            $table->string('phoneSecundario');
            $table->string('cargoSecundario');
            $table->string('fechaNacimientoSecundario');

            //datos bancarios moneda local
            $table->string('local_beneficiary_name');
            $table->string('local_beneficiary_rif');
            $table->string('local_beneficiary_account_number');
            $table->string('local_beneficiary_account_bank');
            $table->string('local_beneficiary_account_type');
            $table->string('local_beneficiary_phone_pm');
            $table->string('local_beneficiary_account_number_mon_inter');
            $table->string('local_beneficiary_account_bank_mon_inter');
            $table->string('local_beneficiary_account_type_mon_inter');

            //datos bancarios moneda extrangera
            $table->string('extra_beneficiary_name');
            $table->string('extra_beneficiary_ci_rif');
            $table->string('extra_beneficiary_account_number');
            $table->string('extra_beneficiary_account_bank');
            $table->string('extra_beneficiary_account_type');
            $table->string('extra_beneficiary_route');
            $table->string('extra_beneficiary_zelle');
            $table->string('extra_beneficiary_ach');
            $table->string('extra_beneficiary_swift');
            $table->string('extra_beneficiary_aba');
            $table->string('extra_beneficiary_address');
            
            $table->string('logo');
            $table->string('createdBy');
            $table->string('updatedBy');
            $table->integer('travel_agency_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_agents');
    }
};
