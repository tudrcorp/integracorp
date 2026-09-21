<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliate_corporates')) {
            return;
        }

        if (Schema::hasColumn('affiliate_corporates', 'relationship')) {
            return;
        }

        Schema::table('affiliate_corporates', function (Blueprint $table): void {
            $table->string('relationship')->nullable()->after('sex');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('affiliate_corporates')) {
            return;
        }

        if (! Schema::hasColumn('affiliate_corporates', 'relationship')) {
            return;
        }

        Schema::table('affiliate_corporates', function (Blueprint $table): void {
            $table->dropColumn('relationship');
        });
    }
};
