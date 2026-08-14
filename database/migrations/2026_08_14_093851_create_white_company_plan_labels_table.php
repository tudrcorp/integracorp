<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('white_company_plan_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('white_company_id')->constrained('white_companies')->cascadeOnDelete();
            $table->unsignedBigInteger('plan_id');
            $table->string('display_name')->nullable();
            $table->string('short_label', 32)->nullable();
            $table->timestamps();

            $table->unique(['white_company_id', 'plan_id'], 'white_company_plan_labels_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('white_company_plan_labels');
    }
};
