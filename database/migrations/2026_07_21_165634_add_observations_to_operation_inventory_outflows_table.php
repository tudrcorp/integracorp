<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('operation_inventory_outflows')) {
            return;
        }

        Schema::table('operation_inventory_outflows', function (Blueprint $table): void {
            if (! Schema::hasColumn('operation_inventory_outflows', 'observations')) {
                $table->text('observations')->nullable()->after('type_entry');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('operation_inventory_outflows')) {
            return;
        }

        Schema::table('operation_inventory_outflows', function (Blueprint $table): void {
            if (Schema::hasColumn('operation_inventory_outflows', 'observations')) {
                $table->dropColumn('observations');
            }
        });
    }
};
