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
        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach ($this->modules as $module) {
            $permissionId = DB::table('permissions')
                ->where('slug', 'editar-guardia-oficinas')
                ->where('module', $module)
                ->value('id');

            if ($permissionId === null) {
                DB::table('permissions')->insert([
                    'name' => 'Guardia Oficinas: crear, actualizar y eliminar',
                    'slug' => 'editar-guardia-oficinas',
                    'module' => $module,
                    'created_by' => 'system',
                    'updated_by' => 'system',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('permissions')
                ->where('id', $permissionId)
                ->update([
                    'name' => 'Guardia Oficinas: crear, actualizar y eliminar',
                    'updated_by' => 'system',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->where('slug', 'editar-guardia-oficinas')
            ->whereIn('module', $this->modules)
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('user_permissions')) {
            DB::table('user_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
