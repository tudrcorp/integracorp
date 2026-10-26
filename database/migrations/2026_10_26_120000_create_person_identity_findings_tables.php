<?php

declare(strict_types=1);

use App\Support\Affiliations\PersonIdentity\PersonIdentityIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personas duplicadas (Negocios → Afiliaciones).
 *
 * - `identity_document_key`, `identity_name_key`, `identity_birth_date` en `affiliates`,
 *   `affiliate_corporates` y `company_associates`: la identidad comparable de cada persona
 *   (cédula sin letra ni separadores, nombre como conjunto de palabras, nacimiento como fecha).
 *   Las mantiene el observer de los modelos; aquí se calculan para las filas que ya existen.
 * - `person_identity_findings`: una fila por par de registros que son la misma persona (o por
 *   registro con el nombre cargado dos veces), con su estado y la nota de quien lo resolvió.
 * - `person_identity_finding_events`: bitácora de cada paso.
 *
 * Aditiva e idempotente: se puede aplicar con `--path` aunque ya exista.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const PERSON_TABLES = [
        'affiliates' => 'aff',
        'affiliate_corporates' => 'affc',
        'company_associates' => 'cas',
    ];

    public function up(): void
    {
        foreach (self::PERSON_TABLES as $table => $prefix) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'identity_document_key')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($prefix): void {
                $blueprint->string('identity_document_key', 40)->nullable();
                $blueprint->string('identity_name_key', 255)->nullable();
                $blueprint->date('identity_birth_date')->nullable();

                $blueprint->index('identity_document_key', $prefix.'_identity_document_idx');
                $blueprint->index('identity_birth_date', $prefix.'_identity_birth_idx');
            });
        }

        if (! Schema::hasTable('person_identity_findings')) {
            Schema::create('person_identity_findings', function (Blueprint $table): void {
                $table->id();
                $table->string('kind', 40);
                $table->string('severity', 10);
                $table->string('status', 30);
                $table->string('pair_key', 120);
                $table->string('record_a_type', 20);
                $table->unsignedBigInteger('record_a_id');
                $table->string('record_b_type', 20)->nullable();
                $table->unsignedBigInteger('record_b_id')->nullable();
                $table->string('person_name', 255)->nullable();
                $table->json('details');
                $table->string('fingerprint', 40);
                $table->string('dismissed_fingerprint', 40)->nullable();
                $table->timestamp('first_detected_at');
                $table->timestamp('last_detected_at');
                $table->timestamp('resolved_at')->nullable();
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->string('resolved_by_name', 255)->nullable();
                $table->text('resolution_note')->nullable();
                $table->timestamps();

                $table->unique('pair_key', 'pif_pair_unique');
                $table->index(['status', 'severity'], 'pif_status_severity_idx');
                $table->index(['record_a_type', 'record_a_id'], 'pif_record_a_idx');
                $table->index(['record_b_type', 'record_b_id'], 'pif_record_b_idx');
            });
        }

        if (! Schema::hasTable('person_identity_finding_events')) {
            Schema::create('person_identity_finding_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('person_identity_finding_id');
                $table->string('action', 30);
                $table->text('note')->nullable();
                $table->json('payload')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_name', 255)->nullable();
                $table->timestamps();

                $table->index('person_identity_finding_id', 'pife_finding_idx');
            });
        }

        PersonIdentityIndex::rebuildAll();
    }

    public function down(): void
    {
        Schema::dropIfExists('person_identity_finding_events');
        Schema::dropIfExists('person_identity_findings');

        foreach (self::PERSON_TABLES as $table => $prefix) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'identity_document_key')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($prefix): void {
                $blueprint->dropIndex($prefix.'_identity_document_idx');
                $blueprint->dropIndex($prefix.'_identity_birth_idx');
                $blueprint->dropColumn(['identity_document_key', 'identity_name_key', 'identity_birth_date']);
            });
        }
    }
};
