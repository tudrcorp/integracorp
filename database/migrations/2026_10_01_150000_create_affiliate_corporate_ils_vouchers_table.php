<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vouchers ILS por beneficio y cobertura de un afiliado corporativo.
 *
 * Reemplaza al voucher único de `affiliate_corporates.vaucherIls`, que queda
 * como histórico: un plan puede tener varios beneficios con tope en USD
 * (`benefit_coverages.limit`) y cada uno lleva su propio voucher, vigencia y
 * comprobante.
 *
 * La unicidad (afiliado, beneficio, cobertura) impide cargar dos vouchers para
 * la misma relación; recargar reemplaza el existente. `limit` es una foto del
 * tope al momento de cargar, para que el voucher siga legible aunque el plan se
 * renegocie.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('affiliate_corporate_ils_vouchers')) {
            return;
        }

        Schema::create('affiliate_corporate_ils_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_corporate_id')->constrained('affiliate_corporates')->cascadeOnDelete();
            $table->unsignedBigInteger('affiliation_corporate_id');
            $table->unsignedBigInteger('benefit_coverage_id')->nullable();
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('benefit_id');
            $table->unsignedBigInteger('coverage_id');
            $table->decimal('limit', 12, 2);
            $table->string('voucher_code', 100);
            $table->date('date_init');
            $table->date('date_end');
            $table->unsignedInteger('number_days');
            $table->string('document_path');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['affiliate_corporate_id', 'benefit_id', 'coverage_id'], 'ac_ils_vouchers_affiliate_benefit_coverage_unique');
            $table->index('affiliation_corporate_id', 'ac_ils_vouchers_affiliation_index');
            $table->index('voucher_code', 'ac_ils_vouchers_code_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_corporate_ils_vouchers');
    }
};
