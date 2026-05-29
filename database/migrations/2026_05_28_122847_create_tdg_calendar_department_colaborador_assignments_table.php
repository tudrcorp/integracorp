<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tdg_calendar_department_colaborador_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tdg_calendar_day_id');
            $table->string('department');
            $table->unsignedBigInteger('rrhh_colaborador_id');
            $table->timestamps();

            $table->unique(
                ['tdg_calendar_day_id', 'department', 'rrhh_colaborador_id'],
                'tdg_dept_colab_day_dept_unique',
            );
            $table->index(['department'], 'tdg_dept_colab_department_idx');

            $table->foreign('tdg_calendar_day_id', 'tdg_dept_colab_day_fk')
                ->references('id')
                ->on('tdg_calendar_days')
                ->cascadeOnDelete();
            $table->foreign('rrhh_colaborador_id', 'tdg_dept_colab_colaborador_fk')
                ->references('id')
                ->on('rrhh_colaboradors')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tdg_calendar_department_colaborador_assignments');
    }
};
