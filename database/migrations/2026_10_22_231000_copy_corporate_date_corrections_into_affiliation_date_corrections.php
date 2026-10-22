<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La corrección de vigencia nació solo para corporativas (`affiliation_corporate_date_corrections`)
 * y pasó a ser genérica (`affiliation_date_corrections`). Copia las correcciones existentes para
 * que sigan siendo reversibles. No borra la tabla vieja ni sus filas.
 *
 * Idempotente: copia solo lo que aún no existe (por `public_id`). Sin la tabla vieja no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliation_corporate_date_corrections') || ! Schema::hasTable('affiliation_date_corrections')) {
            return;
        }

        $existing = DB::table('affiliation_date_corrections')->pluck('public_id')->all();

        foreach (DB::table('affiliation_corporate_date_corrections')->orderBy('id')->get() as $row) {
            if (in_array($row->public_id, $existing, true)) {
                continue;
            }

            DB::table('affiliation_date_corrections')->insert([
                'public_id' => $row->public_id,
                'affiliation_type' => 'CORPORATIVA',
                'affiliation_id' => $row->affiliation_corporate_id,
                'affiliation_code' => $row->affiliation_code,
                'status' => $row->status,
                'scope' => 'first_year',
                'activated_at_before' => $row->activated_at_before,
                'effective_date_before' => $row->effective_date_before,
                'new_date' => $row->new_date,
                'reason' => $row->reason,
                'snapshot_before' => $row->snapshot_before,
                'snapshot_after' => $row->snapshot_after,
                'summary' => $row->summary,
                'applied_by' => $row->applied_by,
                'applied_by_name' => $row->applied_by_name,
                'applied_at' => $row->applied_at,
                'rolled_back_by' => $row->rolled_back_by,
                'rolled_back_by_name' => $row->rolled_back_by_name,
                'rolled_back_at' => $row->rolled_back_at,
                'rollback_note' => $row->rollback_note,
                'rollback_summary' => $row->rollback_summary,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        // Solo copió filas; la tabla de origen sigue intacta.
    }
};
