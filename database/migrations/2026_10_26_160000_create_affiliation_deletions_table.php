<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eliminación reversible de afiliaciones individuales y corporativas (Negocios y Administración).
 *
 * - `deleted_at` en `affiliations`, `affiliation_corporates`, `affiliates` y `affiliate_corporates`:
 *   el borrado suave de Eloquent oculta la afiliación y sus afiliados en todo el sistema.
 * - `affiliation_deletions`: la traza y el punto de restauración de cada eliminación (qué se
 *   ocultó, qué cuotas se anularon, qué pacientes se desvincularon y qué casos cerrados se
 *   ocultaron), y la restauración si la hubo.
 *
 * Aditiva e idempotente: se puede aplicar con `--path` aunque ya exista.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const SOFT_DELETE_TABLES = [
        'affiliations' => 'aff_deleted_at_idx',
        'affiliation_corporates' => 'affc_deleted_at_idx',
        'affiliates' => 'afi_deleted_at_idx',
        'affiliate_corporates' => 'afic_deleted_at_idx',
    ];

    public function up(): void
    {
        foreach (self::SOFT_DELETE_TABLES as $table => $index) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                $blueprint->timestamp('deleted_at')->nullable();
                $blueprint->index('deleted_at', $index);
            });
        }

        if (! Schema::hasTable('affiliation_deletions')) {
            Schema::create('affiliation_deletions', function (Blueprint $table): void {
                $table->id();
                $table->string('affiliation_type', 20);
                $table->unsignedBigInteger('affiliation_id');
                $table->string('affiliation_code', 100);
                $table->string('affiliation_name', 255)->nullable();
                $table->string('status', 20);
                $table->text('reason');
                $table->json('snapshot');
                $table->json('summary');
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->string('deleted_by_name', 255)->nullable();
                $table->timestamp('deleted_at_moment');
                $table->text('restoration_reason')->nullable();
                $table->json('restoration_summary')->nullable();
                $table->unsignedBigInteger('restored_by')->nullable();
                $table->string('restored_by_name', 255)->nullable();
                $table->timestamp('restored_at')->nullable();
                $table->timestamps();

                $table->index(['affiliation_type', 'affiliation_id'], 'afd_affiliation_idx');
                $table->index('affiliation_code', 'afd_code_idx');
                $table->index('status', 'afd_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliation_deletions');

        foreach (self::SOFT_DELETE_TABLES as $table => $index) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                $blueprint->dropIndex($index);
                $blueprint->dropColumn('deleted_at');
            });
        }
    }
};
