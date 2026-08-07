<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_desk_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('help_desk_id')->constrained('help_desks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('type', 40);
            $table->longText('body_html')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['help_desk_id', 'occurred_at']);
            $table->index(['help_desk_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_events');
    }
};
