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
        if (! Schema::hasTable('affiliations')) {
            return;
        }

        Schema::table('affiliations', function (Blueprint $table) {
            $table->string('document')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('affiliations')) {
            return;
        }

        Schema::table('affiliations', function (Blueprint $table): void {
            if (Schema::hasColumn('affiliations', 'document')) {
                $table->dropColumn('document');
            }
        });
    }
};
