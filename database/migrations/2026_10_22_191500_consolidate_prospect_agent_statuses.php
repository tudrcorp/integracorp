<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remapea el embudo antiguo a los 5 estatus actuales.
     * No altera el esquema: solo actualiza valores de `status`.
     */
    public function up(): void
    {
        if (! Schema::hasTable('prospect_agents') || ! Schema::hasColumn('prospect_agents', 'status')) {
            return;
        }

        $map = [
            'captación' => 'captación-prospección',
            'prospecto' => 'captación-prospección',
            'en-proceso' => 'en-proceso-negociación',
            'en-negociación' => 'en-proceso-negociación',
        ];

        foreach ($map as $from => $to) {
            DB::table('prospect_agents')
                ->where('status', $from)
                ->update(['status' => $to]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('prospect_agents') || ! Schema::hasColumn('prospect_agents', 'status')) {
            return;
        }

        DB::table('prospect_agents')
            ->where('status', 'captación-prospección')
            ->update(['status' => 'captación']);

        DB::table('prospect_agents')
            ->where('status', 'en-proceso-negociación')
            ->update(['status' => 'en-proceso']);
    }
};
