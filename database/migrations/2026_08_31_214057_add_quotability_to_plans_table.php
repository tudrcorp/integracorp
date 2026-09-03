<?php

declare(strict_types=1);

use App\Enums\PlanQuotableScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El superadmin decide, por cada plan Dress Tylor, si entra a cotización y
 * en qué canal: individual, corporativo o ambos. Los BÁSICOS no usan estos
 * campos: siguen cotizándose como hasta ahora.
 *
 * El backfill deja los Dress Tylor ya existentes cotizables solo en
 * corporativo, que es el único canal donde ya aparecían.
 */
return new class extends Migration
{
    public function up(): void
    {
        $addedColumns = false;

        if (! Schema::hasColumn('plans', 'is_quotable')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->boolean('is_quotable')
                    ->default(false)
                    ->after('type');
            });
            $addedColumns = true;
        }

        if (! Schema::hasColumn('plans', 'quotable_in')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->string('quotable_in', 20)
                    ->nullable()
                    ->after('is_quotable');
                $table->index(['type', 'is_quotable', 'quotable_in'], 'plans_quotability_idx');
            });
            $addedColumns = true;
        }

        if ($addedColumns) {
            $this->backfillExistingDressTylorPlans();
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('plans', 'quotable_in')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->dropIndex('plans_quotability_idx');
                $table->dropColumn('quotable_in');
            });
        }

        if (Schema::hasColumn('plans', 'is_quotable')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->dropColumn('is_quotable');
            });
        }
    }

    private function backfillExistingDressTylorPlans(): void
    {
        if (! Schema::hasColumn('plans', 'is_quotable') || ! Schema::hasColumn('plans', 'quotable_in')) {
            return;
        }

        DB::table('plans')
            ->where('type', 'DRESS-TAILOR')
            ->where('is_quotable', false)
            ->whereNull('quotable_in')
            ->update([
                'is_quotable' => true,
                'quotable_in' => PlanQuotableScope::Corporate->value,
            ]);
    }
};
