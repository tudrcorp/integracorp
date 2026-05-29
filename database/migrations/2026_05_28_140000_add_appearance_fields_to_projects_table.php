<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('projects', 'color')) {
                $table->string('color', 20)->default('#3B82F6')->after('status');
            }

            if (! Schema::hasColumn('projects', 'icon')) {
                $table->string('icon', 100)->default('heroicon-o-folder')->after('color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            if (Schema::hasColumn('projects', 'icon')) {
                $table->dropColumn('icon');
            }

            if (Schema::hasColumn('projects', 'color')) {
                $table->dropColumn('color');
            }
        });
    }
};
