<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $modules = [
        'NEGOCIOS',
        'ADMINISTRACION',
        'OPERACIONES',
        'MARKETING',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('user_permissions')) {
            return;
        }

        foreach ($this->modules as $module) {
            $sourceId = DB::table('permissions')
                ->where('slug', 'calendarios-tdg')
                ->where('module', $module)
                ->value('id');

            $permissionId = DB::table('permissions')
                ->where('slug', 'guardias-operaciones')
                ->where('module', $module)
                ->value('id');

            if ($permissionId === null) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => 'Guardias Operaciones',
                    'slug' => 'guardias-operaciones',
                    'module' => $module,
                    'created_by' => 'system',
                    'updated_by' => 'system',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permissions')
                    ->where('id', $permissionId)
                    ->update([
                        'name' => 'Guardias Operaciones',
                        'updated_by' => 'system',
                        'updated_at' => now(),
                    ]);
            }

            if ($sourceId === null) {
                continue;
            }

            $userIds = DB::table('user_permissions')
                ->where('permission_id', $sourceId)
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                $alreadyGranted = DB::table('user_permissions')
                    ->where('user_id', $userId)
                    ->where('permission_id', $permissionId)
                    ->exists();

                if ($alreadyGranted) {
                    continue;
                }

                DB::table('user_permissions')->insert([
                    'user_id' => $userId,
                    'permission_id' => $permissionId,
                    'created_by' => 'system',
                    'updated_by' => 'system',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('user_permissions')) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->where('slug', 'guardias-operaciones')
            ->whereIn('module', $this->modules)
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::table('user_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
