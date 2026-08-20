<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planes habilitados para una empresa aliada.
 *
 * Es un paso distinto de la matriz de negociación (`white_company_fees`): acá el
 * analista decide **qué planes puede cotizar** la aliada, y recién después le
 * pone precio de venta y neta a las tarifas de esos planes. Separarlo permite
 * tener un plan asignado todavía sin precios, y que el selector de la matriz
 * ofrezca solo tarifas de planes ya habilitados.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('white_company_plans')) {
            return;
        }

        Schema::create('white_company_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('white_company_id');
            $table->unsignedBigInteger('plan_id');
            $table->string('status')->default('ACTIVO');
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->unique(['white_company_id', 'plan_id'], 'white_company_plans_unique');
            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('white_company_plans');
    }
};
