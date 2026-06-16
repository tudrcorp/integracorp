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
            $table->string('control_number')->default('')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('plan_generators', function (Blueprint $table): void {
            $table->dropColumn('control_number');
        });
    }
};
