<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemedicine_case_chat_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('telemedicine_case_id')->constrained('telemedicine_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_read_at');
            $table->timestamps();

            $table->unique(['telemedicine_case_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemedicine_case_chat_reads');
    }
};
