<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_coordination_services', function (Blueprint $table): void {
            $table->boolean('assigned_to_supplier_by_tdg')->default(false)->after('supplier_id');
            $table->timestamp('assigned_to_supplier_by_tdg_at')->nullable()->after('assigned_to_supplier_by_tdg');
            $table->string('assigned_to_supplier_by_tdg_by')->nullable()->after('assigned_to_supplier_by_tdg_at');
        });
    }

    public function down(): void
    {
        Schema::table('operation_coordination_services', function (Blueprint $table): void {
            $table->dropColumn([
                'assigned_to_supplier_by_tdg',
                'assigned_to_supplier_by_tdg_at',
                'assigned_to_supplier_by_tdg_by',
            ]);
        });
    }
};
