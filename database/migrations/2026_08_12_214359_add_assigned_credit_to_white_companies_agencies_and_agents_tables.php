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
        Schema::table('white_companies', function (Blueprint $table): void {
            $table->decimal('assigned_credit', 14, 2)->default(0)->after('address');
        });

        Schema::table('agencies', function (Blueprint $table): void {
            $table->decimal('assigned_credit', 14, 2)->default(0)->nullable();
        });

        Schema::table('agents', function (Blueprint $table): void {
            $table->decimal('assigned_credit', 14, 2)->default(0)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('white_companies', function (Blueprint $table): void {
            $table->dropColumn('assigned_credit');
        });

        Schema::table('agencies', function (Blueprint $table): void {
            $table->dropColumn('assigned_credit');
        });

        Schema::table('agents', function (Blueprint $table): void {
            $table->dropColumn('assigned_credit');
        });
    }
};
