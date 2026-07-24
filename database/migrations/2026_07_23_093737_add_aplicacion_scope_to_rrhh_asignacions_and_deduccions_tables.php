<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rrhh_asignacions', function (Blueprint $table) {
            if (! Schema::hasColumn('rrhh_asignacions', 'aplicacion')) {
                $table->string('aplicacion')->default('departamento')->after('monto');
            }

            if (! Schema::hasColumn('rrhh_asignacions', 'departamento_id')) {
                $table->unsignedBigInteger('departamento_id')->nullable()->after('aplicacion');
            }

            if (! Schema::hasColumn('rrhh_asignacions', 'colaborador_id')) {
                $table->unsignedBigInteger('colaborador_id')->nullable()->after('departamento_id');
            }

            if (Schema::hasColumn('rrhh_asignacions', 'cargo_id')) {
                $table->integer('cargo_id')->nullable()->change();
            }
        });

        Schema::table('rrhh_deduccions', function (Blueprint $table) {
            if (! Schema::hasColumn('rrhh_deduccions', 'aplicacion')) {
                $table->string('aplicacion')->default('departamento')->after('monto');
            }

            if (! Schema::hasColumn('rrhh_deduccions', 'departamento_id')) {
                $table->unsignedBigInteger('departamento_id')->nullable()->after('aplicacion');
            }

            if (! Schema::hasColumn('rrhh_deduccions', 'colaborador_id')) {
                $table->unsignedBigInteger('colaborador_id')->nullable()->after('departamento_id');
            }

            if (Schema::hasColumn('rrhh_deduccions', 'cargo_id')) {
                $table->integer('cargo_id')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rrhh_asignacions', function (Blueprint $table) {
            if (Schema::hasColumn('rrhh_asignacions', 'colaborador_id')) {
                $table->dropColumn('colaborador_id');
            }

            if (Schema::hasColumn('rrhh_asignacions', 'departamento_id')) {
                $table->dropColumn('departamento_id');
            }

            if (Schema::hasColumn('rrhh_asignacions', 'aplicacion')) {
                $table->dropColumn('aplicacion');
            }
        });

        Schema::table('rrhh_deduccions', function (Blueprint $table) {
            if (Schema::hasColumn('rrhh_deduccions', 'colaborador_id')) {
                $table->dropColumn('colaborador_id');
            }

            if (Schema::hasColumn('rrhh_deduccions', 'departamento_id')) {
                $table->dropColumn('departamento_id');
            }

            if (Schema::hasColumn('rrhh_deduccions', 'aplicacion')) {
                $table->dropColumn('aplicacion');
            }
        });
    }
};
