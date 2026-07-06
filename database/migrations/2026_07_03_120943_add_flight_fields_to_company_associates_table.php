<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_associates', function (Blueprint $table): void {
            $table->date('flight_date')->nullable()->after('phone');
            $table->time('flight_time')->nullable()->after('flight_date');
        });
    }

    public function down(): void
    {
        Schema::table('company_associates', function (Blueprint $table): void {
            $table->dropColumn(['flight_date', 'flight_time']);
        });
    }
};
