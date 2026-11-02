<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prospect_agents')) {
            return;
        }

        Schema::table('prospect_agents', function (Blueprint $table): void {
            if (! Schema::hasColumn('prospect_agents', 'deleted_at')) {
                $table->softDeletes()->index();
            }

            if (! Schema::hasColumn('prospect_agents', 'deleted_by')) {
                $table->string('deleted_by')->nullable()->after('deleted_at');
            }

            if (! Schema::hasColumn('prospect_agents', 'deletion_reason')) {
                $table->text('deletion_reason')->nullable()->after('deleted_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('prospect_agents')) {
            return;
        }

        Schema::table('prospect_agents', function (Blueprint $table): void {
            if (Schema::hasColumn('prospect_agents', 'deletion_reason')) {
                $table->dropColumn('deletion_reason');
            }

            if (Schema::hasColumn('prospect_agents', 'deleted_by')) {
                $table->dropColumn('deleted_by');
            }

            if (Schema::hasColumn('prospect_agents', 'deleted_at')) {
                $table->dropIndex(['deleted_at']);
                $table->dropSoftDeletes();
            }
        });
    }
};
