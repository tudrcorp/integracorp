<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_generators', function (Blueprint $table): void {
            $table->string('client_data')->default('')->after('name');
            $table->date('issued_at')->nullable()->after('client_data');
            $table->string('agent_name')->default('')->after('issued_at');
            $table->string('population_summary')->default('')->after('agent_name');
        });
    }

    public function down(): void
    {
        Schema::table('plan_generators', function (Blueprint $table): void {
            $table->dropColumn([
                'client_data',
                'issued_at',
                'agent_name',
                'population_summary',
            ]);
        });
    }
};
