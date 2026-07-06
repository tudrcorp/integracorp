<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_generator_columns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_generator_id')->constrained('plan_generators')->cascadeOnDelete();
            $table->string('column_key', 64);
            $table->string('header_label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['plan_generator_id', 'column_key'], 'pg_columns_generator_key_unique');
            $table->index(['plan_generator_id', 'sort_order'], 'pg_columns_generator_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_generator_columns');
    }
};
