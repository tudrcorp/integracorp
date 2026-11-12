<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes de valores de una cotización (tarifa y personas por línea) con su efecto en la
 * afiliación enlazada y su reverso. Idempotente: se aplica con --path.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quote_value_adjustments')) {
            Schema::create('quote_value_adjustments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->string('quote_type', 20);
                $table->unsignedBigInteger('quote_id');
                $table->string('quote_code', 60)->nullable();
                $table->string('status', 20)->default('APLICADO');
                $table->text('reason');
                $table->json('lines');
                $table->json('effects')->nullable();
                $table->unsignedBigInteger('created_by_id')->nullable();
                $table->string('created_by_name', 150)->nullable();
                $table->timestamp('reverted_at')->nullable();
                $table->unsignedBigInteger('reverted_by_id')->nullable();
                $table->string('reverted_by_name', 150)->nullable();
                $table->text('revert_reason')->nullable();
                $table->json('revert_effects')->nullable();
                $table->timestamps();

                $table->index(['quote_type', 'quote_id'], 'qva_quote_idx');
                $table->index('status', 'qva_status_idx');
            });
        }

        if (! Schema::hasTable('quote_value_adjustment_affiliations')) {
            Schema::create('quote_value_adjustment_affiliations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('quote_value_adjustment_id');
                $table->string('affiliation_type', 20);
                $table->unsignedBigInteger('affiliation_id');
                $table->string('affiliation_code', 60)->nullable();
                $table->unsignedBigInteger('plan_row_id')->nullable();
                $table->integer('extra_persons')->default(0);
                $table->decimal('extra_annual', 14, 2)->default(0);
                $table->date('valid_until')->nullable();
                $table->string('status', 20)->default('APLICADO');
                $table->timestamps();

                $table->index('quote_value_adjustment_id', 'qvaa_adjustment_idx');
                $table->index(['affiliation_type', 'affiliation_id', 'status'], 'qvaa_affiliation_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_value_adjustment_affiliations');
        Schema::dropIfExists('quote_value_adjustments');
    }
};
