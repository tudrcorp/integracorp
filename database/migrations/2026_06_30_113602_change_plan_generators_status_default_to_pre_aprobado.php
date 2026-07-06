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
            $table->string('status')->default('PRE-APROBADO')->change();
        });
    }

    public function down(): void
    {
        Schema::table('plan_generators', function (Blueprint $table): void {
            $table->string('status')->default('ACTIVO')->change();
        });
    }
};
