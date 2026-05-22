<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tdg_calendar_office_assignments', function (Blueprint $table) {
            $table->dropForeign(['tdg_calendar_day_id']);
            $table->dropForeign(['rrhh_colaborador_id']);
        });

        Schema::table('tdg_calendar_office_assignments', function (Blueprint $table) {
            $table->dropUnique('tdg_calendar_office_day_office_unique');
            $table->unique(
                ['tdg_calendar_day_id', 'office', 'rrhh_colaborador_id'],
                'tdg_calendar_office_day_office_colaborador_unique',
            );
            $table->foreign('tdg_calendar_day_id')
                ->references('id')
                ->on('tdg_calendar_days')
                ->cascadeOnDelete();
            $table->foreign('rrhh_colaborador_id')
                ->references('id')
                ->on('rrhh_colaboradors')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tdg_calendar_office_assignments', function (Blueprint $table) {
            $table->dropForeign(['tdg_calendar_day_id']);
            $table->dropForeign(['rrhh_colaborador_id']);
        });

        Schema::table('tdg_calendar_office_assignments', function (Blueprint $table) {
            $table->dropUnique('tdg_calendar_office_day_office_colaborador_unique');
            $table->unique(['tdg_calendar_day_id', 'office'], 'tdg_calendar_office_day_office_unique');
            $table->foreign('tdg_calendar_day_id')
                ->references('id')
                ->on('tdg_calendar_days')
                ->cascadeOnDelete();
            $table->foreign('rrhh_colaborador_id')
                ->references('id')
                ->on('rrhh_colaboradors')
                ->cascadeOnDelete();
        });
    }
};
