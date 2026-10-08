<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Departamento Médico Outsourcing en proveedores naturales (`doctor_nurses`) y
 * jurídicos (`suppliers`).
 *
 * - `is_outsourcing_medical_department`: el proveedor atiende población bajo
 *   esquema de capitación.
 * - `outsourcing_monthly_fee_per_affiliate_usd`: tarifa mensual en USD por cada
 *   afiliado asignado. Nula cuando el proveedor no es outsourcing.
 *
 * Aditiva e idempotente: se aplica con `migrate --path`.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = ['doctor_nurses', 'suppliers'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'is_outsourcing_medical_department')) {
                    $table->boolean('is_outsourcing_medical_department')->default(false)->index();
                }

                if (! Schema::hasColumn($tableName, 'outsourcing_monthly_fee_per_affiliate_usd')) {
                    $table->decimal('outsourcing_monthly_fee_per_affiliate_usd', 10, 2)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'outsourcing_monthly_fee_per_affiliate_usd')) {
                    $table->dropColumn('outsourcing_monthly_fee_per_affiliate_usd');
                }

                if (Schema::hasColumn($tableName, 'is_outsourcing_medical_department')) {
                    $table->dropIndex(['is_outsourcing_medical_department']);
                    $table->dropColumn('is_outsourcing_medical_department');
                }
            });
        }
    }
};
