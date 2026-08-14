<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('white_companies', function (Blueprint $table) {
            $table->string('certificate_signature')->nullable()->after('brand_primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('white_companies', function (Blueprint $table) {
            $table->dropColumn('certificate_signature');
        });
    }
};
