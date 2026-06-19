<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_generators', function (Blueprint $table): void {
            $table->string('brand_color', 7)->default('#1d4ed8')->after('population_summary');
        });
    }

    public function down(): void
    {
        Schema::table('plan_generators', function (Blueprint $table): void {
            $table->dropColumn('brand_color');
        });
    }
};
