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
        Schema::create('affiliation_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliation_id')->constrained('affiliations')->cascadeOnDelete();
            $table->longText('description');
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index('affiliation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliation_observations');
    }
};
