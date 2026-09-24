<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agencies') && ! Schema::hasColumn('agencies', 'id_agency_tdev')) {
            Schema::table('agencies', function (Blueprint $table): void {
                $table->unsignedInteger('id_agency_tdev')->nullable()->after('commission_tdev');
            });
        }

        if (Schema::hasTable('agents') && ! Schema::hasColumn('agents', 'id_agent_tdev')) {
            Schema::table('agents', function (Blueprint $table): void {
                $table->unsignedInteger('id_agent_tdev')->nullable()->after('commission_tdev');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('agencies') && Schema::hasColumn('agencies', 'id_agency_tdev')) {
            Schema::table('agencies', function (Blueprint $table): void {
                $table->dropColumn('id_agency_tdev');
            });
        }

        if (Schema::hasTable('agents') && Schema::hasColumn('agents', 'id_agent_tdev')) {
            Schema::table('agents', function (Blueprint $table): void {
                $table->dropColumn('id_agent_tdev');
            });
        }
    }
};
