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
        if (! Schema::hasTable('operation_inventory_ubications')) {
            return;
        }

        Schema::table('operation_inventory_ubications', function (Blueprint $table): void {
            if (! Schema::hasColumn('operation_inventory_ubications', 'state_id')) {
                $table->foreignId('state_id')
                    ->nullable()
                    ->after('address')
                    ->constrained('states')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('operation_inventory_ubications')) {
            return;
        }

        Schema::table('operation_inventory_ubications', function (Blueprint $table): void {
            if (Schema::hasColumn('operation_inventory_ubications', 'state_id')) {
                $table->dropConstrainedForeignId('state_id');
            }
        });
    }
};
