<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_desks', function (Blueprint $table): void {
            if (! Schema::hasColumn('help_desks', 'cc_colaboradores')) {
                $table->json('cc_colaboradores')->nullable()->after('updated_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('help_desks', function (Blueprint $table): void {
            if (Schema::hasColumn('help_desks', 'cc_colaboradores')) {
                $table->dropColumn('cc_colaboradores');
            }
        });
    }
};
