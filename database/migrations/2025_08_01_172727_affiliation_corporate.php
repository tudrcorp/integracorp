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
        Schema::create('affiliation_corporates', function (Blueprint $table) {
            $table->id();
            $table->integer('corporate_quote_id');
            $table->string('code_agency');
            $table->string('agent_id');
            $table->string('owner_code');
            $table->string('name_corporate');
            $table->string('rif');
            $table->string('address');
            $table->string('city_id');
            $table->string('country_id');
            $table->string('region_id');
            $table->string('state_id');
            $table->string('phone');
            $table->string('email');
            $table->string('full_name_contact');
            $table->string('nro_identificacion_contact');
            $table->string('country_code_contact');
            $table->string('phone_contact');
            $table->string('email_contact');
            $table->string('date_affiliation');
            $table->string('created_by');
            $table->string('status');
            $table->string('document');
            $table->longText('observations');
            $table->string('payment_frequency');
            $table->string('fee_anual');
            $table->decimal('total_amount', 8,2);
            $table->string('vaucher_ils');
            $table->string('date_payment_initial_ils');
            $table->string('date_payment_final_ils');
            $table->string('document_ils');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};