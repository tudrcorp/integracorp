<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard de proveedores (Operaciones).
 *
 * - `created_by` / `updated_by` en `corporate_allies`: quién registró y quién actualizó cada aliado
 *   (jurídicos y naturales ya los tienen).
 * - `provider_activity_events`: bitácora de lo que hace el equipo con los proveedores (altas,
 *   actualizaciones, cambios de estatus y de tipo de convenio, cartas de aceptación, eliminaciones),
 *   para medir su productividad desde hoy.
 *
 * Aditiva e idempotente: se puede aplicar con `--path` aunque ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('corporate_allies')) {
            Schema::table('corporate_allies', function (Blueprint $table): void {
                if (! Schema::hasColumn('corporate_allies', 'created_by')) {
                    $table->string('created_by', 255)->nullable();
                }

                if (! Schema::hasColumn('corporate_allies', 'updated_by')) {
                    $table->string('updated_by', 255)->nullable();
                }
            });
        }

        if (! Schema::hasTable('provider_activity_events')) {
            Schema::create('provider_activity_events', function (Blueprint $table): void {
                $table->id();
                $table->string('provider_type', 20);
                $table->unsignedBigInteger('provider_id');
                $table->string('provider_name', 255)->nullable();
                $table->string('action', 30);
                $table->string('field', 60)->nullable();
                $table->text('before')->nullable();
                $table->text('after')->nullable();
                $table->json('changed_fields')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_name', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['created_at', 'action'], 'pae_date_action_idx');
                $table->index(['provider_type', 'provider_id'], 'pae_provider_idx');
                $table->index('user_name', 'pae_user_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_activity_events');

        if (Schema::hasTable('corporate_allies')) {
            Schema::table('corporate_allies', function (Blueprint $table): void {
                foreach (['created_by', 'updated_by'] as $column) {
                    if (Schema::hasColumn('corporate_allies', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
