<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            if (! Schema::hasColumn('suppliers', 'unidades_negocio_especificas')) {
                $table->json('unidades_negocio_especificas')->nullable()->after('gestion_integracorp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            if (Schema::hasColumn('suppliers', 'unidades_negocio_especificas')) {
                $table->dropColumn('unidades_negocio_especificas');
            }
        });
    }
};
