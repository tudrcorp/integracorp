<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('corporate_agenda_activity_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')
                ->constrained(
                    table: 'corporate_agenda_activities',
                    indexName: 'caap_activity_fk'
                )
                ->cascadeOnDelete();
            $table->foreignId('rrhh_colaborador_id')
                ->constrained(
                    table: 'rrhh_colaboradors',
                    indexName: 'caap_colaborador_fk'
                )
                ->cascadeOnDelete();
            $table->string('invitation_status')->default('PENDING');
            $table->timestamps();

            $table->unique(['activity_id', 'rrhh_colaborador_id'], 'agenda_activity_collaborator_unique');
            $table->index(['invitation_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('corporate_agenda_activity_participants');
    }
};
