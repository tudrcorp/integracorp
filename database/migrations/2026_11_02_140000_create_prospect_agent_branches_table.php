<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prospect_agent_branches')) {
            return;
        }

        Schema::create('prospect_agent_branches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('prospect_agent_id');
            $table->unsignedBigInteger('state_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('local_phone', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('prospect_agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_agent_branches');
    }
};
