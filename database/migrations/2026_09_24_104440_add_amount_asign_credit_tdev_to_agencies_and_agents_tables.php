<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agencies') && ! Schema::hasColumn('agencies', 'amount_asign_credit_tdev')) {
            Schema::table('agencies', function (Blueprint $table): void {
                $table->decimal('amount_asign_credit_tdev', 14, 2)->nullable()->default(0);
            });
        }

        if (Schema::hasTable('agents') && ! Schema::hasColumn('agents', 'amount_asign_credit_tdev')) {
            Schema::table('agents', function (Blueprint $table): void {
                $table->decimal('amount_asign_credit_tdev', 14, 2)->nullable()->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('agencies') && Schema::hasColumn('agencies', 'amount_asign_credit_tdev')) {
            Schema::table('agencies', function (Blueprint $table): void {
                $table->dropColumn('amount_asign_credit_tdev');
            });
        }

        if (Schema::hasTable('agents') && Schema::hasColumn('agents', 'amount_asign_credit_tdev')) {
            Schema::table('agents', function (Blueprint $table): void {
                $table->dropColumn('amount_asign_credit_tdev');
            });
        }
    }
};
