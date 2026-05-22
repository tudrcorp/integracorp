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
        Schema::create('renovations', function (Blueprint $table) {
            $table->id();
            // Campos de la tabla con relacion a las tablas affiliation_individual y affiliation_corporate
            $table->integer('affiliation_id');
            $table->date('date_renewal');
            $table->string('status');
            $table->string('created_by');
            $table->string('updated_by');
            $table->string('code_affiliation');
            $table->string('agent_id');
            $table->string('code_agency');
            $table->string('owner_code')->nullable();
            $table->string('owner_agent')->nullable();
            $table->integer('plan_id');
            $table->integer('coverage_id')->nullable(); // El plan Inicial no tiene covertura
            $table->integer('age_range_id');
            $table->decimal('fee', 8, 2);
            $table->decimal('subtotal_anual', 8, 2);
            $table->decimal('subtotal_quarterly', 8, 2);
            $table->decimal('subtotal_biannual', 8, 2);
            $table->decimal('subtotal_monthly', 8, 2);
            $table->integer('total_persons');
            $table->string('payment_frequency');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('renovations');
    }
};
