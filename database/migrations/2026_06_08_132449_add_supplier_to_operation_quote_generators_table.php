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
        Schema::table('operation_quote_generators', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_id')->nullable()->after('type_service');
            $table->string('supplier_address')->nullable()->after('supplier_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operation_quote_generators', function (Blueprint $table) {
            $table->dropColumn(['supplier_id', 'supplier_address']);
        });
    }
};
