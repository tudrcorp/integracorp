<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rrhh_colaboradors', function (Blueprint $table): void {
            if (! Schema::hasColumn('rrhh_colaboradors', 'documents')) {
                $table->json('documents')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('rrhh_colaboradors', function (Blueprint $table): void {
            if (Schema::hasColumn('rrhh_colaboradors', 'documents')) {
                $table->dropColumn('documents');
            }
        });
    }
};
