<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agencies') && ! Schema::hasColumn('agencies', 'referidor_percentage')) {
            Schema::table('agencies', function (Blueprint $table): void {
                $table->decimal('referidor_percentage', 5, 2)->nullable()->after('is_referidor');
            });
        }

        if (Schema::hasTable('agents') && ! Schema::hasColumn('agents', 'referidor_percentage')) {
            Schema::table('agents', function (Blueprint $table): void {
                $table->decimal('referidor_percentage', 5, 2)->nullable()->after('is_referidor');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('agencies') && Schema::hasColumn('agencies', 'referidor_percentage')) {
            Schema::table('agencies', function (Blueprint $table): void {
                $table->dropColumn('referidor_percentage');
            });
        }

        if (Schema::hasTable('agents') && Schema::hasColumn('agents', 'referidor_percentage')) {
            Schema::table('agents', function (Blueprint $table): void {
                $table->dropColumn('referidor_percentage');
            });
        }
    }
};
