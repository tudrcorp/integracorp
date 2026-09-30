<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'service_providers')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->json('service_providers')->nullable()->after('address');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('companies', 'service_providers')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->dropColumn('service_providers');
            });
        }
    }
};
