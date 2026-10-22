<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detector de inconsistencias de fechas en afiliaciones (Administración).
 *
 * - `affiliation_date_inconsistencies`: una fila por (afiliación, tipo) con su estado
 *   (ABIERTA, REPARADA, NO_ES_INCONSISTENCIA, RESUELTA), el detalle que la explica y la
 *   corrección que la reparó (punto de restauración en `affiliation_date_corrections`).
 * - `affiliation_date_inconsistency_events`: bitácora de cada paso (detectada, reparada,
 *   revertida, marcada como no inconsistencia, reabierta…) con nota y usuario.
 *
 * Aditiva e idempotente: se puede aplicar con `--path` aunque ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliation_date_inconsistencies')) {
            Schema::create('affiliation_date_inconsistencies', function (Blueprint $table): void {
                $table->id();
                $table->string('affiliation_type', 20);
                $table->unsignedBigInteger('affiliation_id');
                $table->string('affiliation_code', 100);
                $table->string('kind', 40);
                $table->string('severity', 10);
                $table->string('status', 30);
                $table->json('details');
                $table->string('fingerprint', 40);
                $table->string('dismissed_fingerprint', 40)->nullable();
                $table->unsignedBigInteger('correction_id')->nullable();
                $table->timestamp('first_detected_at');
                $table->timestamp('last_detected_at');
                $table->timestamp('resolved_at')->nullable();
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->string('resolved_by_name', 255)->nullable();
                $table->text('resolution_note')->nullable();
                $table->timestamps();

                $table->unique(['affiliation_type', 'affiliation_id', 'kind'], 'adi_affiliation_kind_unique');
                $table->index(['status', 'severity'], 'adi_status_severity_idx');
                $table->index('affiliation_code', 'adi_code_idx');
            });
        }

        if (! Schema::hasTable('affiliation_date_inconsistency_events')) {
            Schema::create('affiliation_date_inconsistency_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('affiliation_date_inconsistency_id');
                $table->string('action', 30);
                $table->text('note')->nullable();
                $table->json('payload')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_name', 255)->nullable();
                $table->timestamps();

                $table->index('affiliation_date_inconsistency_id', 'adie_inconsistency_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliation_date_inconsistency_events');
        Schema::dropIfExists('affiliation_date_inconsistencies');
    }
};
