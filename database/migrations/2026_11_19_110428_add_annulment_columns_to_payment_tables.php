<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un pago pendiente ya no se elimina desde «Historial de pagos»: se anula. La
 * fila queda con estatus ANULADO y aquí se guarda quién, cuándo y por qué.
 *
 * El permiso conserva su slug (`eliminar-pago-pendiente`) para no perder las
 * asignaciones existentes; solo cambia el nombre que ve el administrador.
 *
 * Aditiva e idempotente: se puede aplicar con `--path` aunque ya exista.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = ['paid_memberships', 'paid_membership_corporates'];

    private const PERMISSION_SLUG = 'eliminar-pago-pendiente';

    private const PERMISSION_NAME = 'Anular pagos pendientes del historial de pagos (con motivo obligatorio)';

    private const PREVIOUS_PERMISSION_NAME = 'Eliminar pagos pendientes del historial de pagos (con nota obligatoria)';

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'annulled_at')) {
                    $table->timestamp('annulled_at')->nullable();
                }

                if (! Schema::hasColumn($tableName, 'annulled_by_user_id')) {
                    $table->unsignedBigInteger('annulled_by_user_id')->nullable();
                }

                if (! Schema::hasColumn($tableName, 'annulled_by_name')) {
                    $table->string('annulled_by_name', 255)->nullable();
                }

                if (! Schema::hasColumn($tableName, 'annulment_reason')) {
                    $table->text('annulment_reason')->nullable();
                }
            });
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->where('slug', self::PERMISSION_SLUG)
                ->update(['name' => self::PERMISSION_NAME, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $columns = array_values(array_filter(
                ['annulled_at', 'annulled_by_user_id', 'annulled_by_name', 'annulment_reason'],
                static fn (string $column): bool => Schema::hasColumn($tableName, $column),
            ));

            if ($columns !== []) {
                Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->where('slug', self::PERMISSION_SLUG)
                ->update(['name' => self::PREVIOUS_PERMISSION_NAME, 'updated_at' => now()]);
        }
    }
};
