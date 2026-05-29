<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('groups', 'collaborator_ids')) {
                $table->json('collaborator_ids')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table): void {
            if (Schema::hasColumn('groups', 'collaborator_ids')) {
                $table->dropColumn('collaborator_ids');
            }
        });
    }
};
