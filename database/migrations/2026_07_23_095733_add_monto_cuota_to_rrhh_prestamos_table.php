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
        Schema::table('rrhh_prestamos', function (Blueprint $table) {
            if (! Schema::hasColumn('rrhh_prestamos', 'monto_cuota')) {
                $table->decimal('monto_cuota', 10, 2)->nullable()->after('nro_cuotas');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rrhh_prestamos', function (Blueprint $table) {
            if (Schema::hasColumn('rrhh_prestamos', 'monto_cuota')) {
                $table->dropColumn('monto_cuota');
            }
        });
    }
};
