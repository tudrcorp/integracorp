<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_coordination_services', function (Blueprint $table): void {
            $table->text('observations')
                ->nullable()
                ->comment('Observaciones')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('operation_coordination_services', function (Blueprint $table): void {
            $table->string('observations')
                ->nullable()
                ->comment('Observaciones')
                ->change();
        });
    }
};
