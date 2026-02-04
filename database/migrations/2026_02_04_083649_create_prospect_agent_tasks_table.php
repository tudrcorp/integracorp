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
        Schema::create('prospect_agent_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('prospect_agent_id');
            $table->string('task');
            $table->string('created_by');
            $table->string('updated_by');
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospect_agent_tasks');
    }
};
