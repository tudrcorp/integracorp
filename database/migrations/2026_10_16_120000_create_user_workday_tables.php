<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jornada laboral en Actividad de usuarios.
 *
 * - `user_work_breaks`: el almuerzo que declara cada colaborador (uno por día).
 * - `user_work_absences`: vacaciones, reposos y permisos; esos días no hay jornada.
 * - `users.work_schedule`: estándar (NULL), por guardias o exenta.
 * - `user_activity_days`: cifras de la jornada. `workday_kind` NULL = día del
 *   régimen anterior (solo activo ÷ conectado).
 *
 * Idempotente: se puede aplicar con `--path` aunque parte ya exista.
 */
return new class extends Migration
{
    /** @var array<string, string> Columna nueva de `user_activity_days` => la que la precede. */
    private const DAY_COLUMNS = [
        'work_base_minutes' => 'workday_kind',
        'work_active_minutes' => 'work_base_minutes',
        'work_idle_minutes' => 'work_active_minutes',
        'work_absent_minutes' => 'work_idle_minutes',
        'lunch_minutes' => 'work_absent_minutes',
        'lunch_excess_minutes' => 'lunch_minutes',
        'overtime_minutes' => 'lunch_excess_minutes',
        'guard_minutes' => 'overtime_minutes',
        'guard_present_minutes' => 'guard_minutes',
        'guard_active_minutes' => 'guard_present_minutes',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('user_work_breaks')) {
            Schema::create('user_work_breaks', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->date('work_date');
                $table->dateTime('started_at');
                $table->dateTime('ended_at')->nullable();
                $table->string('end_reason', 20)->nullable();
                $table->string('started_from', 60)->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'work_date'], 'uwb_user_date_unique');
                $table->index(['work_date', 'ended_at'], 'uwb_date_ended_idx');
            });
        }

        if (! Schema::hasTable('user_work_absences')) {
            Schema::create('user_work_absences', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('type', 20);
                $table->date('starts_on');
                $table->date('ends_on');
                $table->string('reason', 255)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'starts_on', 'ends_on'], 'uwa_user_range_idx');
                $table->index(['starts_on', 'ends_on'], 'uwa_range_idx');
            });
        }

        if (! Schema::hasColumn('users', 'work_schedule')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('work_schedule', 20)->nullable();
            });
        }

        if (Schema::hasTable('user_activity_days')) {
            if (! Schema::hasColumn('user_activity_days', 'workday_kind')) {
                Schema::table('user_activity_days', function (Blueprint $table): void {
                    $table->string('workday_kind', 20)->nullable()->after('minute_states');
                });
            }

            foreach (self::DAY_COLUMNS as $column => $after) {
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
            foreach ([...array_reverse(array_keys(self::DAY_COLUMNS)), 'workday_kind'] as $column) {
                if (Schema::hasColumn('user_activity_days', $column)) {
                    Schema::table('user_activity_days', function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        if (Schema::hasColumn('users', 'work_schedule')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('work_schedule');
            });
        }

        Schema::dropIfExists('user_work_absences');
        Schema::dropIfExists('user_work_breaks');
    }
};
