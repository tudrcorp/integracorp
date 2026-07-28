<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activities')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table): void {
            if (! Schema::hasColumn('activities', 'help_desk_id')) {
                $table->foreignId('help_desk_id')
                    ->nullable()
                    ->after('sprint_id')
                    ->constrained('help_desks')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activities')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table): void {
            if (Schema::hasColumn('activities', 'help_desk_id')) {
                $table->dropConstrainedForeignId('help_desk_id');
            }
        });
    }
};
