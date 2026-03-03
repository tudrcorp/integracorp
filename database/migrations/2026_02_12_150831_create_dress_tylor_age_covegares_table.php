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
        Schema::create('dress_tylor_age_covegares', function (Blueprint $table) {
            $table->id();
            $table->string('dress_tylor_quote_id');
            $table->string('age_range_id');
            $table->string('coverage_id');
            $table->decimal('cost', 8, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dress_tylor_age_covegares');
    }
};
