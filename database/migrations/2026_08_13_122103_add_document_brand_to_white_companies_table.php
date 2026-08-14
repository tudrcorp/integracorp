<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('white_companies', function (Blueprint $table) {
            $table->string('carnet_template_image')->nullable()->after('logo');
            $table->string('brand_primary_color', 7)->nullable()->after('carnet_template_image');
        });
    }

    public function down(): void
    {
        Schema::table('white_companies', function (Blueprint $table) {
            $table->dropColumn([
                'carnet_template_image',
                'brand_primary_color',
            ]);
        });
    }
};
