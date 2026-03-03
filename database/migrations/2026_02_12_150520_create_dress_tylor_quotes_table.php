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
        Schema::create('dress_tylor_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('agent_id')->nullable();
            $table->string('agency_code')->nullable();
            $table->string('owner_code')->nullable();
            $table->string('total');
            $table->string('anual');
            $table->string('mensual');
            $table->string('trimestral');
            $table->string('semestral');
            $table->string('status');
            $table->string('created_by');
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dress_tylor_quotes');
    }
};
