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
        Schema::create('config_costo_benefits', function (Blueprint $table) {
            $table->id();
            $table->decimal('porcentaje_uno', 10, 2);
            $table->decimal('porcentaje_dos', 10, 2);
            $table->decimal('porcentaje_tres', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_costo_benefits');
    }
};
