<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_responsibles', function (Blueprint $table): void {
            $table->date('contract_start_date')->nullable()->after('zone_id');
            $table->date('contract_end_date')->nullable()->after('contract_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('company_responsibles', function (Blueprint $table): void {
            $table->dropColumn(['contract_start_date', 'contract_end_date']);
        });
    }
};
