<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro directo de servicios médicos (Operaciones → Servicios médicos).
 *
 * Un analista registra laboratorios, estudios, especialistas, medicamentos,
 * traslado en ambulancia o ingreso a clínica sin pasar por la consulta médica.
 * Esta tabla es la traza completa de cada registro (quién, cuándo, para qué
 * paciente y caso, cada ítem con su cobertura, las coordinaciones creadas y
 * los consumos de cupo clínico). Cada coordinación creada apunta a su registro
 * con `direct_service_registration_id`, que es también su marca de origen.
 *
 * Aditiva e idempotente: se aplica con `migrate --path`, y si un intento previo
 * falló a mitad (tabla creada sin índices) se puede volver a correr.
 */
return new class extends Migration
{
    /**
     * @var array<string, string> columna => índice
     */
    private const REGISTRATION_INDEXES = [
        'telemedicine_patient_id' => 'odsr_patient_idx',
        'telemedicine_case_id' => 'odsr_case_idx',
        'registered_by_user_id' => 'odsr_registered_by_idx',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('operation_direct_service_registrations')) {
            Schema::create('operation_direct_service_registrations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('telemedicine_patient_id');
                $table->unsignedBigInteger('telemedicine_case_id');
                $table->boolean('case_created')->default(false);
                $table->unsignedBigInteger('registered_by_user_id')->nullable();
                $table->string('registered_by_name')->nullable();
                $table->string('service_line')->nullable();
                $table->text('diagnosis');
                $table->date('request_date');
                $table->date('service_date');
                $table->text('observations')->nullable();
                $table->unsignedBigInteger('prescribing_doctor_id')->nullable();
                $table->json('items');
                $table->json('coordination_ids');
                $table->json('clinical_usage_ids')->nullable();
                $table->timestamps();
            });
        }

        /*
         * Índices con nombre explícito y aparte del CREATE: los automáticos pasan de
         * 64 caracteres (límite de MySQL). Si un intento anterior dejó la tabla creada
         * sin índices, aquí se completan.
         */
        foreach (self::REGISTRATION_INDEXES as $column => $index) {
            if (! Schema::hasIndex('operation_direct_service_registrations', $index)) {
                Schema::table('operation_direct_service_registrations', function (Blueprint $table) use ($column, $index): void {
                    $table->index($column, $index);
                });
            }
        }

        if (! Schema::hasColumn('operation_coordination_services', 'direct_service_registration_id')) {
            Schema::table('operation_coordination_services', function (Blueprint $table): void {
                $table->unsignedBigInteger('direct_service_registration_id')->nullable();
            });
        }

        /** Nombre explícito: el automático pasa de 64 caracteres, el límite de MySQL. */
        if (! Schema::hasIndex('operation_coordination_services', 'ocs_direct_registration_idx')) {
            Schema::table('operation_coordination_services', function (Blueprint $table): void {
                $table->index('direct_service_registration_id', 'ocs_direct_registration_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('operation_coordination_services', 'direct_service_registration_id')) {
            Schema::table('operation_coordination_services', function (Blueprint $table): void {
                if (Schema::hasIndex('operation_coordination_services', 'ocs_direct_registration_idx')) {
                    $table->dropIndex('ocs_direct_registration_idx');
                }

                $table->dropColumn('direct_service_registration_id');
            });
        }

        Schema::dropIfExists('operation_direct_service_registrations');
    }
};
