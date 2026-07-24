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
            if (! Schema::hasColumn('rrhh_prestamos', 'interes')) {
                $table->decimal('interes', 8, 2)->default(0)->after('monto');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rrhh_prestamos', function (Blueprint $table) {
            if (Schema::hasColumn('rrhh_prestamos', 'interes')) {
                $table->dropColumn('interes');
            }
        });
    }
};
