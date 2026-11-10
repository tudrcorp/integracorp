<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el estatus «CLINICA CERRADA» al catálogo de proveedores jurídicos. Idempotente.
 */
return new class extends Migration
{
    private const DESCRIPTION = 'CLINICA CERRADA';

    public function up(): void
    {
        if (! Schema::hasTable('supplier_estatus_sistemas')) {
            return;
        }

        $exists = DB::table('supplier_estatus_sistemas')
            ->where('description', self::DESCRIPTION)
            ->exists();

        if ($exists) {
            return;
        }

        $row = [
            'description' => self::DESCRIPTION,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('supplier_estatus_sistemas', 'created_by')) {
            $row['created_by'] = 'SYSTEM';
        }

        if (Schema::hasColumn('supplier_estatus_sistemas', 'updated_by')) {
            $row['updated_by'] = 'SYSTEM';
        }

        DB::table('supplier_estatus_sistemas')->insert($row);
    }

    public function down(): void
    {
        if (! Schema::hasTable('supplier_estatus_sistemas')) {
            return;
        }

        $inUse = false;

        if (Schema::hasTable('suppliers') && Schema::hasColumn('suppliers', 'status_sistema')) {
            $inUse = DB::table('suppliers')
                ->where('status_sistema', self::DESCRIPTION)
                ->exists();
        }

        if ($inUse) {
            return;
        }

        DB::table('supplier_estatus_sistemas')
            ->where('description', self::DESCRIPTION)
            ->delete();
    }
};
