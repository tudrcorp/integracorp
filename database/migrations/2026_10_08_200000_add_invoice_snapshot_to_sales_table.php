<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos con los que se emitió la factura de una venta (fecha, a nombre de
 * quién, tasa, total en bolívares y vigencia). Hasta ahora solo se guardaba el
 * número (`invoice_generated`), y una factura no podía regenerarse idéntica.
 *
 * Aditiva e idempotente: se aplica con `migrate --path`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales', 'invoice_snapshot')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->json('invoice_snapshot')->nullable()->after('invoice_generated');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales', 'invoice_snapshot')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->dropColumn('invoice_snapshot');
            });
        }
    }
};
