<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('suppliers', 'integracorp_alias')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->string('integracorp_alias', 60)->nullable()->after('gestion_integracorp');
                $table->unique('integracorp_alias', 'suppliers_integracorp_alias_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('suppliers', 'integracorp_alias')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->dropUnique('suppliers_integracorp_alias_unique');
                $table->dropColumn('integracorp_alias');
            });
        }
    }
};
