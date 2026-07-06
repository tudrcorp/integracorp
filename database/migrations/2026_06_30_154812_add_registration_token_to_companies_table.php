<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->uuid('registration_token')->nullable()->unique()->after('created_by');
        });

        \App\Models\Company::query()
            ->whereNull('registration_token')
            ->each(function (\App\Models\Company $company): void {
                $company->update(['registration_token' => (string) Str::uuid()]);
            });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('registration_token');
        });
    }
};
