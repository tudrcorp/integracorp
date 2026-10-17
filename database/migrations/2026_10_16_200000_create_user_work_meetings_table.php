<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reuniones que declara cada colaborador (varias por día, con motivo).
 *
 * - `user_work_meetings`: inicio, fin y motivo. `ended_at` NULL = sigue en reunión.
 * - `user_activity_days.meeting_minutes`: todo el tiempo en reunión del día.
 * - `user_activity_days.work_meeting_minutes`: la parte dentro de la jornada, que
 *   cuenta como trabajo en el uso de jornada (la de fuera va a horas extra).
 *
 * Idempotente: se puede aplicar con `--path` aunque parte ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_work_meetings')) {
            Schema::create('user_work_meetings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->date('work_date');
                $table->dateTime('started_at');
                $table->dateTime('ended_at')->nullable();
                $table->string('end_reason', 20)->nullable();
                $table->string('reason', 255);
                $table->string('started_from', 60)->nullable();
                $table->timestamps();

                $table->index(['user_id', 'work_date'], 'uwm_user_date_idx');
                $table->index(['work_date', 'ended_at'], 'uwm_date_ended_idx');
            });
        }

        if (Schema::hasTable('user_activity_days')) {
            foreach (['meeting_minutes' => 'guard_active_minutes', 'work_meeting_minutes' => 'meeting_minutes'] as $column => $after) {
                if (! Schema::hasColumn('user_activity_days', $column)) {
                    Schema::table('user_activity_days', function (Blueprint $table) use ($column, $after): void {
                        $table->unsignedSmallInteger($column)->default(0)->after($after);
                    });
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_activity_days')) {
            foreach (['work_meeting_minutes', 'meeting_minutes'] as $column) {
                if (Schema::hasColumn('user_activity_days', $column)) {
                    Schema::table('user_activity_days', function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        Schema::dropIfExists('user_work_meetings');
    }
};
