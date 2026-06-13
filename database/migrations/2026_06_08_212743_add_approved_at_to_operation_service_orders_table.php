<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('operation_service_orders', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('status');
        });

        DB::table('operation_service_orders')
            ->whereNull('approved_at')
            ->update([
                'approved_at' => DB::raw('created_at'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operation_service_orders', function (Blueprint $table) {
            $table->dropColumn('approved_at');
        });
    }
};
