<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de altas y bajas de afiliados (individuales y corporativos) con sus
 * efectos en afiliación, cobranza y operaciones, y el desglose que verá el
 * cliente en el aviso de cobro. Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliate_movements')) {
            Schema::create('affiliate_movements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->string('affiliation_type', 12);
                $table->unsignedBigInteger('affiliation_id');
                $table->string('affiliation_code', 60)->nullable();
                $table->unsignedBigInteger('affiliate_id');
                $table->string('affiliate_name', 255)->nullable();
                $table->string('affiliate_document', 60)->nullable();
                $table->string('kind', 10);
                $table->string('status', 12)->default('APLICADO');
                $table->date('effective_date');
                $table->string('payment_frequency', 20)->nullable();
                $table->decimal('annual_fee', 12, 2)->default(0);
                $table->decimal('period_fee', 12, 2)->default(0);
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->unsignedSmallInteger('period_days')->default(0);
                $table->unsignedSmallInteger('prorated_days')->default(0);
                $table->decimal('prorated_amount', 12, 2)->default(0);
                $table->decimal('pending_credit', 12, 2)->default(0);
                $table->unsignedBigInteger('charge_collection_id')->nullable();
                $table->json('snapshot')->nullable();
                $table->json('effects')->nullable();
                $table->string('billing_note', 500)->nullable();
                $table->string('reason', 1000);
                $table->unsignedBigInteger('created_by_id')->nullable();
                $table->string('created_by_name', 150)->nullable();
                $table->string('source', 80)->nullable();
                $table->timestamp('reverted_at')->nullable();
                $table->unsignedBigInteger('reverted_by_id')->nullable();
                $table->string('reverted_by_name', 150)->nullable();
                $table->string('revert_reason', 1000)->nullable();
                $table->json('revert_effects')->nullable();
                $table->timestamps();

                $table->index(['affiliation_type', 'affiliation_id', 'status'], 'am_affiliation_idx');
                $table->index(['affiliation_type', 'affiliate_id'], 'am_affiliate_idx');
                $table->index(['affiliation_code', 'pending_credit'], 'am_code_credit_idx');
            });
        }

        if (! Schema::hasTable('collection_movement_lines')) {
            Schema::create('collection_movement_lines', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('collection_id');
                $table->unsignedBigInteger('affiliate_movement_id');
                $table->string('kind', 20);
                $table->string('description', 500);
                $table->decimal('amount', 12, 2);
                $table->boolean('reverted')->default(false);
                $table->timestamps();

                $table->index('collection_id', 'cml_collection_idx');
                $table->index('affiliate_movement_id', 'cml_movement_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_movement_lines');
        Schema::dropIfExists('affiliate_movements');
    }
};
