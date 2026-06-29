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
        Schema::table('affiliation_corporates', function (Blueprint $table) {
            $table->string('affiliation_type')->default('ESTANDARD')->after('name_corporate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affiliation_corporates', function (Blueprint $table) {
            $table->dropColumn('affiliation_type');
        });
    }
};
