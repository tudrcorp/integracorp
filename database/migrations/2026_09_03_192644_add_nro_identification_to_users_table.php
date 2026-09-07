<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'nro_identification')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('nro_identification', 20)->nullable()->after('phone');
            });
        }

        if (Schema::hasColumn('users', 'identity_card') && Schema::hasColumn('users', 'nro_identification')) {
            DB::table('users')
                ->whereNull('nro_identification')
                ->whereNotNull('identity_card')
                ->where('identity_card', '!=', '')
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('users')
                            ->where('id', $row->id)
                            ->update(['nro_identification' => $row->identity_card]);
                    }
                });
        }

        if (! $this->hasIndex('users_nro_identification_unique') && Schema::hasColumn('users', 'nro_identification')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique('nro_identification', 'users_nro_identification_unique');
            });
        }

        if (Schema::hasColumn('users', 'email')) {
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('users_nro_identification_unique') && Schema::hasColumn('users', 'nro_identification')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique('users_nro_identification_unique');
            });
        }

        if (Schema::hasColumn('users', 'nro_identification')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('nro_identification');
            });
        }
    }

    private function hasIndex(string $indexName): bool
    {
        $database = Schema::getConnection()->getDatabaseName();

        $row = DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, 'users', $indexName]
        );

        return $row !== null;
    }
};
