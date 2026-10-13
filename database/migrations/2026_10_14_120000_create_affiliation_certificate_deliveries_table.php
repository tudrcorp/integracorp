<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envíos por correo del «Generador de Certificado»: qué se mandó (documento
 * completo o solo algunos carnets), a quién, quién lo pidió y cómo salió
 * (adjunto o enlace de descarga, cuando el PDF pesa demasiado para adjuntarlo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('affiliation_certificate_deliveries')) {
            return;
        }

        Schema::create('affiliation_certificate_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliation_certificate_issue_id')
                ->constrained('affiliation_certificate_issues', indexName: 'aff_cert_deliveries_issue_fk')
                ->cascadeOnDelete();
            $table->string('content', 20);
            $table->json('carnet_affiliate_ids')->nullable();
            $table->json('recipients');
            $table->json('delivered_to')->nullable();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('delivery_method', 20)->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamp('link_expires_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users', indexName: 'aff_cert_deliveries_user_fk')->nullOnDelete();
            $table->string('sent_by_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliation_certificate_deliveries');
    }
};
