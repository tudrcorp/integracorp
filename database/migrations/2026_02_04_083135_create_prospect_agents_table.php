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
        Schema::create('prospect_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('phone_1');
            $table->string('phone_2');
            $table->string('email');
            $table->string('state_id');
            $table->string('city_id');
            $table->string('country_id');
            $table->string('status');
            $table->string('created_by');
            $table->string('updated_by');
            $table->string('reference_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospect_agents');
    }
};
