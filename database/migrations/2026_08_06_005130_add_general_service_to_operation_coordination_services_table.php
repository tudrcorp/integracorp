<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operation_coordination_services')) {
            return;
        }

        if (Schema::hasColumn('operation_coordination_services', 'general_service')) {
            return;
        }

        Schema::table('operation_coordination_services', function (Blueprint $table): void {
            $table->string('general_service')
                ->nullable()
                ->after('servicie');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('operation_coordination_services')) {
            return;
        }

        if (! Schema::hasColumn('operation_coordination_services', 'general_service')) {
            return;
        }

        Schema::table('operation_coordination_services', function (Blueprint $table): void {
            $table->dropColumn('general_service');
        });
    }
};
