<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reprogramaciones del «Próximo seguimiento» de un caso de telemedicina.
 *
 * El próximo seguimiento se calcula desde la última consulta; reasignarlo sin
 * registrar una consulta nueva no puede reescribir esa consulta, que es un
 * registro clínico firmado. Cada reprogramación queda aquí, con su autor y la
 * observación obligatoria, y manda sobre la consulta hasta que llegue una más nueva.
 */
return new class extends Migration
{
    private const TABLE = 'telemedicine_case_follow_up_reschedules';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('telemedicine_case_id');
            $table->unsignedBigInteger('telemedicine_doctor_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('priority_monitoring');
            $table->dateTime('previous_next_follow_up_at')->nullable();
            $table->dateTime('next_follow_up_at');
            $table->string('observation', 255);
            $table->timestamps();

            $table->index(['telemedicine_case_id', 'id'], 'tm_case_follow_up_reschedules_case_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
