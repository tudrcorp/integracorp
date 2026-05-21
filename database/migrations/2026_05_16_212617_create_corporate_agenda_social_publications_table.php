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
        Schema::create('corporate_agenda_social_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('publication_date');
            $table->string('platform');
            $table->text('brief')->nullable();
            $table->timestamps();

            $table->unique(['publication_date', 'platform'], 'cas_publication_date_platform_unique');
            $table->index(['publication_date']);
            $table->index(['creator_user_id', 'publication_date'], 'cas_creator_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('corporate_agenda_social_publications');
    }
};
