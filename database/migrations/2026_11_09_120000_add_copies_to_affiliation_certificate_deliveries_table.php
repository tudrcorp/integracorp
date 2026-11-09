<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Copias del envío por correo del «Generador de Certificado»: correos testigos
 * (CC visible) y copia oculta (CCO), más la marca de que esas copias ya
 * salieron, para que un reintento del job no las repita.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliation_certificate_deliveries')) {
            return;
        }

        Schema::table('affiliation_certificate_deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('affiliation_certificate_deliveries', 'cc_recipients')) {
                $table->json('cc_recipients')->nullable()->after('recipients');
            }

            if (! Schema::hasColumn('affiliation_certificate_deliveries', 'bcc_recipients')) {
                $table->json('bcc_recipients')->nullable()->after('cc_recipients');
            }

            if (! Schema::hasColumn('affiliation_certificate_deliveries', 'copies_sent_at')) {
                $table->timestamp('copies_sent_at')->nullable()->after('delivered_to');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('affiliation_certificate_deliveries')) {
            return;
        }

        Schema::table('affiliation_certificate_deliveries', function (Blueprint $table) {
            foreach (['copies_sent_at', 'bcc_recipients', 'cc_recipients'] as $column) {
                if (Schema::hasColumn('affiliation_certificate_deliveries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
