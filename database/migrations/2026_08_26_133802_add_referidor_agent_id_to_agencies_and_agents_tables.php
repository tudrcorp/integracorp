<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agencies') && ! Schema::hasColumn('agencies', 'referidor_agent_id')) {
            Schema::table('agencies', function (Blueprint $table): void {
                $table->foreignId('referidor_agent_id')
                    ->nullable()
                    ->after('referidor_id')
                    ->constrained('agents')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('agents') && ! Schema::hasColumn('agents', 'referidor_agent_id')) {
            Schema::table('agents', function (Blueprint $table): void {
                $table->foreignId('referidor_agent_id')
                    ->nullable()
                    ->after('referidor_id')
                    ->constrained('agents')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('agencies') && Schema::hasColumn('agencies', 'referidor_agent_id')) {
            Schema::table('agencies', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('referidor_agent_id');
            });
        }

        if (Schema::hasTable('agents') && Schema::hasColumn('agents', 'referidor_agent_id')) {
            Schema::table('agents', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('referidor_agent_id');
            });
        }
    }
};
