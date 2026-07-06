<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_generator_cells', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_generator_row_id')
                ->constrained('plan_generator_rows')
                ->cascadeOnDelete();
            $table->foreignId('plan_generator_column_id')
                ->constrained('plan_generator_columns')
                ->cascadeOnDelete();
            $table->boolean('is_selected')->default(false);
            $table->decimal('coverage_amount', 14, 2)->nullable();
            $table->timestamps();

            $table->unique(
                ['plan_generator_row_id', 'plan_generator_column_id'],
                'pg_cells_row_column_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_generator_cells');
    }
};
