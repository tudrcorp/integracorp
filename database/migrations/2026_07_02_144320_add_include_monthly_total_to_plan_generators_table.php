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
        Schema::table('plan_generators', function (Blueprint $table) {
            $table->boolean('include_monthly_total')->default(false)->after('population_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_generators', function (Blueprint $table) {
            $table->dropColumn('include_monthly_total');
        });
    }
};
