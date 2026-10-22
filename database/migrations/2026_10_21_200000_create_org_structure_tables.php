<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estructura organizacional (organigrama de la empresa).
 *
 * - org_structure_versions: cada versión del organigrama. `current_marker` vale 1
 *   solo en la vigente y es único, así la base misma impide dos vigentes.
 * - org_positions: el árbol de puestos de una versión. `position_key` se conserva
 *   al copiar a un borrador: es lo que permite comparar versiones.
 * - org_position_assignments: quién ocupa cada puesto (titular o interino). Los
 *   colaboradores salen de RRHH; aquí solo se guarda el vínculo.
 * - org_structure_events: bitácora de cada cambio con antes y después. Solo se inserta.
 * - org_saved_views: vistas guardadas del modo Presentar.
 *
 * No toca ninguna tabla de RRHH. Idempotente: se puede aplicar con `--path`
 * aunque alguna tabla ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('org_structure_versions')) {
            Schema::create('org_structure_versions', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120);
                $table->string('status', 20);
                $table->unsignedBigInteger('based_on_version_id')->nullable();
                $table->unsignedTinyInteger('current_marker')->nullable();
                $table->unsignedInteger('lock_version')->default(0);
                $table->text('notes')->nullable();
                $table->text('review_notes')->nullable();
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->unsignedBigInteger('published_by')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique('current_marker', 'osv_current_unique');
                $table->index(['status', 'id'], 'osv_status_idx');
            });
        }

        if (! Schema::hasTable('org_positions')) {
            Schema::create('org_positions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('version_id')->constrained('org_structure_versions', 'id', 'op_version_fk')->cascadeOnDelete();
                $table->char('position_key', 36);
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->unsignedBigInteger('rrhh_cargo_id')->nullable();
                $table->unsignedBigInteger('rrhh_departamento_id')->nullable();
                $table->string('title', 150);
                $table->string('kind', 10)->default('LINEA');
                $table->unsignedSmallInteger('seats')->default(1);
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('color', 20)->nullable();
                $table->timestamps();

                $table->unique(['version_id', 'position_key'], 'op_version_key_unique');
                $table->index(['version_id', 'parent_id', 'sort_order'], 'op_tree_idx');
            });
        }

        if (! Schema::hasTable('org_position_assignments')) {
            Schema::create('org_position_assignments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('version_id');
                $table->foreignId('position_id')->constrained('org_positions', 'id', 'opa_position_fk')->cascadeOnDelete();
                $table->unsignedBigInteger('rrhh_colaborador_id');
                $table->string('role', 10)->default('TITULAR');
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['version_id', 'rrhh_colaborador_id'], 'opa_version_colab_idx');
                $table->index(['rrhh_colaborador_id', 'ends_on'], 'opa_colab_active_idx');
            });
        }

        if (! Schema::hasTable('org_structure_events')) {
            Schema::create('org_structure_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('version_id');
                $table->string('action', 40);
                $table->char('position_key', 36)->nullable();
                $table->string('summary', 255);
                $table->json('before')->nullable();
                $table->json('after')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_name', 255)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['version_id', 'id'], 'ose_version_idx');
            });
        }

        if (! Schema::hasTable('org_saved_views')) {
            Schema::create('org_saved_views', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 80);
                $table->char('root_position_key', 36)->nullable();
                $table->unsignedTinyInteger('depth')->nullable();
                $table->json('card_fields')->nullable();
                $table->boolean('show_vacancies')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('org_saved_views');
        Schema::dropIfExists('org_structure_events');
        Schema::dropIfExists('org_position_assignments');
        Schema::dropIfExists('org_positions');
        Schema::dropIfExists('org_structure_versions');
    }
};
