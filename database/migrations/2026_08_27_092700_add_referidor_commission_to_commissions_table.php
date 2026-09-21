<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commissions')) {
            return;
        }

        Schema::table('commissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('commissions', 'porcent_referidor')) {
                $table->decimal('porcent_referidor', 5, 2)->nullable()->default(0)->after('commission_sub_agent_ves');
            }

            if (! Schema::hasColumn('commissions', 'commission_referidor_usd')) {
                $table->decimal('commission_referidor_usd', 12, 2)->nullable()->default(0)->after('porcent_referidor');
            }

            if (! Schema::hasColumn('commissions', 'commission_referidor_ves')) {
                $table->decimal('commission_referidor_ves', 12, 2)->nullable()->default(0)->after('commission_referidor_usd');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('commissions')) {
            return;
        }

        Schema::table('commissions', function (Blueprint $table): void {
            foreach (['commission_referidor_ves', 'commission_referidor_usd', 'porcent_referidor'] as $column) {
                if (Schema::hasColumn('commissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
