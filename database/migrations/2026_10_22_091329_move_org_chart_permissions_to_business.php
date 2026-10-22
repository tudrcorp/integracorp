<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const EDIT_SLUG = 'editar-estructura-organizacional';

    private const EXPORT_SLUG = 'exportar-organigrama';

    /**
     * Módulos que dejaron de exportar el organigrama.
     *
     * @var list<string>
     */
    private array $retiredExportModules = [
        'ADMINISTRACION',
        'OPERACIONES',
        'MARKETING',
        'PROYECTOS',
        'METRICAS',
        'TELEMEDICINA',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $this->moveEditPermissionToBusiness();
        $this->ensureBusinessExportPermission();
        $this->retireExportPermissionsOutsideBusiness();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $businessId = DB::table('permissions')
            ->where('slug', self::EDIT_SLUG)
            ->where('module', 'NEGOCIOS')
            ->value('id');

        if ($businessId !== null) {
            $administrationId = DB::table('permissions')
                ->where('slug', self::EDIT_SLUG)
                ->where('module', 'ADMINISTRACION')
                ->value('id');

            if ($administrationId === null) {
                DB::table('permissions')
                    ->where('id', $businessId)
                    ->update([
                        'module' => 'ADMINISTRACION',
                        'updated_by' => 'system',
                        'updated_at' => now(),
                    ]);
            }
        }

        foreach ($this->retiredExportModules as $module) {
            $exists = DB::table('permissions')
                ->where('slug', self::EXPORT_SLUG)
                ->where('module', $module)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('permissions')->insert([
                'name' => 'Organigrama: exportar a PDF e imagen',
                'slug' => self::EXPORT_SLUG,
                'module' => $module,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function moveEditPermissionToBusiness(): void
    {
        $name = 'Estructura organizacional: crear y editar borradores del organigrama y enviarlos a aprobación';
        $sourceId = DB::table('permissions')
            ->where('slug', self::EDIT_SLUG)
            ->where('module', 'ADMINISTRACION')
            ->value('id');
        $targetId = DB::table('permissions')
            ->where('slug', self::EDIT_SLUG)
            ->where('module', 'NEGOCIOS')
            ->value('id');

        if ($sourceId === null && $targetId === null) {
            DB::table('permissions')->insert([
                'name' => $name,
                'slug' => self::EDIT_SLUG,
                'module' => 'NEGOCIOS',
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        if ($sourceId !== null && $targetId === null) {
            DB::table('permissions')
                ->where('id', $sourceId)
                ->update([
                    'name' => $name,
                    'module' => 'NEGOCIOS',
                    'updated_by' => 'system',
                    'updated_at' => now(),
                ]);

            return;
        }

        if ($sourceId !== null && $targetId !== null) {
            $this->reassignUsers((int) $sourceId, (int) $targetId);
            DB::table('permissions')->where('id', $sourceId)->delete();
        }

        if ($targetId !== null) {
            DB::table('permissions')
                ->where('id', $targetId)
                ->update([
                    'name' => $name,
                    'updated_by' => 'system',
                    'updated_at' => now(),
                ]);
        }
    }

    private function ensureBusinessExportPermission(): void
    {
        $exists = DB::table('permissions')
            ->where('slug', self::EXPORT_SLUG)
            ->where('module', 'NEGOCIOS')
            ->exists();

        if ($exists) {
            DB::table('permissions')
                ->where('slug', self::EXPORT_SLUG)
                ->where('module', 'NEGOCIOS')
                ->update([
                    'name' => 'Organigrama: exportar a PDF e imagen',
                    'updated_by' => 'system',
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('permissions')->insert([
            'name' => 'Organigrama: exportar a PDF e imagen',
            'slug' => self::EXPORT_SLUG,
            'module' => 'NEGOCIOS',
            'created_by' => 'system',
            'updated_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function retireExportPermissionsOutsideBusiness(): void
    {
        $permissionIds = DB::table('permissions')
            ->where('slug', self::EXPORT_SLUG)
            ->whereIn('module', $this->retiredExportModules)
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('user_permissions')) {
            DB::table('user_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }

    private function reassignUsers(int $sourceId, int $targetId): void
    {
        if (! Schema::hasTable('user_permissions')) {
            return;
        }

        $userIds = DB::table('user_permissions')
            ->where('permission_id', $sourceId)
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $alreadyGranted = DB::table('user_permissions')
                ->where('user_id', $userId)
                ->where('permission_id', $targetId)
                ->exists();

            if ($alreadyGranted) {
                DB::table('user_permissions')
                    ->where('user_id', $userId)
                    ->where('permission_id', $sourceId)
                    ->delete();

                continue;
            }

            DB::table('user_permissions')
                ->where('user_id', $userId)
                ->where('permission_id', $sourceId)
                ->update([
                    'permission_id' => $targetId,
                    'updated_by' => 'system',
                    'updated_at' => now(),
                ]);
        }
    }
};
