<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renovations', function (Blueprint $table) {
            $table->unsignedInteger('remaining_days')
                ->default(0)
                ->after('date_renewal');
        });
    }

    public function down(): void
    {
        Schema::table('renovations', function (Blueprint $table) {
            $table->dropColumn('remaining_days');
        });
    }
};
