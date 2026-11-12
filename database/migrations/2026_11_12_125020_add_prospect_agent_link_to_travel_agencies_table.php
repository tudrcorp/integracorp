<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('travel_agencies') && ! Schema::hasColumn('travel_agencies', 'prospect_agent_id')) {
            Schema::table('travel_agencies', function (Blueprint $table): void {
                $table->unsignedBigInteger('prospect_agent_id')
                    ->nullable()
                    ->unique()
                    ->after('id');
            });
        }

        if (Schema::hasTable('prospect_agents') && ! Schema::hasColumn('prospect_agents', 'travel_agency_id')) {
            Schema::table('prospect_agents', function (Blueprint $table): void {
                $table->unsignedBigInteger('travel_agency_id')
                    ->nullable()
                    ->unique()
                    ->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('travel_agencies') && Schema::hasColumn('travel_agencies', 'prospect_agent_id')) {
            Schema::table('travel_agencies', function (Blueprint $table): void {
                $table->dropUnique(['prospect_agent_id']);
                $table->dropColumn('prospect_agent_id');
            });
        }

        if (Schema::hasTable('prospect_agents') && Schema::hasColumn('prospect_agents', 'travel_agency_id')) {
            Schema::table('prospect_agents', function (Blueprint $table): void {
                $table->dropUnique(['travel_agency_id']);
                $table->dropColumn('travel_agency_id');
            });
        }
    }
};
