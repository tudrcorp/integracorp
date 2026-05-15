<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rrhh_colaboradors', function (Blueprint $table): void {
            if (! Schema::hasColumn('rrhh_colaboradors', 'birth_date')) {
                $table->date('birth_date')->nullable();
            }

            if (! Schema::hasColumn('rrhh_colaboradors', 'age')) {
                $table->unsignedSmallInteger('age')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('rrhh_colaboradors', function (Blueprint $table): void {
            if (Schema::hasColumn('rrhh_colaboradors', 'age')) {
                $table->dropColumn('age');
            }

            if (Schema::hasColumn('rrhh_colaboradors', 'birth_date')) {
                $table->dropColumn('birth_date');
            }
        });
    }
};
