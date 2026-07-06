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
        if (Schema::hasColumn('mass_notifications', 'email_subject')) {
            return;
        }

        Schema::table('mass_notifications', function (Blueprint $table) {
            $table->string('email_subject')->nullable()->after('header_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('mass_notifications', 'email_subject')) {
            return;
        }

        Schema::table('mass_notifications', function (Blueprint $table) {
            $table->dropColumn('email_subject');
        });
    }
};
