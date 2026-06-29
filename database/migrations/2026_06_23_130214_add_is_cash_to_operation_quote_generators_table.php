<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('operation_quote_generators', 'is_cash')) {
            return;
        }

        Schema::table('operation_quote_generators', function (Blueprint $table) {
            $table->boolean('is_cash')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('operation_quote_generators', 'is_cash')) {
            return;
        }

        Schema::table('operation_quote_generators', function (Blueprint $table) {
            $table->dropColumn('is_cash');
        });
    }
};
