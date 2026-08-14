<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('white_company_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('white_company_id')->constrained('white_companies')->cascadeOnDelete();
            $table->unsignedBigInteger('fee_id');
            $table->decimal('sale_price', 8, 2);
            $table->decimal('neta', 8, 2);
            $table->string('status')->default('ACTIVO');
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->unique(['white_company_id', 'fee_id'], 'white_company_fees_company_fee_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('white_company_fees');
    }
};
