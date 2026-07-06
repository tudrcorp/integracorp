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
        Schema::create('affiliation_corporate_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliation_corporate_id')
                ->constrained('affiliation_corporates', indexName: 'aco_affiliation_corporate_id_index')
                ->cascadeOnDelete();
            $table->longText('description');
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliation_corporate_observations');
    }
};
