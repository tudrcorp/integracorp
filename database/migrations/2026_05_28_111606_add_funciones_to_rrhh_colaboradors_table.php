<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rrhh_colaboradors', function (Blueprint $table): void {
            if (! Schema::hasColumn('rrhh_colaboradors', 'funciones')) {
                $table->longText('funciones')->nullable()->after('avatar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rrhh_colaboradors', function (Blueprint $table): void {
            if (Schema::hasColumn('rrhh_colaboradors', 'funciones')) {
                $table->dropColumn('funciones');
            }
        });
    }
};
