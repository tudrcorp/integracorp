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
        Schema::create('vaucher_ils', function (Blueprint $table) {
            $table->id();
            $table->integer('affiliation_id');
            $table->integer('affiliation_corporate_id');
            $table->string('code');
            $table->string('date_init');
            $table->string('date_end');
            $table->integer('numberDays');
            $table->string('status');
            $table->integer('created_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vaucher_ils');
    }
};