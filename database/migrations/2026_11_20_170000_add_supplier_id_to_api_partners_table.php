<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula un aliado API con su registro en Proveedores jurídicos (opcional).
 * Solo referencia: el acceso al API no depende de este vínculo.
 *
 * Aditiva e idempotente: se puede aplicar con `--path` aunque ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('api_partners') || Schema::hasColumn('api_partners', 'supplier_id')) {
            return;
        }

        Schema::table('api_partners', function (Blueprint $table): void {
            $table->unsignedBigInteger('supplier_id')->nullable()->after('contact_email');
            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('api_partners') || ! Schema::hasColumn('api_partners', 'supplier_id')) {
            return;
        }

        Schema::table('api_partners', function (Blueprint $table): void {
            $table->dropIndex(['supplier_id']);
            $table->dropColumn('supplier_id');
        });
    }
};
