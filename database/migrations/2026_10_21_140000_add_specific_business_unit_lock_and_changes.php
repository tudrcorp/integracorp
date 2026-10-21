<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unidad de negocio específica editada a mano por afiliado (Negocios).
 *
 * - `specific_business_unit_locked_at` / `_locked_by` en afiliados individuales
 *   y corporativos: si tiene fecha, el valor lo fijó un analista y las
 *   sincronizaciones con la afiliación no lo pisan.
 * - `affiliate_business_unit_changes`: traza de cada cambio (antes, después,
 *   nota obligatoria, pacientes de telemedicina actualizados). Solo se inserta.
 *
 * Aditiva e idempotente: se puede aplicar con `--path` aunque ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['affiliates', 'affiliate_corporates'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'specific_business_unit_locked_at')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->timestamp('specific_business_unit_locked_at')->nullable();
                });
            }

            if (! Schema::hasColumn($tableName, 'specific_business_unit_locked_by')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->string('specific_business_unit_locked_by', 255)->nullable();
                });
            }
        }

        if (Schema::hasTable('affiliate_business_unit_changes')) {
            return;
        }

        Schema::create('affiliate_business_unit_changes', function (Blueprint $table): void {
            $table->id();
            $table->string('affiliate_type', 20);
            $table->unsignedBigInteger('affiliate_id');
            $table->unsignedBigInteger('affiliation_id')->nullable();
            $table->uuid('batch_id');
            $table->string('before_value', 255)->nullable();
            $table->string('after_value', 255)->nullable();
            $table->boolean('inherited')->default(false);
            $table->text('note');
            $table->json('telemedicine_patient_ids')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['affiliate_type', 'affiliate_id', 'id'], 'abuc_affiliate_idx');
            $table->index(['affiliate_type', 'affiliation_id', 'id'], 'abuc_affiliation_idx');
            $table->index('batch_id', 'abuc_batch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_business_unit_changes');

        foreach (['affiliates', 'affiliate_corporates'] as $tableName) {
            foreach (['specific_business_unit_locked_by', 'specific_business_unit_locked_at'] as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    Schema::table($tableName, function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};
