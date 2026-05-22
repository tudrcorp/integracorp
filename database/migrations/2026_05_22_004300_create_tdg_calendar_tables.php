<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tdg_calendar_days', function (Blueprint $table) {
            $table->id();
            $table->date('calendar_date')->unique();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['calendar_date']);
        });

        Schema::create('tdg_calendar_office_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tdg_calendar_day_id')->constrained('tdg_calendar_days')->cascadeOnDelete();
            $table->string('office');
            $table->foreignId('rrhh_colaborador_id')->constrained('rrhh_colaboradors')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tdg_calendar_day_id', 'office'], 'tdg_calendar_office_day_office_unique');
            $table->index(['office']);
        });

        Schema::create('tdg_calendar_guard_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tdg_calendar_day_id')->constrained('tdg_calendar_days')->cascadeOnDelete();
            $table->string('guard_shift');
            $table->foreignId('rrhh_colaborador_id')->constrained('rrhh_colaboradors')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tdg_calendar_day_id', 'guard_shift'], 'tdg_calendar_guard_day_shift_unique');
            $table->index(['guard_shift']);
        });

        Schema::create('tdg_calendar_department_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tdg_calendar_day_id')->constrained('tdg_calendar_days')->cascadeOnDelete();
            $table->string('department');
            $table->timestamps();

            $table->unique(['tdg_calendar_day_id', 'department'], 'tdg_calendar_department_day_department_unique');
            $table->index(['department']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tdg_calendar_department_assignments');
        Schema::dropIfExists('tdg_calendar_guard_assignments');
        Schema::dropIfExists('tdg_calendar_office_assignments');
        Schema::dropIfExists('tdg_calendar_days');
    }
};
