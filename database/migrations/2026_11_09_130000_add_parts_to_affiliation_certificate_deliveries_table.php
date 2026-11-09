<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partes del documento que lleva cada envío por correo del «Generador de
 * Certificado»: certificado, lista de afiliados y/o carnets, en cualquier
 * combinación. Nulo en los envíos anteriores, que se leen desde `content`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliation_certificate_deliveries') || Schema::hasColumn('affiliation_certificate_deliveries', 'parts')) {
            return;
        }

        Schema::table('affiliation_certificate_deliveries', function (Blueprint $table) {
            $table->json('parts')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('affiliation_certificate_deliveries') || ! Schema::hasColumn('affiliation_certificate_deliveries', 'parts')) {
            return;
        }

        Schema::table('affiliation_certificate_deliveries', function (Blueprint $table) {
            $table->dropColumn('parts');
        });
    }
};
