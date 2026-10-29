<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes de facturación por cambio de población corporativa (Administración).
 *
 * - `corporate_billing_adjustments`: un ajuste por actualización de población aplicada, con la
 *   fecha efectiva, los totales y la foto completa del cálculo.
 * - `corporate_billing_adjustment_lines`: una línea por persona que entra o sale, con su tarifa,
 *   los días y el monto prorrateado, para que el analista vea de dónde sale cada centavo.
 * - `collections.billing_adjustment_id`: marca el aviso de cobro por ajuste. El resto de los
 *   módulos que recorren cuotas del calendario (corrección de vigencia, cambio de frecuencia,
 *   upgrades, detector) lo ignoran por esta columna.
 *
 * Idempotente: se aplica con --path y puede correrse dos veces sin daño.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('corporate_billing_adjustments')) {
            Schema::create('corporate_billing_adjustments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('affiliation_corporate_id');
                $table->string('affiliation_code', 100);
                $table->unsignedBigInteger('corporate_population_update_id');
                $table->string('status', 20);
                $table->date('effective_date');
                $table->string('payment_frequency', 20);
                $table->date('cycle_start');
                $table->date('cycle_end');
                $table->unsignedInteger('persons_added')->default(0);
                $table->unsignedInteger('persons_removed')->default(0);
                $table->decimal('charge_amount', 12, 2)->default(0);
                $table->decimal('installment_delta', 12, 2)->default(0);
                $table->unsignedInteger('installments_adjusted')->default(0);
                $table->unsignedBigInteger('charge_collection_id')->nullable();
                $table->string('fingerprint', 40);
                $table->json('summary');
                $table->text('reason');
                $table->unsignedBigInteger('created_by_id')->nullable();
                $table->string('created_by_name', 255)->nullable();
                $table->string('pdf_path', 500)->nullable();
                $table->timestamp('reverted_at')->nullable();
                $table->unsignedBigInteger('reverted_by_id')->nullable();
                $table->string('reverted_by_name', 255)->nullable();
                $table->text('revert_reason')->nullable();
                $table->timestamps();

                $table->index(['affiliation_corporate_id', 'status'], 'cba_affiliation_status_idx');
                $table->index(['corporate_population_update_id', 'status'], 'cba_update_status_idx');
                $table->index(['status', 'created_at'], 'cba_status_created_idx');
            });
        }

        if (! Schema::hasTable('corporate_billing_adjustment_lines')) {
            Schema::create('corporate_billing_adjustment_lines', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('corporate_billing_adjustment_id');
                $table->unsignedBigInteger('corporate_population_update_item_id')->nullable();
                $table->unsignedBigInteger('affiliate_corporate_id')->nullable();
                $table->string('kind', 10);
                $table->string('movement', 40);
                $table->string('full_name', 255)->nullable();
                $table->string('document', 60)->nullable();
                $table->decimal('annual_fee', 12, 2);
                $table->decimal('period_fee', 12, 2);
                $table->date('covered_from')->nullable();
                $table->date('covered_until')->nullable();
                $table->unsignedSmallInteger('days')->default(0);
                $table->unsignedSmallInteger('period_days')->default(0);
                $table->decimal('prorated_amount', 12, 2)->default(0);
                $table->decimal('installment_delta', 12, 2)->default(0);
                $table->timestamps();

                $table->index('corporate_billing_adjustment_id', 'cbal_adjustment_idx');
                $table->index('affiliate_corporate_id', 'cbal_affiliate_idx');
            });
        }

        if (Schema::hasTable('collections') && ! Schema::hasColumn('collections', 'billing_adjustment_id')) {
            Schema::table('collections', function (Blueprint $table): void {
                $table->unsignedBigInteger('billing_adjustment_id')->nullable()->after('white_company_id');
                $table->index('billing_adjustment_id', 'collections_billing_adjustment_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('collections') && Schema::hasColumn('collections', 'billing_adjustment_id')) {
            Schema::table('collections', function (Blueprint $table): void {
                $table->dropIndex('collections_billing_adjustment_idx');
                $table->dropColumn('billing_adjustment_id');
            });
        }

        Schema::dropIfExists('corporate_billing_adjustment_lines');
        Schema::dropIfExists('corporate_billing_adjustments');
    }
};
