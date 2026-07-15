<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('operation_coordination_services')) {
            return;
        }

        Schema::table('operation_coordination_services', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('operation_coordination_services', 'holder') ? 'holder' : null,
                Schema::hasColumn('operation_coordination_services', 'ci_holder') ? 'ci_holder' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('operation_coordination_services')) {
            return;
        }

        Schema::table('operation_coordination_services', function (Blueprint $table): void {
            if (! Schema::hasColumn('operation_coordination_services', 'holder')) {
                $table->string('holder')->nullable()->comment('Titular');
            }

            if (! Schema::hasColumn('operation_coordination_services', 'ci_holder')) {
                $table->string('ci_holder')->nullable();
            }
        });
    }
};
