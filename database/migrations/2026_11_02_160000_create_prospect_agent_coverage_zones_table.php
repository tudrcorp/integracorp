<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prospect_agent_coverage_zones')) {
            return;
        }

        Schema::create('prospect_agent_coverage_zones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('prospect_agent_id');
            $table->string('classification')->nullable();
            $table->string('service_type')->nullable();
            $table->unsignedBigInteger('state_id');
            $table->unsignedBigInteger('city_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('prospect_agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_agent_coverage_zones');
    }
};
