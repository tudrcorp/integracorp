<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_inventory_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('low_stock_threshold')->default(3);
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('operation_inventory_settings')->insert([
            'low_stock_threshold' => 3,
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_inventory_settings');
    }
};
