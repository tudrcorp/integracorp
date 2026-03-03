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
        Schema::create('benefit_limit', function (Blueprint $table) {
            $table->id();
            $table->integer('benefit_id');
            $table->integer('limit_id');
            $table->string('benefit_description');
            $table->decimal('benefit_pvp', 10, 2);
            $table->integer('limit_cuota');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('benefit_limit');
    }
};
