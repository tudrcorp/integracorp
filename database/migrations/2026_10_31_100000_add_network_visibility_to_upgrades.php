<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién de la red comercial puede cotizar cada upgrade: nadie (solo Negocios, el valor con el que
 * arrancan todos), toda la red o las agencias y agentes elegidos en `upgrade_network_targets`.
 *
 * Idempotente: se aplica con --path y puede correrse dos veces sin daño.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('upgrades', 'network_visibility')) {
            Schema::table('upgrades', function (Blueprint $table): void {
                $table->string('network_visibility', 20)->default('SOLO_NEGOCIOS')->after('status')->index('upgrades_network_visibility_idx');
            });
        }

        if (Schema::hasTable('upgrade_network_targets')) {
            return;
        }

        Schema::create('upgrade_network_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('upgrade_id')->constrained('upgrades')->cascadeOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->cascadeOnDelete();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->unique(['upgrade_id', 'agency_id'], 'upgrade_targets_agency_unique');
            $table->unique(['upgrade_id', 'agent_id'], 'upgrade_targets_agent_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upgrade_network_targets');

        if (Schema::hasColumn('upgrades', 'network_visibility')) {
            Schema::table('upgrades', function (Blueprint $table): void {
                $table->dropIndex('upgrades_network_visibility_idx');
                $table->dropColumn('network_visibility');
            });
        }
    }
};
