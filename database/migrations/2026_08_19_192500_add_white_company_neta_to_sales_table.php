<?php

declare(strict_types=1);

use App\Support\WhiteCompanies\WhiteCompanySaleAmounts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->decimal('white_company_neta', 8, 2)->nullable()->after('total_amount');
        });

        WhiteCompanySaleAmounts::backfillAlliedSales();
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn('white_company_neta');
        });
    }
};
