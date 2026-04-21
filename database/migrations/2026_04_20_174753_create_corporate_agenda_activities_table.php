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
        Schema::create('corporate_agenda_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('activity_date');
            $table->string('activity_type');
            $table->boolean('has_google_meet')->default(false);
            $table->string('google_meet_url')->nullable();
            $table->text('description');
            $table->timestamps();

            $table->index(['activity_date']);
            $table->index(['creator_user_id', 'activity_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('corporate_agenda_activities');
    }
};
