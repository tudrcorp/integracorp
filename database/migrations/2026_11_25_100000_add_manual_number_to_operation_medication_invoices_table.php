<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Número de factura que el analista registra a mano (Operaciones → Facturas a pacientes →
 * Acciones → «Registrar N.º de factura»). Cuando existe, es el número que se muestra en
 * pantalla, PDF, envíos, reportes y cuentas por cobrar; `invoice_number` (FAC-000001) queda
 * como número interno del sistema y no cambia, porque la bitácora y el inventario lo citan.
 *
 * Aditiva e idempotente (aplicar con --path).
 */
return new class extends Migration
{
    private const TABLE = 'operation_medication_invoices';

    private const UNIQUE = 'omi_manual_number_unique';

    private const PERMISSION = 'registrar-numero-factura-medicamentos';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $columns = [
            'manual_invoice_number' => fn (Blueprint $table) => $table->string('manual_invoice_number', 40)->nullable()->after('invoice_number'),
            'manual_invoice_number_set_at' => fn (Blueprint $table) => $table->timestamp('manual_invoice_number_set_at')->nullable()->after('manual_invoice_number'),
            'manual_invoice_number_set_by_name' => fn (Blueprint $table) => $table->string('manual_invoice_number_set_by_name')->nullable()->after('manual_invoice_number_set_at'),
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn(self::TABLE, $column)) {
                Schema::table(self::TABLE, fn (Blueprint $table) => $definition($table));
            }
        }

        if (! Schema::hasIndex(self::TABLE, self::UNIQUE)) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->unique('manual_invoice_number', self::UNIQUE));
        }

        if (Schema::hasTable('permissions') && ! DB::table('permissions')->where('slug', self::PERMISSION)->exists()) {
            DB::table('permissions')->insert([
                'name' => 'Facturas a pacientes: registrar o corregir el número de factura',
                'slug' => self::PERMISSION,
                'module' => 'OPERACIONES',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (Schema::hasIndex(self::TABLE, self::UNIQUE)) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropUnique(self::UNIQUE));
        }

        foreach (['manual_invoice_number_set_by_name', 'manual_invoice_number_set_at', 'manual_invoice_number'] as $column) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
