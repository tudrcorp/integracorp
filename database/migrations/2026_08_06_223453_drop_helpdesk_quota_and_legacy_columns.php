<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('helpdesk_groups') && Schema::hasColumn('helpdesk_groups', 'total_tickets_assigned')) {
            Schema::table('helpdesk_groups', function (Blueprint $table): void {
                $table->dropColumn('total_tickets_assigned');
            });
        }

        if (Schema::hasTable('help_desks') && Schema::hasColumn('help_desks', 'rrhh_colaborador_id')) {
            Schema::table('help_desks', function (Blueprint $table): void {
                $table->dropColumn('rrhh_colaborador_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('helpdesk_groups') && ! Schema::hasColumn('helpdesk_groups', 'total_tickets_assigned')) {
            Schema::table('helpdesk_groups', function (Blueprint $table): void {
                $table->unsignedInteger('total_tickets_assigned')->default(0);
            });
        }

        if (Schema::hasTable('help_desks') && ! Schema::hasColumn('help_desks', 'rrhh_colaborador_id')) {
            Schema::table('help_desks', function (Blueprint $table): void {
                $table->unsignedBigInteger('rrhh_colaborador_id')->nullable();
            });
        }
    }
};
