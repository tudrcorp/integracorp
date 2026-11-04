<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite deshacer un ajuste de capitado volviendo al estado exacto anterior:
 * - `previous_reason` / `previous_plan_amount_usd`: el ajuste que había antes del cambio;
 * - `reverts_log_id`: la reversión apunta al cambio que deshace;
 * - `reverted_at` / `reverted_by_name`: el cambio ya fue revertido y no se revierte dos veces.
 *
 * Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliate_capitation_override_logs')) {
            return;
        }

        Schema::table('affiliate_capitation_override_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('affiliate_capitation_override_logs', 'previous_reason')) {
                $table->text('previous_reason')->nullable()->after('new_amount_usd');
            }

            if (! Schema::hasColumn('affiliate_capitation_override_logs', 'previous_plan_amount_usd')) {
                $table->decimal('previous_plan_amount_usd', 12, 2)->nullable()->after('previous_reason');
            }

            if (! Schema::hasColumn('affiliate_capitation_override_logs', 'reverts_log_id')) {
                $table->unsignedBigInteger('reverts_log_id')->nullable()->after('batch_uuid');
            }

            if (! Schema::hasColumn('affiliate_capitation_override_logs', 'reverted_at')) {
                $table->timestamp('reverted_at')->nullable()->after('reverts_log_id');
            }

            if (! Schema::hasColumn('affiliate_capitation_override_logs', 'reverted_by_name')) {
                $table->string('reverted_by_name')->nullable()->after('reverted_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('affiliate_capitation_override_logs')) {
            return;
        }

        Schema::table('affiliate_capitation_override_logs', function (Blueprint $table): void {
            foreach (['reverted_by_name', 'reverted_at', 'reverts_log_id', 'previous_plan_amount_usd', 'previous_reason'] as $column) {
                if (Schema::hasColumn('affiliate_capitation_override_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
