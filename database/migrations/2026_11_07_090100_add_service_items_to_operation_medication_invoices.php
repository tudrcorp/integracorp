<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las facturas al paciente cubren también laboratorios, estudios y especialistas no cubiertos.
 * - invoice_type: MEDICAMENTOS | LABORATORIOS | IMAGENOLOGIA | ESPECIALISTA (lo previo es MEDICAMENTOS).
 * - operation_service_order_id: la orden al proveedor que realiza el servicio facturado.
 * - En las líneas: el ítem recetado (laboratorio, estudio o especialista) y su fila del catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operation_medication_invoices')) {
            Schema::table('operation_medication_invoices', function (Blueprint $table): void {
                if (! Schema::hasColumn('operation_medication_invoices', 'invoice_type')) {
                    $table->string('invoice_type', 20)->default('MEDICAMENTOS')->after('status')->index('omi_invoice_type_idx');
                }

                if (! Schema::hasColumn('operation_medication_invoices', 'operation_service_order_id')) {
                    $table->foreignId('operation_service_order_id')->nullable()->after('operation_coordination_service_id')->index('omi_service_order_idx');
                }
            });
        }

        if (Schema::hasTable('operation_medication_invoice_items')) {
            Schema::table('operation_medication_invoice_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('operation_medication_invoice_items', 'item_type')) {
                    $table->string('item_type', 20)->default('MEDICAMENTOS')->after('operation_medication_invoice_id');
                }

                if (! Schema::hasColumn('operation_medication_invoice_items', 'source_item_id')) {
                    $table->unsignedBigInteger('source_item_id')->nullable()->after('telemedicine_patient_medication_id');
                }

                if (! Schema::hasColumn('operation_medication_invoice_items', 'catalog_item_id')) {
                    $table->unsignedBigInteger('catalog_item_id')->nullable()->after('source_item_id');
                }
            });

            if (! Schema::hasIndex('operation_medication_invoice_items', 'omi_items_source_idx')) {
                Schema::table('operation_medication_invoice_items', function (Blueprint $table): void {
                    $table->index(['item_type', 'source_item_id'], 'omi_items_source_idx');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('operation_medication_invoice_items', 'item_type')) {
            Schema::table('operation_medication_invoice_items', function (Blueprint $table): void {
                $table->dropIndex('omi_items_source_idx');
                $table->dropColumn(['item_type', 'source_item_id', 'catalog_item_id']);
            });
        }

        if (Schema::hasColumn('operation_medication_invoices', 'invoice_type')) {
            Schema::table('operation_medication_invoices', function (Blueprint $table): void {
                $table->dropIndex('omi_invoice_type_idx');
                $table->dropIndex('omi_service_order_idx');
                $table->dropColumn(['invoice_type', 'operation_service_order_id']);
            });
        }
    }
};
