<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Progreso del dibujo por tramos de los certificados grandes: cada tramo es un
 * job corto y reintentable, y el panel muestra «tramo X de Y». `pdf_progress_at`
 * permite detectar un dibujo detenido (worker caído, cola sin escuchar) y
 * ofrecer reintentar sin repetir los tramos ya listos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliation_certificate_issues')) {
            return;
        }

        Schema::table('affiliation_certificate_issues', function (Blueprint $table): void {
            if (! Schema::hasColumn('affiliation_certificate_issues', 'pdf_batch_id')) {
                $table->string('pdf_batch_id', 36)->nullable();
            }

            if (! Schema::hasColumn('affiliation_certificate_issues', 'pdf_chunks_total')) {
                $table->unsignedSmallInteger('pdf_chunks_total')->nullable();
            }

            if (! Schema::hasColumn('affiliation_certificate_issues', 'pdf_chunks_done')) {
                $table->unsignedSmallInteger('pdf_chunks_done')->default(0);
            }

            if (! Schema::hasColumn('affiliation_certificate_issues', 'pdf_pages')) {
                $table->unsignedSmallInteger('pdf_pages')->nullable();
            }

            if (! Schema::hasColumn('affiliation_certificate_issues', 'pdf_progress_at')) {
                $table->timestamp('pdf_progress_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('affiliation_certificate_issues')) {
            return;
        }

        Schema::table('affiliation_certificate_issues', function (Blueprint $table): void {
            foreach (['pdf_batch_id', 'pdf_chunks_total', 'pdf_chunks_done', 'pdf_pages', 'pdf_progress_at'] as $column) {
                if (Schema::hasColumn('affiliation_certificate_issues', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
