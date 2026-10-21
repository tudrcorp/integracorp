<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actualización de población corporativa desde el archivo del cliente.
 *
 * - `corporate_population_updates`: cada carga (archivo, plantilla usada,
 *   estatus, quién analizó, aprobó o revirtió, totales antes/después).
 * - `corporate_population_update_items`: una fila por persona con la acción
 *   propuesta, la decisión del analista y la foto previa del afiliado (base
 *   del rollback).
 * - `corporate_population_templates`: columnas del archivo por afiliación,
 *   para que la segunda carga no pida el mapeo otra vez.
 * - `corporate_population_otp_challenges`: retos OTP para aplicar o revertir.
 *
 * Aditiva e idempotente: se puede aplicar con `--path` aunque ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('corporate_population_updates')) {
            Schema::create('corporate_population_updates', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('affiliation_corporate_id');
                $table->string('status', 30);
                $table->string('mode', 20)->default('FULL');
                $table->string('file_disk', 30);
                $table->string('file_path', 500);
                $table->string('file_name', 255);
                $table->string('file_sha1', 40);
                $table->string('file_format', 10);
                $table->json('mapping');
                $table->json('summary')->nullable();
                $table->json('totals_before')->nullable();
                $table->json('totals_after')->nullable();
                $table->json('rollback_summary')->nullable();
                $table->text('error')->nullable();
                $table->unsignedBigInteger('analyzed_by')->nullable();
                $table->string('analyzed_by_name', 255)->nullable();
                $table->timestamp('analyzed_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->string('approved_by_name', 255)->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->unsignedBigInteger('rolled_back_by')->nullable();
                $table->string('rolled_back_by_name', 255)->nullable();
                $table->timestamp('rolled_back_at')->nullable();
                $table->text('rollback_note')->nullable();
                $table->timestamps();

                $table->index(['affiliation_corporate_id', 'id'], 'cpu_affiliation_idx');
                $table->index(['status', 'updated_at'], 'cpu_status_idx');
            });
        }

        if (! Schema::hasTable('corporate_population_update_items')) {
            Schema::create('corporate_population_update_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('corporate_population_update_id');
                $table->string('action', 30);
                $table->boolean('selectable')->default(false);
                $table->boolean('selected')->default(false);
                $table->unsignedInteger('file_row')->nullable();
                $table->json('file_data')->nullable();
                $table->unsignedBigInteger('affiliate_corporate_id')->nullable();
                $table->string('match_via', 30)->nullable();
                $table->text('reason')->nullable();
                $table->json('details')->nullable();
                $table->json('snapshot')->nullable();
                $table->json('result')->nullable();
                $table->string('apply_status', 20)->default('PENDING');
                $table->timestamps();

                $table->index(['corporate_population_update_id', 'action'], 'cpui_update_action_idx');
                $table->index('affiliate_corporate_id', 'cpui_affiliate_idx');
            });
        }

        if (! Schema::hasTable('corporate_population_templates')) {
            Schema::create('corporate_population_templates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('affiliation_corporate_id')->unique();
                $table->json('mapping');
                $table->json('headers')->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('corporate_population_otp_challenges')) {
            Schema::create('corporate_population_otp_challenges', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('corporate_population_update_id');
                $table->string('purpose', 20);
                $table->string('code_hash', 255);
                $table->timestamp('expires_at');
                $table->unsignedSmallInteger('attempt_count')->default(0);
                $table->unsignedSmallInteger('max_attempts')->default(3);
                $table->timestamp('consumed_at')->nullable();
                $table->timestamp('last_sent_at')->nullable();
                $table->unsignedSmallInteger('emails_sent')->default(0);
                $table->unsignedSmallInteger('phones_sent')->default(0);
                $table->timestamps();

                $table->index(['corporate_population_update_id', 'purpose', 'user_id'], 'cpoc_update_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_population_otp_challenges');
        Schema::dropIfExists('corporate_population_templates');
        Schema::dropIfExists('corporate_population_update_items');
        Schema::dropIfExists('corporate_population_updates');
    }
};
