<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activities')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table): void {
            if (Schema::hasColumn('activities', 'executor_type')) {
                $table->string('executor_type')->nullable()->change();
            }

            if (Schema::hasColumn('activities', 'executor_id')) {
                $table->unsignedBigInteger('executor_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activities')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table): void {
            if (Schema::hasColumn('activities', 'executor_type')) {
                $table->string('executor_type')->nullable(false)->change();
            }

            if (Schema::hasColumn('activities', 'executor_id')) {
                $table->unsignedBigInteger('executor_id')->nullable(false)->change();
            }
        });
    }
};
