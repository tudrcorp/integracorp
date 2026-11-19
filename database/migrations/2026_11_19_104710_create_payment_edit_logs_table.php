<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Traza de ediciones de pagos PENDIENTES desde «Pagos registrados» de
 * Administración (individual y corporativo).
 *
 * Cada fila guarda campo por campo el valor anterior y el nuevo (`changes`), la
 * foto previa completa (`snapshot`), el motivo obligatorio y quién lo hizo. Los
 * comprobantes reemplazados no se borran: su ruta anterior queda aquí. Solo se
 * inserta.
 *
 * También registra el permiso asignable «editar-pago-pendiente» del módulo
 * ADMINISTRACION. Idempotente: se puede aplicar con `--path` aunque ya exista.
 */
return new class extends Migration
{
    private const PERMISSION_SLUG = 'editar-pago-pendiente';

    private const PERMISSION_MODULE = 'ADMINISTRACION';

    public function up(): void
    {
        if (! Schema::hasTable('payment_edit_logs')) {
            Schema::create('payment_edit_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('payment_type', 20);
                $table->unsignedBigInteger('payment_id');
                $table->unsignedBigInteger('affiliation_id')->nullable();
                $table->string('affiliation_code', 60)->nullable();
                $table->json('changes');
                $table->json('snapshot');
                $table->text('note');
                $table->string('panel', 40)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_name', 255)->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['payment_type', 'payment_id', 'id'], 'pel_payment_idx');
                $table->index(['payment_type', 'affiliation_id', 'id'], 'pel_affiliation_idx');
                $table->index('actor_id', 'pel_actor_idx');
            });
        }

        if (! Schema::hasTable('permissions')) {
            return;
        }

        $exists = DB::table('permissions')
            ->where('slug', self::PERMISSION_SLUG)
            ->where('module', self::PERMISSION_MODULE)
            ->exists();

        if (! $exists) {
            DB::table('permissions')->insert([
                'name' => 'Editar pagos pendientes en «Pagos registrados» (con motivo obligatorio)',
                'slug' => self::PERMISSION_SLUG,
                'module' => self::PERMISSION_MODULE,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_edit_logs');

        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->where('slug', self::PERMISSION_SLUG)
            ->where('module', self::PERMISSION_MODULE)
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
