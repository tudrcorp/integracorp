<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora del panel de Telemedicina: lo que hace cada médico y, sobre todo, lo
 * que le sale mal (mensajes que le impiden guardar, errores, «pulsó y no pasó
 * nada»), para diagnosticar sin preguntarle. Sin datos clínicos. Se purga a los
 * 90 días. Idempotente: si la tabla ya existe, no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('telemedicine_panel_events')) {
            return;
        }

        Schema::create('telemedicine_panel_events', function (Blueprint $table): void {
            $table->id();
            $table->dateTime('occurred_at', 3);
            $table->string('level', 10);
            $table->string('category', 20);
            $table->string('event', 40);
            $table->string('title', 255);
            $table->text('message')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 150)->nullable();
            $table->unsignedBigInteger('telemedicine_doctor_id')->nullable();
            $table->unsignedBigInteger('telemedicine_case_id')->nullable();
            $table->string('telemedicine_case_code', 40)->nullable();
            $table->unsignedBigInteger('telemedicine_patient_id')->nullable();
            $table->unsignedBigInteger('telemedicine_consultation_id')->nullable();
            $table->unsignedBigInteger('telemedicine_service_list_id')->nullable();
            $table->string('screen', 190)->nullable();
            $table->string('component', 190)->nullable();
            $table->string('livewire_id', 64)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('action', 120)->nullable();
            $table->string('step', 120)->nullable();
            $table->json('fields')->nullable();
            $table->json('context')->nullable();
            $table->string('request_id', 36)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->index('occurred_at', 'tpe_occurred_at_idx');
            $table->index(['level', 'occurred_at'], 'tpe_level_occurred_idx');
            $table->index(['category', 'occurred_at'], 'tpe_category_occurred_idx');
            $table->index(['user_id', 'occurred_at'], 'tpe_user_occurred_idx');
            $table->index(['telemedicine_case_id', 'occurred_at'], 'tpe_case_occurred_idx');
            $table->index('telemedicine_case_code', 'tpe_case_code_idx');
            $table->index('livewire_id', 'tpe_livewire_idx');
            $table->index('request_id', 'tpe_request_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemedicine_panel_events');
    }
};
