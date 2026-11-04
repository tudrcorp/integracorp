<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formas de pago de upgrades que la cotización le muestra al cliente (una o varias).
 * Reemplaza a `upgrade_billing_frequency` (una sola), que queda como respaldo de lectura.
 *
 * Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plan_generators') && ! Schema::hasColumn('plan_generators', 'upgrade_billing_frequencies')) {
            Schema::table('plan_generators', function (Blueprint $table): void {
                $table->json('upgrade_billing_frequencies')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('plan_generators') && Schema::hasColumn('plan_generators', 'upgrade_billing_frequencies')) {
            Schema::table('plan_generators', function (Blueprint $table): void {
                $table->dropColumn('upgrade_billing_frequencies');
            });
        }
    }
};
