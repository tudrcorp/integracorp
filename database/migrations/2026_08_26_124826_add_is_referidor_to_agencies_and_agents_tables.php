<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agencies') && ! Schema::hasColumn('agencies', 'is_referidor')) {
            Schema::table('agencies', function (Blueprint $table): void {
                $table->boolean('is_referidor')->default(false)->after('assigned_credit');
            });
        }

        if (Schema::hasTable('agents') && ! Schema::hasColumn('agents', 'is_referidor')) {
            Schema::table('agents', function (Blueprint $table): void {
                $table->boolean('is_referidor')->default(false)->after('assigned_credit');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('agencies') && Schema::hasColumn('agencies', 'is_referidor')) {
            Schema::table('agencies', function (Blueprint $table): void {
                $table->dropColumn('is_referidor');
            });
        }

        if (Schema::hasTable('agents') && Schema::hasColumn('agents', 'is_referidor')) {
            Schema::table('agents', function (Blueprint $table): void {
                $table->dropColumn('is_referidor');
            });
        }
    }
};
