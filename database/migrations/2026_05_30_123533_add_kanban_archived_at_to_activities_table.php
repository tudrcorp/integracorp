<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            if (! Schema::hasColumn('activities', 'kanban_archived_at')) {
                $table->timestamp('kanban_archived_at')->nullable()->after('due_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            if (Schema::hasColumn('activities', 'kanban_archived_at')) {
                $table->dropColumn('kanban_archived_at');
            }
        });
    }
};
