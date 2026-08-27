<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function tables(): array
    {
        return [
            'family_histories',
            'pathological_histories',
            'no_pathological_histories',
            'surgical_histories',
            'gynecological_histories',
        ];
    }

    public function up(): void
    {
        foreach ($this->tables() as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'observations')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->text('observations')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables() as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'observations')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('observations')->nullable(false)->change();
            });
        }
    }
};
