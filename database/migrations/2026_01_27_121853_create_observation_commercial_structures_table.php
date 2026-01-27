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
        Schema::create('observation_commercial_structures', function (Blueprint $table) {
            $table->id();
            $table->string('agency_id');
            $table->string('agent_id');
            $table->string('travel_agency_id');
            $table->string('travel_agent_id');
            $table->string('observation');
            $table->string('created_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('observation_commercial_structures');
    }
};
