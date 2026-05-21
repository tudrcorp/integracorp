<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_desks', function (Blueprint $table): void {
            if (! Schema::hasColumn('help_desks', 'team')) {
                $table->string('team')->nullable()->after('cc_colaboradores');
            }

            if (! Schema::hasColumn('help_desks', 'team_members')) {
                $table->json('team_members')->nullable()->after('team');
            }
        });
    }

    public function down(): void
    {
        Schema::table('help_desks', function (Blueprint $table): void {
            if (Schema::hasColumn('help_desks', 'team_members')) {
                $table->dropColumn('team_members');
            }

            if (Schema::hasColumn('help_desks', 'team')) {
                $table->dropColumn('team');
            }
        });
    }
};
