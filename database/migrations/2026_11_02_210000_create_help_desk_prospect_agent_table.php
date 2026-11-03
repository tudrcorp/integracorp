<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo entre un ticket del Helpdesk y los prospectos de Captación sobre los que se creó
 * (acción «Ticket de trabajo»).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('help_desk_prospect_agent')) {
            return;
        }

        Schema::create('help_desk_prospect_agent', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('help_desk_id');
            $table->unsignedBigInteger('prospect_agent_id');
            $table->timestamps();

            $table->unique(['help_desk_id', 'prospect_agent_id'], 'hdpa_ticket_prospect_unique');
            $table->index('prospect_agent_id', 'hdpa_prospect_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_prospect_agent');
    }
};
