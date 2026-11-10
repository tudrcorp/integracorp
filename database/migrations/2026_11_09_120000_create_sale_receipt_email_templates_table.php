<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copys del correo con el que Administración → Ventas envía el recibo de pago.
 *
 * Cada copy se dirige al afiliado, al responsable de la afiliación (agente y agencia) o a
 * ambos. Los copys no se borran: se desactivan, para que la auditoría de un envío pasado
 * siga apuntando a un texto existente. Siembra los dos copys aprobados por Administración.
 */
return new class extends Migration
{
    public function up(): void
    {
        /** La tabla ya se generalizó (2026_11_09_160000): volver a crearla dejaría copys duplicados. */
        if (Schema::hasTable('document_email_templates')) {
            return;
        }

        if (! Schema::hasTable('sale_receipt_email_templates')) {
            Schema::create('sale_receipt_email_templates', function (Blueprint $table): void {
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

                $table->index(['audience', 'is_active'], 'sret_audience_active_idx');
            });
        }

        $now = now();

        $seeds = [
            [
                'name' => 'Envío de recibos: Agencias - Agentes',
                'audience' => 'RESPONSABLE',
                'subject' => 'Recibo de pago Nro. {nro_recibo}',
                'body' => '<p><strong>Estimada Agencia, agente:</strong></p>'
                    .'<p>¡Es un gusto saludarle!</p>'
                    .'<p>Le confirmamos que hemos registrado con éxito el pago correspondiente a la cuota del Afiliado.</p>'
                    .'<p>Adjunto a este correo encontrará el recibo detallado con la fecha, el monto aplicado y los datos de la transacción.</p>'
                    .'<p>Apreciamos mucho su puntualidad y, sobre todo, la confianza que deposita en nuestros servicios.</p>'
                    .'<p>Saludos cordiales,<br><strong>El equipo de Tu Doctor en Casa</strong></p>',
                'sort_order' => 1,
            ],
            [
                'name' => 'Envío de recibos: In House',
                'audience' => 'AFILIADO',
                'subject' => 'Recibo de pago Nro. {nro_recibo}',
                'body' => '<p>Estimado(a) afiliado(a):</p>'
                    .'<p>¡Es un gusto saludarle!</p>'
                    .'<p>Le confirmamos que hemos registrado con éxito el pago correspondiente a la cuota del Afiliado.</p>'
                    .'<p>Adjunto a este correo encontrará el recibo detallado con la fecha, el monto aplicado y los datos de la transacción.</p>'
                    .'<p>Apreciamos mucho su puntualidad y, sobre todo, la confianza que deposita en nuestros servicios.</p>'
                    .'<p>Saludos cordiales,<br><strong>El equipo de Tu Doctor en Casa</strong></p>',
                'sort_order' => 2,
            ],
        ];

        foreach ($seeds as $seed) {
            $exists = DB::table('sale_receipt_email_templates')->where('name', $seed['name'])->exists();

            if ($exists) {
                continue;
            }

            DB::table('sale_receipt_email_templates')->insert(array_merge($seed, [
                'is_active' => true,
                'is_default' => true,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_receipt_email_templates');
    }
};
