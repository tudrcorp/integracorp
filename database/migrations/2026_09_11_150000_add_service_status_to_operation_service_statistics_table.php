<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operation_service_statistics')) {
            return;
        }

        if (! Schema::hasColumn('operation_service_statistics', 'service_status')) {
            Schema::table('operation_service_statistics', function (Blueprint $table): void {
                $table->string('service_status')->nullable()->after('case_status');
            });
        }

        if (! Schema::hasIndex('operation_service_statistics', 'oss_service_status_idx')) {
            Schema::table('operation_service_statistics', function (Blueprint $table): void {
                $table->index('service_status', 'oss_service_status_idx');
            });
        }

        if (Schema::hasTable('operation_coordination_services')) {
            DB::statement(
                'UPDATE operation_service_statistics oss
                 INNER JOIN operation_coordination_services ocs ON ocs.id = oss.operation_coordination_service_id
                 SET oss.service_status = ocs.status
                 WHERE oss.operation_coordination_service_id IS NOT NULL'
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('operation_service_statistics')) {
            return;
        }

        if (Schema::hasIndex('operation_service_statistics', 'oss_service_status_idx')) {
            Schema::table('operation_service_statistics', function (Blueprint $table): void {
                $table->dropIndex('oss_service_status_idx');
            });
        }

        if (Schema::hasColumn('operation_service_statistics', 'service_status')) {
            Schema::table('operation_service_statistics', function (Blueprint $table): void {
                $table->dropColumn('service_status');
            });
        }
    }
};
