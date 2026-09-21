<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clinical_service_override_challenges')) {
            return;
        }

        Schema::create('clinical_service_override_challenges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('telemedicine_patient_id');
            $table->unsignedBigInteger('telemedicine_case_id')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedBigInteger('benefit_id');
            $table->string('channel', 32);
            $table->unsignedBigInteger('telemedicine_service_list_id')->nullable();
            $table->text('reason');
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedSmallInteger('emails_sent')->default(0);
            $table->unsignedSmallInteger('phones_sent')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'telemedicine_patient_id', 'channel'], 'csoc_doctor_patient_channel');
            $table->index('expires_at');
            $table->index('consumed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_service_override_challenges');
    }
};
