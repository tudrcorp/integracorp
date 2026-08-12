<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tdev_agencies', function (Blueprint $table) {
            $table->string('landing_slogan_line_1')
                ->nullable()
                ->after('url');
            $table->string('landing_slogan_line_2')
                ->nullable()
                ->after('landing_slogan_line_1');
        });
    }

    public function down(): void
    {
        Schema::table('tdev_agencies', function (Blueprint $table) {
            $table->dropColumn([
                'landing_slogan_line_1',
                'landing_slogan_line_2',
            ]);
        });
    }
};
