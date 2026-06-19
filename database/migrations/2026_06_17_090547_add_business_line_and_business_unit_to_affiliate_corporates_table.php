<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_corporates', function (Blueprint $table): void {
            if (! Schema::hasColumn('affiliate_corporates', 'business_unit_id')) {
                $table->foreignId('business_unit_id')
                    ->nullable()
                    ->after('coverage_id')
                    ->constrained('business_units')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('affiliate_corporates', 'business_line_id')) {
                $table->foreignId('business_line_id')
                    ->nullable()
                    ->after('business_unit_id')
                    ->constrained('business_lines')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_corporates', function (Blueprint $table): void {
            if (Schema::hasColumn('affiliate_corporates', 'business_line_id')) {
                $table->dropConstrainedForeignId('business_line_id');
            }

            if (Schema::hasColumn('affiliate_corporates', 'business_unit_id')) {
                $table->dropConstrainedForeignId('business_unit_id');
            }
        });
    }
};
