<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración de la caducidad de las órdenes de servicio de Operaciones.
 *
 * Arranca activada con 10 días, que era la regla fija del código: desplegar esto
 * no cambia el comportamiento hasta que alguien lo edite en
 * Operaciones → Parámetros de órdenes. Crea además el permiso de menú
 * `parametros-ordenes-servicio` sin asignarlo a nadie (el SUPERADMIN lo otorga).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operation_service_order_settings')) {
            Schema::create('operation_service_order_settings', function (Blueprint $table): void {
                $table->id();
                $table->boolean('expiration_enabled')->default(true);
                $table->unsignedSmallInteger('validity_days')->default(10);
                $table->string('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! DB::table('operation_service_order_settings')->exists()) {
            DB::table('operation_service_order_settings')->insert([
                'expiration_enabled' => true,
                'validity_days' => 10,
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('permissions')
            && ! DB::table('permissions')->where('slug', 'parametros-ordenes-servicio')->where('module', 'OPERACIONES')->exists()) {
            DB::table('permissions')->insert([
                'name' => 'Parámetros de órdenes de servicio',
                'slug' => 'parametros-ordenes-servicio',
                'module' => 'OPERACIONES',
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            $permissionIds = DB::table('permissions')
                ->where('slug', 'parametros-ordenes-servicio')
                ->where('module', 'OPERACIONES')
                ->pluck('id');

            if ($permissionIds->isNotEmpty() && Schema::hasTable('user_permissions')) {
                DB::table('user_permissions')->whereIn('permission_id', $permissionIds)->delete();
            }

            if ($permissionIds->isNotEmpty()) {
                DB::table('permissions')->whereIn('id', $permissionIds)->delete();
            }
        }

        Schema::dropIfExists('operation_service_order_settings');
    }
};
