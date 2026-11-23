<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permisos propios de dos reportes del Generador de reportes de Operaciones.
 * Se crean sin asignarlos a nadie: el administrador los da a quien corresponda.
 */
return new class extends Migration
{
    private const MODULE = 'OPERACIONES';

    /**
     * @var array<string, string>
     */
    private const PERMISSIONS = [
        'reporte-ordenes-servicio-por-estatus' => 'Generador de reportes: órdenes de servicio por estatus (con costo y precio de venta)',
        'reporte-cuentas-por-pagar-por-estatus' => 'Generador de reportes: cuentas por pagar por estatus (facturado y pagado)',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach (self::PERMISSIONS as $slug => $name) {
            if (DB::table('permissions')->where('slug', $slug)->where('module', self::MODULE)->exists()) {
                continue;
            }

            DB::table('permissions')->insert([
                'name' => $name,
                'slug' => $slug,
                'module' => self::MODULE,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => now(),
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
            ->whereIn('slug', array_keys(self::PERMISSIONS))
            ->where('module', self::MODULE)
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
