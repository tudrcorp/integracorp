<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prospect_agent_contacts')) {
            return;
        }

        Schema::table('prospect_agent_contacts', function (Blueprint $table): void {
            if (! Schema::hasColumn('prospect_agent_contacts', 'department')) {
                $table->string('department')->nullable()->after('prospect_agent_id');
            }

            if (! Schema::hasColumn('prospect_agent_contacts', 'local_phone')) {
                $table->string('local_phone', 30)->nullable()->after('phone');
            }

            if (! Schema::hasColumn('prospect_agent_contacts', 'extensions')) {
                $table->string('extensions', 100)->nullable()->after('local_phone');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('prospect_agent_contacts')) {
            return;
        }

        Schema::table('prospect_agent_contacts', function (Blueprint $table): void {
            foreach (['extensions', 'local_phone', 'department'] as $column) {
                if (Schema::hasColumn('prospect_agent_contacts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
