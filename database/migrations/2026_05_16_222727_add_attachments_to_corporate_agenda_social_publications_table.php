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
        Schema::table('corporate_agenda_social_publications', function (Blueprint $table) {
            $table->json('attachments')->nullable()->after('brief');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('corporate_agenda_social_publications', function (Blueprint $table) {
            $table->dropColumn('attachments');
        });
    }
};
