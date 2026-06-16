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
            $table->unsignedSmallInteger('quotation_page_count')->nullable()->after('population_summary');
            $table->unsignedSmallInteger('plan_page_number')->nullable()->after('quotation_page_count');
        });
    }

    public function down(): void
    {
        Schema::table('plan_generators', function (Blueprint $table): void {
            $table->dropColumn(['quotation_page_count', 'plan_page_number']);
        });
    }
};
