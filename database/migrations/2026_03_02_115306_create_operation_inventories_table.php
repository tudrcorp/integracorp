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
        Schema::create('operation_inventories', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('unit');
            $table->string('type');
            $table->integer('existence');
            $table->decimal('cost', 8, 2)->default(0);
            $table->string('created_by');
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_inventories');
    }
};
