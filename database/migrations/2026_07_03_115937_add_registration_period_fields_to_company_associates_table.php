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
            $table->date('registration_start_date')->nullable()->after('registered_at');
            $table->date('registration_end_date')->nullable()->after('registration_start_date');
            $table->unsignedInteger('registration_period_days')->default(0)->after('registration_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('company_associates', function (Blueprint $table): void {
            $table->dropColumn([
                'registration_start_date',
                'registration_end_date',
                'registration_period_days',
            ]);
        });
    }
};
