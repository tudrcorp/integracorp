<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliations', function (Blueprint $table) {
            $table->decimal('white_company_sale_price', 8, 2)->nullable()->after('fee_anual');
            $table->decimal('white_company_neta', 8, 2)->nullable()->after('white_company_sale_price');
            $table->unsignedBigInteger('white_company_fee_id')->nullable()->after('white_company_neta');
        });
    }

    public function down(): void
    {
        Schema::table('affiliations', function (Blueprint $table) {
            $table->dropColumn([
                'white_company_sale_price',
                'white_company_neta',
                'white_company_fee_id',
            ]);
        });
    }
};
