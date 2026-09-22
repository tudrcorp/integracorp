<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prospect_agent_observations') || ! Schema::hasColumn('prospect_agent_observations', 'observation')) {
            return;
        }

        if ($this->observationIsLongText()) {
            return;
        }

        Schema::table('prospect_agent_observations', function (Blueprint $table): void {
            $table->longText('observation')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('prospect_agent_observations') || ! Schema::hasColumn('prospect_agent_observations', 'observation')) {
            return;
        }

        Schema::table('prospect_agent_observations', function (Blueprint $table): void {
            $table->string('observation')->nullable(false)->change();
        });
    }

    private function observationIsLongText(): bool
    {
        $column = collect(Schema::getColumns('prospect_agent_observations'))
            ->firstWhere('name', 'observation');

        $type = strtolower((string) ($column['type'] ?? ''));

        return str_contains($type, 'longtext');
    }
};
