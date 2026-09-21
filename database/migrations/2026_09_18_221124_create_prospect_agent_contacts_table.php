<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_agent_contacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('prospect_agent_id');
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('prospect_agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_agent_contacts');
    }
};
