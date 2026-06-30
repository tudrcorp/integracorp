<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('individual_quotes', function (Blueprint $table): void {
            $table->index('owner_code', 'individual_quotes_owner_code_index');
            $table->index('code_agency', 'individual_quotes_code_agency_index');
            $table->index('agent_id', 'individual_quotes_agent_id_index');
            $table->index(['owner_code', 'agent_id'], 'individual_quotes_owner_code_agent_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('individual_quotes', function (Blueprint $table): void {
            $table->dropIndex('individual_quotes_owner_code_index');
            $table->dropIndex('individual_quotes_code_agency_index');
            $table->dropIndex('individual_quotes_agent_id_index');
            $table->dropIndex('individual_quotes_owner_code_agent_id_index');
        });
    }
};
