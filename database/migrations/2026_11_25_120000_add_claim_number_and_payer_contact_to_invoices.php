<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Factura a pacientes con el formato de la factura de Tu Dr. en Casa:
 *
 * - N.º de siniestro en el caso de telemedicina. Lo registra el analista desde la ficha del
 *   caso; sin él no se emite ninguna factura del caso. La factura lo copia al emitirse.
 * - Teléfono y correo del cliente (responsable de pago) en la cotización y en la factura.
 *
 * Aditiva e idempotente (aplicar con --path).
 */
return new class extends Migration
{
    private const PERMISSION = 'registrar-siniestro-caso';

    public function up(): void
    {
        $this->addColumns('telemedicine_cases', [
            'claim_number' => fn (Blueprint $table) => $table->string('claim_number', 60)->nullable()->after('code'),
            'claim_number_set_at' => fn (Blueprint $table) => $table->timestamp('claim_number_set_at')->nullable()->after('claim_number'),
            'claim_number_set_by_name' => fn (Blueprint $table) => $table->string('claim_number_set_by_name')->nullable()->after('claim_number_set_at'),
        ]);

        $this->addColumns('operation_medication_invoices', [
            'claim_number' => fn (Blueprint $table) => $table->string('claim_number', 60)->nullable()->after('case_code'),
            'payer_phone' => fn (Blueprint $table) => $table->string('payer_phone', 40)->nullable()->after('payer_address'),
            'payer_email' => fn (Blueprint $table) => $table->string('payer_email', 150)->nullable()->after('payer_phone'),
        ]);

        $this->addColumns('operation_quote_generators', [
            'invoice_payer_phone' => fn (Blueprint $table) => $table->string('invoice_payer_phone', 40)->nullable()->after('invoice_payer_address'),
            'invoice_payer_email' => fn (Blueprint $table) => $table->string('invoice_payer_email', 150)->nullable()->after('invoice_payer_phone'),
        ]);

        if (Schema::hasTable('permissions') && ! DB::table('permissions')->where('slug', self::PERMISSION)->exists()) {
            DB::table('permissions')->insert([
                'name' => 'Casos de telemedicina: registrar o corregir el N.º de siniestro',
                'slug' => self::PERMISSION,
                'module' => 'OPERACIONES',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $this->dropColumns('operation_quote_generators', ['invoice_payer_email', 'invoice_payer_phone']);
        $this->dropColumns('operation_medication_invoices', ['payer_email', 'payer_phone', 'claim_number']);
        $this->dropColumns('telemedicine_cases', ['claim_number_set_by_name', 'claim_number_set_at', 'claim_number']);
    }

    /**
     * @param  array<string, Closure(Blueprint): mixed>  $columns
     */
    private function addColumns(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn($tableName, $column)) {
                Schema::table($tableName, fn (Blueprint $table) => $definition($table));
            }
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropColumns(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        foreach ($columns as $column) {
            if (Schema::hasColumn($tableName, $column)) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
