<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los copys de correo dejan de ser solo del recibo de pago: la misma tabla guarda los del aviso de
 * cobro (Gestión de Cobranza y Cobranza Por Mes), distinguidos por `document`.
 *
 * Renombra `sale_receipt_email_templates` → `document_email_templates`, agrega `document` (los
 * copys existentes quedan como RECIBO_PAGO) y siembra el copy aprobado del aviso para agencias.
 * Idempotente: puede correr sobre una base que ya tenga parte del cambio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sale_receipt_email_templates') && ! Schema::hasTable('document_email_templates')) {
            Schema::rename('sale_receipt_email_templates', 'document_email_templates');
        }

        if (! Schema::hasTable('document_email_templates')) {
            Schema::create('document_email_templates', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 150);
                $table->string('audience', 20);
                $table->string('subject', 200);
                $table->longText('body');
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('document_email_templates', 'document')) {
            Schema::table('document_email_templates', function (Blueprint $table): void {
                $table->string('document', 30)->default('RECIBO_PAGO')->after('id');
            });
        }

        if (Schema::hasIndex('document_email_templates', 'sret_audience_active_idx')) {
            Schema::table('document_email_templates', function (Blueprint $table): void {
                $table->dropIndex('sret_audience_active_idx');
            });
        }

        if (! Schema::hasIndex('document_email_templates', 'det_document_audience_active_idx')) {
            Schema::table('document_email_templates', function (Blueprint $table): void {
                $table->index(['document', 'audience', 'is_active'], 'det_document_audience_active_idx');
            });
        }

        $name = 'Envío de avisos: Agencias - Agentes';

        $exists = DB::table('document_email_templates')
            ->where('document', 'AVISO_COBRO')
            ->where('name', $name)
            ->exists();

        if (! $exists) {
            $now = now();

            DB::table('document_email_templates')->insert([
                'document' => 'AVISO_COBRO',
                'name' => $name,
                'audience' => 'RESPONSABLE',
                'subject' => 'Aviso de cobro Nro. {nro_aviso}',
                'body' => '<p><strong>Estimada Agencia, agente:</strong></p>'
                    .'<p>Desde el Departamento de Administración – Unidad de Cobranzas de Tu Dr. En Casa es un gusto saludarle.</p>'
                    .'<p>Por medio del presente correo, le compartimos <strong>Aviso de Cobro</strong> correspondiente a su afiliado.</p>'
                    .'<p><strong>Detalle del servicio:</strong></p>'
                    .'<ul><li><strong>Plan:</strong> {plan}</li><li><strong>Período de vigencia:</strong> {periodo_desde} al {periodo_hasta}</li></ul>'
                    .'<p><strong>Monto a pagar:</strong> {monto}</p>'
                    .'<p>Asimismo, adjunto los métodos de pago disponibles para su comodidad.</p>'
                    .'<p>Quedamos a su entera disposición para cualquier duda o consulta que requiera.</p>'
                    .'<p>Atentamente,<br><strong>El equipo de Tu Doctor en Casa</strong><br>Unidad de Cobranzas</p>',
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 1,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('document_email_templates')) {
            return;
        }

        DB::table('document_email_templates')->where('document', 'AVISO_COBRO')->delete();

        if (Schema::hasIndex('document_email_templates', 'det_document_audience_active_idx')) {
            Schema::table('document_email_templates', function (Blueprint $table): void {
                $table->dropIndex('det_document_audience_active_idx');
            });
        }

        if (Schema::hasColumn('document_email_templates', 'document')) {
            Schema::table('document_email_templates', function (Blueprint $table): void {
                $table->dropColumn('document');
            });
        }

        if (! Schema::hasTable('sale_receipt_email_templates')) {
            Schema::rename('document_email_templates', 'sale_receipt_email_templates');
        }
    }
};
