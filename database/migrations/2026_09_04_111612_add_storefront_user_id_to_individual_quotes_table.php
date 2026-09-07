<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('individual_quotes')) {
            return;
        }

        if (! Schema::hasColumn('individual_quotes', 'storefront_user_id')) {
            Schema::table('individual_quotes', function (Blueprint $table): void {
                $table->unsignedBigInteger('storefront_user_id')->nullable()->after('created_by');
            });
        }

        if (! Schema::hasIndex('individual_quotes', 'individual_quotes_storefront_user_id_index')) {
            Schema::table('individual_quotes', function (Blueprint $table): void {
                $table->index('storefront_user_id', 'individual_quotes_storefront_user_id_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('individual_quotes') || ! Schema::hasColumn('individual_quotes', 'storefront_user_id')) {
            return;
        }

        Schema::table('individual_quotes', function (Blueprint $table): void {
            if (Schema::hasIndex('individual_quotes', 'individual_quotes_storefront_user_id_index')) {
                $table->dropIndex('individual_quotes_storefront_user_id_index');
            }

            $table->dropColumn('storefront_user_id');
        });
    }
};
