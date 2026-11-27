<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Servicios principales del registro RETAIL (antes fijos en el código). `name` es la clave que queda en
 * `specific_service` de la coordinación: nunca cambia. Quitar un servicio lo desactiva; no se borra, porque
 * las coordinaciones y estadísticas viejas lo siguen nombrando.
 */
return new class extends Migration
{
    private const TABLE = 'retail_main_services';

    /** @var list<array{0: string, 1: string, 2: bool}> clave, nombre visible, protegido */
    private const SEED = [
        ['TELEMEDICINA', 'Telemedicina', true],
        ['AMD (ASISTENCIA MEDICA DOMICILIARIA)', 'AMD · Asistencia médica domiciliaria', true],
        ['TRASLADO EN AMBULANCIA', 'Traslado en ambulancia', false],
        ['CONSULTA ONLINE CON MEDICO ESPECIALISTA', 'Consulta online con especialista', false],
        ['URGEN CARE', 'Urgent care', false],
        ['APS', 'APS · Atención primaria', false],
        ['INGRESO A CLINICA', 'Ingreso a clínica', false],
        ['LECTURA DE RESULTADOS (LABORATORIO(S))', 'Lectura de resultados · Laboratorio', false],
        ['LECTURA DE RESULTADOS (IMAGENOLOGIA)', 'Lectura de resultados · Imagenología', false],
        ['EQUIPOS MEDICOS', 'Equipos médicos', false],
        ['SESION DE FISIOTERAPIA', 'Sesión de fisioterapia', false],
    ];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120)->unique();
                $table->string('label', 120);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_protected')->default(false);
                $table->unsignedSmallInteger('sort')->default(0);
                $table->string('created_by', 120)->nullable();
                $table->string('updated_by', 120)->nullable();
                $table->timestamps();

                $table->index(['is_active', 'sort'], 'retail_main_services_active_sort_idx');
            });
        }

        $now = now();

        foreach (self::SEED as $index => [$name, $label, $protected]) {
            if (DB::table(self::TABLE)->where('name', $name)->exists()) {
                continue;
            }

            DB::table(self::TABLE)->insert([
                'name' => $name,
                'label' => $label,
                'is_active' => true,
                'is_protected' => $protected,
                'sort' => ($index + 1) * 10,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
