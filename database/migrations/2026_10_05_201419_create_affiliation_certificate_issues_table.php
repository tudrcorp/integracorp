<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emisiones del «Generador de Certificado» (panel de Negocios).
 *
 * Cada certificado impreso lleva una clave y un QR hacia la verificación pública;
 * la clave se busca aquí por índice único, no en la bitácora general. Se guarda una
 * foto de lo que decía el documento al emitirse, y la página pública muestra
 * además el estado vigente de la afiliación.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('affiliation_certificate_issues')) {
            return;
        }

        Schema::create('affiliation_certificate_issues', function (Blueprint $table) {
            $table->id();
            $table->string('verification_key', 40)->unique();
            $table->string('affiliation_type', 20);
            $table->unsignedBigInteger('affiliation_id');
            $table->string('affiliation_code');
            $table->string('plan_name')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->date('paid_until')->nullable();
            $table->boolean('current_period_paid')->default(false);
            $table->unsignedInteger('affiliates_count')->default(0);
            $table->unsignedInteger('carnets_count')->default(0);
            $table->json('carnet_affiliate_ids')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('issued_by_name')->nullable();
            $table->timestamps();

            $table->index(['affiliation_type', 'affiliation_id'], 'aff_cert_issues_affiliation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliation_certificate_issues');
    }
};
