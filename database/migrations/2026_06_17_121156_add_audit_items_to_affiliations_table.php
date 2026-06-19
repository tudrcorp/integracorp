<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('affiliations', function (Blueprint $table) {
            if (! Schema::hasColumn('affiliations', 'audit_items')) {
                $table->json('audit_items')->nullable()->after('observations');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affiliations', function (Blueprint $table) {
            if (Schema::hasColumn('affiliations', 'audit_items')) {
                $table->dropColumn('audit_items');
            }
        });
    }
};
