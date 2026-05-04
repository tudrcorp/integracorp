<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Asegura `hrs_init` y `hrs_end` en `operation_on_call_users`.
     * Idempotente: no falla si la migración de creación ya las definió.
     */
    public function up(): void
    {
        if (! Schema::hasTable('operation_on_call_users')) {
            return;
        }

        if (! Schema::hasColumn('operation_on_call_users', 'hrs_init')) {
            Schema::table('operation_on_call_users', function (Blueprint $table) {
                $table->string('hrs_init')->default('00:00');
            });
        }

        if (! Schema::hasColumn('operation_on_call_users', 'hrs_end')) {
            Schema::table('operation_on_call_users', function (Blueprint $table) {
                $table->string('hrs_end')->default('00:00');
            });
        }
    }

    /**
     * Elimina las columnas si existen. Si ya venían de otra migración, valorar datos antes de rollback.
     */
    public function down(): void
    {
        if (! Schema::hasTable('operation_on_call_users')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('operation_on_call_users', 'hrs_init') ? 'hrs_init' : null,
            Schema::hasColumn('operation_on_call_users', 'hrs_end') ? 'hrs_end' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('operation_on_call_users', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
