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
        Schema::create('operation_coordination_services', function (Blueprint $table) {
            $table->id();

            $table->string('date_solicitud')->nullable();
            $table->string('date_service')->nullable();
            $table->string('business_line_id')->nullable();
            $table->string('business_unit_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('status')->nullable();
            $table->string('holder')->comment('Titular');
            $table->string('ci_holder')->nullable();
            $table->string('patient')->nullable();
            $table->string('ci_patient');
            $table->string('birth_date_patient')->nullable();
            $table->string('relationship_patient');
            $table->string('age_patient')->nullable();
            $table->string('contractor')->comment('Contratante');
            $table->string('state_id')->nullable();
            $table->string('city_id')->nullable();
            $table->string('address')->nullable();
            $table->string('phone_holder')->nullable();
            $table->string('symptoms_diagnosis')->comment('Síntomas y diagnóstico');
            $table->string('servicie')->nullable();
            $table->string('specific_service')->nullable();
            $table->string('type_service')->nullable();
            $table->string('supplier_service')->nullable();
            $table->string('farmadoc')->nullable();
            $table->string('type_negotiation')->comment('Tipo de negociación')->nullable();
            $table->string('status_negotiation')->comment('Estado de la negociación')->nullable();
            $table->decimal('neto', 10, 2)->comment('Neto')->nullable();
            $table->decimal('porcen_tdec', 10, 2)->comment('Total')->nullable();
            $table->decimal('quote_price', 10, 2)->comment('Precio de la cotización')->nullable();
            $table->string('negotiation')->comment('negociación')->nullable();
            $table->decimal('porcen_discount', 10, 2)->comment('Porcentaje de descuento')->nullable();
            $table->decimal('price_discount', 10, 2)->comment('Precio de descuento')->nullable();
            $table->string('quote_number')->comment('Número de cotización')->nullable();
            $table->string('approved_number')->comment('Numero de aprobación')->nullable();
            $table->string('service_order_number')->comment('Número de orden de servicio')->nullable();
            $table->string('bill_number')->comment('Número de factura')->nullable();
            $table->decimal('bill_price', 10, 2)->comment('Precio de la factura');
            $table->string('bill_date')->comment('Fecha de la factura')->nullable();
            $table->string('incidence')->comment('Incidencia')->nullable();
            $table->string('negotiation_description')->comment('Descripción de la negociación')->nullable();
            $table->string('qc_description')->comment('Descripción de la QC')->nullable();
            $table->string('observations')->comment('Observaciones')->nullable();
            $table->string('created_by')->comment('Creado por');
            $table->string('updated_by')->comment('Actualizado por')->nullable();

            $table->integer('telemedicine_patient_id')->nullable();
            $table->integer('telemedicine_case_id')->nullable();
            $table->integer('telemedicine_doctor_id')->nullable();
            $table->integer('telemedicine_consultation_patient_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_coordination_services');
    }
};
