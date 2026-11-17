<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca del carnet por plan (logo, color y nombre del pie). Sin fila, el plan
 * usa el carnet estándar de Tu Doctor en Casa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plan_carnet_brands')) {
            return;
        }

        Schema::create('plan_carnet_brands', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('plan_id')->unique();
            $table->string('logo_path')->nullable();
            $table->string('color', 7);
            $table->string('company_label', 80);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_carnet_brands');
    }
};
