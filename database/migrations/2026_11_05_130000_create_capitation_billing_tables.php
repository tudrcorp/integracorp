<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-facturación de capitado (Centro de reportes de Administración):
 *
 * - `affiliate_status_movements`: bitácora de altas y bajas con su fecha, de aquí en adelante
 *   (antes el sistema solo guardaba el estatus actual).
 * - `capitation_billing_closings` + `_items` + `_events`: el cierre mensual por proveedor
 *   Outsourcing, con la foto exacta de afiliados, tarifas, prorrateos, retiros, descuentos y
 *   tasa BCV, para que un mes facturado salga siempre igual.
 *
 * Idempotente: se puede aplicar con --path aunque una tabla ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliate_status_movements')) {
            Schema::create('affiliate_status_movements', function (Blueprint $table): void {
                $table->id();
                $table->string('subject_type', 32);
                $table->unsignedBigInteger('subject_id');
                $table->string('event', 20);
                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40)->nullable();
                $table->timestamp('occurred_at');
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_name')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['subject_type', 'subject_id', 'occurred_at'], 'asm_subject_idx');
                $table->index(['occurred_at'], 'asm_occurred_idx');
            });
        }

        if (! Schema::hasTable('capitation_billing_closings')) {
            Schema::create('capitation_billing_closings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('supplier_id');
                $table->string('supplier_name');
                $table->date('period');
                $table->string('status', 20);
                $table->unsignedInteger('affiliates_count')->default(0);
                $table->decimal('monthly_amount', 14, 2)->default(0);
                $table->unsignedInteger('prorate_count')->default(0);
                $table->decimal('prorate_amount', 14, 2)->default(0);
                $table->unsignedInteger('retirements_count')->default(0);
                $table->json('discounts')->nullable();
                $table->decimal('discounts_amount', 14, 2)->default(0);
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->decimal('bcv_rate', 16, 4)->nullable();
                $table->decimal('total_ves', 18, 2)->nullable();
                $table->unsignedBigInteger('previous_closing_id')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by_user_id')->nullable();
                $table->string('closed_by_name')->nullable();
                $table->timestamp('reopened_at')->nullable();
                $table->string('reopened_by_name')->nullable();
                $table->text('reopen_reason')->nullable();
                $table->timestamps();

                $table->unique(['supplier_id', 'period'], 'cbc_supplier_period_unique');
                $table->index(['status'], 'cbc_status_idx');
            });
        }

        if (! Schema::hasTable('capitation_billing_closing_items')) {
            Schema::create('capitation_billing_closing_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('closing_id');
                $table->string('section', 20);
                $table->string('subject_type', 32);
                $table->unsignedBigInteger('subject_id');
                $table->string('holder_group');
                $table->string('holder_document_type', 2)->nullable();
                $table->string('holder_document', 40)->nullable();
                $table->string('affiliate_name');
                $table->string('affiliate_document_type', 2)->nullable();
                $table->string('affiliate_document', 40)->nullable();
                $table->string('sex', 20)->nullable();
                $table->date('birth_date')->nullable();
                $table->string('relationship', 40)->nullable();
                $table->string('capitation_plan')->nullable();
                $table->string('tdec_plan')->nullable();
                $table->date('enrolled_on')->nullable();
                $table->decimal('monthly_rate', 10, 2)->default(0);
                $table->unsignedSmallInteger('prorate_days')->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->date('retired_on')->nullable();
                $table->string('observation', 500)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['closing_id', 'section'], 'cbci_closing_section_idx');
                $table->index(['subject_type', 'subject_id'], 'cbci_subject_idx');
            });
        }

        if (! Schema::hasTable('capitation_billing_closing_events')) {
            Schema::create('capitation_billing_closing_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('closing_id');
                $table->string('action', 20);
                $table->text('reason')->nullable();
                $table->json('totals')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_name')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['closing_id', 'id'], 'cbce_closing_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('capitation_billing_closing_events');
        Schema::dropIfExists('capitation_billing_closing_items');
        Schema::dropIfExists('capitation_billing_closings');
        Schema::dropIfExists('affiliate_status_movements');
    }
};
