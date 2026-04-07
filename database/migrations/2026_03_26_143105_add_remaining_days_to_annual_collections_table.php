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
        if (! Schema::hasColumn('annual_collections', 'remaining_days')) {
            Schema::table('annual_collections', function (Blueprint $table) {
                $table->unsignedInteger('remaining_days')->default(0)->after('month_12');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('annual_collections', 'remaining_days')) {
            Schema::table('annual_collections', function (Blueprint $table) {
                $table->dropColumn('remaining_days');
            });
        }
    }
};
