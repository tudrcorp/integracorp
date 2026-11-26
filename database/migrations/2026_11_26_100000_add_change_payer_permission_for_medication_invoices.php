<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permiso de Operaciones → Facturas a pacientes → «Cambiar a nombre de quién va» (individual y
 * masivo): el paciente o el grupo corporativo al que pertenece. Sin cambios de esquema: la
 * factura ya guarda el responsable de pago (`payer_*`).
 *
 * Idempotente (aplicar con --path).
 */
return new class extends Migration
{
    private const PERMISSION = 'cambiar-responsable-factura-medicamentos';

    public function up(): void
    {
        if (Schema::hasTable('permissions') && ! DB::table('permissions')->where('slug', self::PERMISSION)->exists()) {
            DB::table('permissions')->insert([
                'name' => 'Facturas a pacientes: cambiar a nombre de quién va (paciente o grupo corporativo), una o varias',
                'slug' => self::PERMISSION,
                'module' => 'OPERACIONES',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('slug', self::PERMISSION)->delete();
        }
    }
};
