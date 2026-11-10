<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturas de medicamentos (no fiscales) que Operaciones emite al paciente cuando un
 * medicamento NO CUBIERTO sale del inventario Diagnomóvil.
 *
 * Todos los montos y datos del producto se congelan al emitir: un cambio posterior de
 * costo, de % de utilidad o de tasa BCV no altera una factura ya emitida.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operation_medication_invoices')) {
            Schema::create('operation_medication_invoices', function (Blueprint $table): void {
                $table->id();
                $table->string('invoice_number', 20)->nullable()->unique('omi_invoice_number_unique');
                $table->foreignId('operation_coordination_service_id')->nullable()->index('omi_coordination_idx');
                $table->foreignId('telemedicine_patient_id')->nullable()->index('omi_patient_idx');
                $table->foreignId('telemedicine_case_id')->nullable()->index('omi_case_idx');
                $table->string('patient_name');
                $table->string('patient_identification', 50)->nullable();
                $table->string('case_code', 50)->nullable();
                $table->string('status', 30);
                $table->decimal('total_usd', 12, 2);
                $table->decimal('bcv_rate', 14, 4);
                $table->decimal('total_ves', 16, 2);
                $table->timestamp('issued_at');
                $table->foreignId('issued_by_user_id')->nullable();
                $table->string('issued_by_name')->nullable();
                $table->date('paid_at')->nullable();
                $table->string('payment_method', 40)->nullable();
                $table->string('payment_reference', 120)->nullable();
                $table->text('payment_notes')->nullable();
                $table->timestamp('payment_registered_at')->nullable();
                $table->string('payment_registered_by_name')->nullable();
                $table->timestamp('annulled_at')->nullable();
                $table->string('annulled_by_name')->nullable();
                $table->text('annulment_reason')->nullable();
                $table->timestamps();

                $table->index(['status', 'issued_at'], 'omi_status_issued_idx');
            });
        }

        if (! Schema::hasTable('operation_medication_invoice_items')) {
            Schema::create('operation_medication_invoice_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('operation_medication_invoice_id')->index('omi_items_invoice_idx');
                $table->foreignId('telemedicine_patient_medication_id')->nullable()->index('omi_items_medication_idx');
                $table->string('prescribed_name');
                $table->text('prescribed_indications')->nullable();
                $table->foreignId('operation_inventory_product_id')->nullable()->index('omi_items_product_idx');
                $table->foreignId('operation_inventory_id')->nullable();
                $table->foreignId('operation_inventory_ubication_id')->nullable();
                $table->string('product_code')->nullable();
                $table->string('product_name');
                $table->string('category_name')->nullable();
                $table->string('ubication_name')->nullable();
                $table->unsignedInteger('quantity');
                $table->decimal('unit_cost_usd', 12, 2);
                $table->decimal('profit_percentage', 8, 2);
                $table->decimal('unit_price_usd', 12, 2);
                $table->decimal('line_total_usd', 12, 2);
                $table->timestamp('restocked_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('operation_medication_invoice_events')) {
            Schema::create('operation_medication_invoice_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('operation_medication_invoice_id')->index('omi_events_invoice_idx');
                $table->string('event', 30);
                $table->text('description');
                $table->json('payload')->nullable();
                $table->foreignId('user_id')->nullable();
                $table->string('user_name')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_medication_invoice_events');
        Schema::dropIfExists('operation_medication_invoice_items');
        Schema::dropIfExists('operation_medication_invoices');
    }
};
