<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logo propio de la cotización del generador de planes. Nulo = el de Tu Doctor en Casa de siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('plan_generators', 'logo_path')) {
            return;
        }

        Schema::table('plan_generators', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('brand_color');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('plan_generators', 'logo_path')) {
            return;
        }

        Schema::table('plan_generators', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }
};
