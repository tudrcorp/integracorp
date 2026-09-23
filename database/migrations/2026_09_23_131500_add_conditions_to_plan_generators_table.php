<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Condiciones comerciales que el analista escribe a mano en una cotización
 * derivada. Van debajo del total grupal, en la ficha y en el PDF.
 *
 * Es una lista ordenada (una o varias). El registro base no las usa: se cargan
 * al derivar. JSON nullable para no tocar las cotizaciones que ya existen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('plan_generators', 'conditions')) {
            return;
        }

        Schema::table('plan_generators', function (Blueprint $table): void {
            $table->json('conditions')->nullable()->after('include_monthly_total');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('plan_generators', 'conditions')) {
            return;
        }

        Schema::table('plan_generators', function (Blueprint $table): void {
            $table->dropColumn('conditions');
        });
    }
};
