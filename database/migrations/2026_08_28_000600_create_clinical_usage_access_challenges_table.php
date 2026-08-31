<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clinical_usage_access_challenges')) {
            return;
        }

        Schema::create('clinical_usage_access_challenges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('context', 32);
            $table->unsignedBigInteger('context_record_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedSmallInteger('emails_sent')->default(0);
            $table->unsignedSmallInteger('phones_sent')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'context', 'context_record_id'], 'cuac_user_context_record');
            $table->index('expires_at');
            $table->index('consumed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_usage_access_challenges');
    }
};
