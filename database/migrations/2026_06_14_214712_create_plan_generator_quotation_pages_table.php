<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_generator_quotation_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_generator_id')
                ->constrained('plan_generators')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('page_number');
            $table->string('image_path');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['plan_generator_id', 'page_number'], 'pg_quote_pages_gen_page_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_generator_quotation_pages');
    }
};
