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
        Schema::table('dress_tylor_quotes', function (Blueprint $table) {
            $table->json('quote_structure')->nullable()->after('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dress_tylor_quotes', function (Blueprint $table) {
            $table->dropColumn('quote_structure');
        });
    }
};
