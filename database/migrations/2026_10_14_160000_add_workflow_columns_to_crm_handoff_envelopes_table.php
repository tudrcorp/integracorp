<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que el CRM necesita para trabajar con volumen: cómo terminó cada caso, recordatorios,
 * avisos de demora ya enviados y la hora de la primera respuesta humana.
 * Índices con nombre corto: la cola filtra por casos abiertos y por quién los atiende.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const INDEXES = [
        'crm_env_open_idx' => 'closed_at,released_at,area',
        'crm_env_taker_idx' => 'taken_by,released_at',
        'crm_env_follow_idx' => 'follow_up_at',
        'crm_env_outcome_idx' => 'outcome_at',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('crm_handoff_envelopes')) {
            return;
        }

        Schema::table('crm_handoff_envelopes', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_handoff_envelopes', 'outcome')) {
                $table->string('outcome', 32)->nullable();
            }

            if (! Schema::hasColumn('crm_handoff_envelopes', 'outcome_note')) {
                $table->string('outcome_note', 255)->nullable();
            }

            if (! Schema::hasColumn('crm_handoff_envelopes', 'outcome_by')) {
                $table->unsignedBigInteger('outcome_by')->nullable();
            }

            if (! Schema::hasColumn('crm_handoff_envelopes', 'outcome_at')) {
                $table->timestamp('outcome_at')->nullable();
            }

            if (! Schema::hasColumn('crm_handoff_envelopes', 'follow_up_at')) {
                $table->timestamp('follow_up_at')->nullable();
            }

            if (! Schema::hasColumn('crm_handoff_envelopes', 'follow_up_note')) {
                $table->string('follow_up_note', 200)->nullable();
            }

            if (! Schema::hasColumn('crm_handoff_envelopes', 'follow_up_by')) {
                $table->unsignedBigInteger('follow_up_by')->nullable();
            }

            if (! Schema::hasColumn('crm_handoff_envelopes', 'follow_up_notified_at')) {
                $table->timestamp('follow_up_notified_at')->nullable();
            }

            if (! Schema::hasColumn('crm_handoff_envelopes', 'sla_alerted_at')) {
                $table->timestamp('sla_alerted_at')->nullable();
            }

            if (! Schema::hasColumn('crm_handoff_envelopes', 'stale_alerted_at')) {
                $table->timestamp('stale_alerted_at')->nullable();
            }

            if (! Schema::hasColumn('crm_handoff_envelopes', 'first_reply_at')) {
                $table->timestamp('first_reply_at')->nullable();
            }
        });

        foreach (self::INDEXES as $name => $columns) {
            if (! Schema::hasIndex('crm_handoff_envelopes', $name)) {
                Schema::table('crm_handoff_envelopes', function (Blueprint $table) use ($name, $columns): void {
                    $table->index(explode(',', $columns), $name);
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_handoff_envelopes')) {
            return;
        }

        foreach (array_keys(self::INDEXES) as $name) {
            if (Schema::hasIndex('crm_handoff_envelopes', $name)) {
                Schema::table('crm_handoff_envelopes', function (Blueprint $table) use ($name): void {
                    $table->dropIndex($name);
                });
            }
        }

        $columns = array_values(array_filter(
            ['outcome', 'outcome_note', 'outcome_by', 'outcome_at', 'follow_up_at', 'follow_up_note', 'follow_up_by', 'follow_up_notified_at', 'sla_alerted_at', 'stale_alerted_at', 'first_reply_at'],
            fn (string $column): bool => Schema::hasColumn('crm_handoff_envelopes', $column),
        ));

        if ($columns !== []) {
            Schema::table('crm_handoff_envelopes', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
