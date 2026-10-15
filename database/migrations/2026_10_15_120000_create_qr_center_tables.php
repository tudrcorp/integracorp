<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centro de QR de tarjetas (panel Negocios).
 *
 * - Destinos: a dónde lleva el QR de cada tarjeta (alcance + plan). El QR impreso
 *   codifica un enlace estable `/q/{code}`; cambiar el destino no exige reimprimir.
 * - Versiones de destino: historial completo (imagen, destino, estilo y autor);
 *   restaurar crea una versión nueva, nunca borra.
 * - Páginas alojadas: HTML subido que se publica en `/p/{slug}`, con sus versiones.
 * - Lecturas: cada escaneo del QR y cada visita a una página, sin datos personales.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qr_hosted_pages')) {
            Schema::create('qr_hosted_pages', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 80)->unique();
                $table->unsignedBigInteger('current_version_id')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'qr_hosted_pages_creator_fk')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'qr_hosted_pages_updater_fk')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('qr_hosted_page_versions')) {
            Schema::create('qr_hosted_page_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('qr_hosted_page_id')->constrained('qr_hosted_pages', indexName: 'qr_page_versions_page_fk')->restrictOnDelete();
                $table->unsignedInteger('version');
                $table->string('file_path');
                $table->string('original_name')->nullable();
                $table->unsignedInteger('size_bytes');
                $table->char('sha256', 64);
                $table->json('review')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'qr_page_versions_creator_fk')->nullOnDelete();
                $table->string('created_by_name')->nullable();
                $table->timestamps();

                $table->unique(['qr_hosted_page_id', 'version'], 'qr_page_versions_page_version_unique');
            });
        }

        if (! Schema::hasTable('qr_destinations')) {
            Schema::create('qr_destinations', function (Blueprint $table) {
                $table->id();
                $table->string('code', 16)->unique();
                $table->string('scope', 20);
                // 0 = sin plan (inclusión): una columna NOT NULL permite que el índice único funcione en MySQL.
                $table->unsignedBigInteger('plan_key')->default(0);
                $table->foreignId('plan_id')->nullable()->constrained('plans', indexName: 'qr_destinations_plan_fk')->nullOnDelete();
                $table->string('target_type', 20);
                $table->unsignedBigInteger('current_version_id')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'qr_destinations_creator_fk')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'qr_destinations_updater_fk')->nullOnDelete();
                $table->timestamps();

                $table->unique(['scope', 'plan_key'], 'qr_destinations_scope_plan_unique');
            });
        }

        if (! Schema::hasTable('qr_destination_versions')) {
            Schema::create('qr_destination_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('qr_destination_id')->constrained('qr_destinations', indexName: 'qr_dest_versions_dest_fk')->restrictOnDelete();
                $table->unsignedInteger('version');
                $table->string('target_type', 20);
                $table->text('target_url')->nullable();
                $table->foreignId('qr_hosted_page_id')->nullable()->constrained('qr_hosted_pages', indexName: 'qr_dest_versions_page_fk')->restrictOnDelete();
                $table->json('style')->nullable();
                $table->string('image_path')->nullable();
                $table->char('image_sha256', 64)->nullable();
                $table->string('note')->nullable();
                $table->unsignedBigInteger('restored_from_version_id')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'qr_dest_versions_creator_fk')->nullOnDelete();
                $table->string('created_by_name')->nullable();
                $table->timestamps();

                $table->unique(['qr_destination_id', 'version'], 'qr_dest_versions_dest_version_unique');
            });
        }

        if (! Schema::hasTable('qr_scans')) {
            Schema::create('qr_scans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('qr_destination_id')->nullable()->constrained('qr_destinations', indexName: 'qr_scans_dest_fk')->nullOnDelete();
                $table->foreignId('qr_hosted_page_id')->nullable()->constrained('qr_hosted_pages', indexName: 'qr_scans_page_fk')->nullOnDelete();
                $table->string('kind', 10);
                $table->char('visitor_hash', 64);
                $table->string('device', 12);
                $table->boolean('is_bot')->default(false);
                $table->timestamp('created_at')->useCurrent();

                $table->index(['qr_destination_id', 'created_at'], 'qr_scans_dest_created_idx');
                $table->index(['qr_hosted_page_id', 'created_at'], 'qr_scans_page_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_scans');
        Schema::dropIfExists('qr_destination_versions');
        Schema::dropIfExists('qr_destinations');
        Schema::dropIfExists('qr_hosted_page_versions');
        Schema::dropIfExists('qr_hosted_pages');
    }
};
