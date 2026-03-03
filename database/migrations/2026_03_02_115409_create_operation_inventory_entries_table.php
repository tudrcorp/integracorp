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
        Schema::create('operation_inventory_entries', function (Blueprint $table) {
            $table->id();
            $table->integer('operation_inventory_id');
            $table->integer('quantity');
            $table->string('unit');
            $table->string('type');
            $table->string('created_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_inventory_entries');
    }
};
