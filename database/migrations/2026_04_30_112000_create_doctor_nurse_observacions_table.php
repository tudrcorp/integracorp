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
        Schema::create('doctor_nurse_observacions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_nurse_id')->constrained('doctor_nurses')->cascadeOnDelete();
            $table->text('observation');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_nurse_observacions');
    }
};
