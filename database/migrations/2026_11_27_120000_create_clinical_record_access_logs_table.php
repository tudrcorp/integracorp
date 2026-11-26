<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de lectura de la ficha clínica: quién la abrió o descargó, cuándo y desde qué panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clinical_record_access_logs')) {
            return;
        }

        Schema::create('clinical_record_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('telemedicine_patient_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 191)->nullable();
            $table->string('panel', 40);
            $table->string('action', 20);
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['telemedicine_patient_id', 'created_at'], 'clinical_access_patient_idx');
            $table->index(['user_id', 'created_at'], 'clinical_access_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_record_access_logs');
    }
};
