<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('affiliate_clinical_service_usages')) {
            return;
        }

        Schema::create('affiliate_clinical_service_usages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('telemedicine_patient_id');
            $table->string('nro_identificacion', 64)->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedBigInteger('affiliation_id')->nullable();
            $table->unsignedBigInteger('affiliation_corporate_id')->nullable();
            $table->unsignedBigInteger('benefit_id');
            $table->string('channel', 32);
            $table->unsignedBigInteger('telemedicine_service_list_id')->nullable();
            $table->unsignedBigInteger('telemedicine_case_id')->nullable();
            $table->unsignedBigInteger('telemedicine_consultation_patient_id')->nullable();
            $table->unsignedBigInteger('operation_coordination_service_id')->nullable();
            $table->string('status', 24)->default('CONSUMED');
            $table->boolean('is_override')->default(false);
            $table->unsignedBigInteger('override_challenge_id')->nullable();
            $table->text('override_reason')->nullable();
            $table->timestamp('window_starts_at')->nullable();
            $table->timestamp('window_ends_at')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['telemedicine_patient_id', 'channel', 'status'], 'acus_patient_channel_status');
            $table->index(['nro_identificacion', 'channel', 'status'], 'acus_ci_channel_status');
            $table->index(['plan_id', 'benefit_id', 'status'], 'acus_plan_benefit_status');
            $table->index(['telemedicine_case_id', 'channel', 'status'], 'acus_case_channel_status');
            $table->index('telemedicine_consultation_patient_id', 'acus_consultation');
            $table->index(['window_starts_at', 'window_ends_at'], 'acus_window');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_clinical_service_usages');
    }
};
