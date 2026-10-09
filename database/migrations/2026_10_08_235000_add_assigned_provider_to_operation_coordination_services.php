<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proveedor asignado a una coordinación desde el registro RETAIL: natural
 * (doctor_nurses), jurídico (suppliers) o aliado corporativo (corporate_allies).
 *
 * No reutiliza `supplier_id`, que es el proveedor de gestión dueño de la
 * coordinación y decide su visibilidad en el portal del proveedor.
 * Nombres de claves cortos: los automáticos pasan de 64 caracteres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_coordination_services', function (Blueprint $table): void {
            if (! Schema::hasColumn('operation_coordination_services', 'assigned_provider_type')) {
                $table->string('assigned_provider_type', 20)->nullable();
            }

            if (! Schema::hasColumn('operation_coordination_services', 'assigned_supplier_id')) {
                $table->foreignId('assigned_supplier_id')->nullable()
                    ->constrained('suppliers', 'id', 'ocs_assigned_supplier_fk')->nullOnDelete();
            }

            if (! Schema::hasColumn('operation_coordination_services', 'assigned_doctor_nurse_id')) {
                $table->foreignId('assigned_doctor_nurse_id')->nullable()
                    ->constrained('doctor_nurses', 'id', 'ocs_assigned_doctor_nurse_fk')->nullOnDelete();
            }

            if (! Schema::hasColumn('operation_coordination_services', 'assigned_corporate_ally_id')) {
                $table->foreignId('assigned_corporate_ally_id')->nullable()
                    ->constrained('corporate_allies', 'id', 'ocs_assigned_corporate_ally_fk')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('operation_coordination_services', function (Blueprint $table): void {
            foreach ([
                'assigned_corporate_ally_id' => 'ocs_assigned_corporate_ally_fk',
                'assigned_doctor_nurse_id' => 'ocs_assigned_doctor_nurse_fk',
                'assigned_supplier_id' => 'ocs_assigned_supplier_fk',
            ] as $column => $foreignKey) {
                if (Schema::hasColumn('operation_coordination_services', $column)) {
                    $table->dropForeign($foreignKey);
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('operation_coordination_services', 'assigned_provider_type')) {
                $table->dropColumn('assigned_provider_type');
            }
        });
    }
};
