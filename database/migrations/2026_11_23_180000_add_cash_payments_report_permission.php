<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permiso propio del reporte «Pagos de contado» del Generador de reportes de
 * Operaciones. Se crea sin asignarlo a nadie.
 */
return new class extends Migration
{
    private const SLUG = 'reporte-pagos-de-contado';

    private const MODULE = 'OPERACIONES';

    public function up(): void
    {
        if (! Schema::hasTable('permissions')
            || DB::table('permissions')->where('slug', self::SLUG)->where('module', self::MODULE)->exists()) {
            return;
        }

        DB::table('permissions')->insert([
            'name' => 'Generador de reportes: pagos de contado (individuales y masivos, registrados y reversados)',
            'slug' => self::SLUG,
            'module' => self::MODULE,
            'created_by' => 'system',
            'updated_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permissionIds = DB::table('permissions')->where('slug', self::SLUG)->where('module', self::MODULE)->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('user_permissions')) {
            DB::table('user_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
