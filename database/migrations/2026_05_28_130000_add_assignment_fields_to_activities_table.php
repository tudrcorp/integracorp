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
            if (! Schema::hasColumn('activities', 'assignment_type')) {
                $table->string('assignment_type')->default('collaborator')->after('priority');
            }

            if (! Schema::hasColumn('activities', 'assigned_collaborator_ids')) {
                $table->json('assigned_collaborator_ids')->nullable()->after('assignment_type');
            }
        });

    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            if (Schema::hasColumn('activities', 'assigned_collaborator_ids')) {
                $table->dropColumn('assigned_collaborator_ids');
            }

            if (Schema::hasColumn('activities', 'assignment_type')) {
                $table->dropColumn('assignment_type');
            }
        });
    }
};
