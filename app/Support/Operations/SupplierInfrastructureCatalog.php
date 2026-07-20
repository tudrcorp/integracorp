<?php

declare(strict_types=1);

namespace App\Support\Operations;

final class SupplierInfrastructureCatalog
{
    /**
     * @return array<string, list<array{key: string, desc: string, label: string}>>
     */
    public static function groups(): array
    {
        return [
            'Especialidades Básicas y Hospitalización' => [
                ['key' => 'cirugia_general', 'desc' => 'descripcion_cirugia_general', 'label' => 'Cirugía General'],
                ['key' => 'medicina_interna', 'desc' => 'descripcion_medicina_interna', 'label' => 'Medicina Interna'],
                ['key' => 'obstetricia_ginecologia', 'desc' => 'descripcion_obstetricia_ginecologia', 'label' => 'Obstetricia y Ginecología'],
                ['key' => 'pediatria', 'desc' => 'descripcion_pediatria', 'label' => 'Pediatría'],
            ],
            'Especialidades Médicas y Quirúrgicas' => [
                ['key' => 'oftalmologia', 'desc' => 'descripcion_oftalmologia', 'label' => 'Unidad de Oftalmología'],
                ['key' => 'otorrinolaringologia', 'desc' => 'descripcion_otorrinolaringologia', 'label' => 'Unidad Otorrinolaringología'],
                ['key' => 'traumatologia_ortopedia', 'desc' => 'descripcion_traumatologia_ortopedia', 'label' => 'Unidad de Traumatología y Ortopedia'],
            ],
            'Unidades Médicas Clínicas Especializadas' => [
                ['key' => 'neumonologia', 'desc' => 'descripcion_neumonologia', 'label' => 'Unidad Neumonología'],
                ['key' => 'gastroenterologia', 'desc' => 'descripcion_gastroenterologia', 'label' => 'Unidad de Gastroenterología'],
                ['key' => 'cardiocirugia', 'desc' => 'descripcion_cardiocirugia', 'label' => 'Unidad Cardiocirugía'],
                ['key' => 'cardiologia', 'desc' => 'descripcion_cardiologia', 'label' => 'Unidad de Cardiología'],
                ['key' => 'oncologia', 'desc' => 'descripcion_encologogia', 'label' => 'Unidad de Oncología'],
                ['key' => 'psiquiatria', 'desc' => 'descripcion_psiquiatria', 'label' => 'Unidad de Psiquiatría'],
            ],
            'Servicios de Apoyo y Diagnóstico' => [
                ['key' => 'laboratorio_centro', 'desc' => 'descripcion_laboratorio_centro', 'label' => 'Laboratorio Clínico'],
                ['key' => 'anestesia_reanimacion', 'desc' => 'descripcion_anestesia_reanimacion', 'label' => 'Unidad de Anestesia y Reanimación'],
                ['key' => 'imagenologia_avanzada', 'desc' => 'descripcion_imagenologia_avanzada', 'label' => 'Imagenología Avanzada'],
                ['key' => 'unidad_uci', 'desc' => 'descripcion_unidad_uci', 'label' => 'Unidad de Cuidados Intensivos (UCI)'],
            ],
            'Otras facilidades' => [
                ['key' => 'estacionamiento_propio', 'desc' => 'descripcion_estacionamiento_propio', 'label' => 'Estacionamiento propio'],
                ['key' => 'ambulancias', 'desc' => 'descripcion_ambulancias', 'label' => 'Ambulancias'],
                ['key' => 'ascensor', 'desc' => 'descripcion_ascensor', 'label' => 'Ascensor operativo'],
                ['key' => 'banco_sangre', 'desc' => 'descripcion_banco_sangre', 'label' => 'Banco de sangre'],
            ],
            'OTRAS Unidades Médicas Especializadas Tipo AA' => [
                ['key' => 'nefrologia', 'desc' => 'descripcion_nefrologia', 'label' => 'Unidad de Nefrología'],
                ['key' => 'dialisis', 'desc' => 'descripcion_dialisis', 'label' => 'Unidad de diálisis'],
                ['key' => 'radioterapia', 'desc' => 'descripcion_radioterapia', 'label' => 'Unidad de radioterapia'],
                ['key' => 'quimioterapia', 'desc' => 'descripcion_quimioterapia', 'label' => 'Unidad de Quimioterapia'],
                ['key' => 'equipos_especiales_oftalmologia', 'desc' => 'descripcion_equipos_especiales_oftalmologia', 'label' => 'Unidad oftalmológicas con Equipos especiales de oftalmología'],
                ['key' => 'odontologia', 'desc' => 'descripcion_odontologia', 'label' => 'Unidad de Urgencias y emergencias Odontológicas'],
                ['key' => 'radioterapia_intraoperatoria', 'desc' => 'descripcion_radioterapia_intraoperatoria', 'label' => 'Radioterapia intraoperatoria'],
                ['key' => 'robotica', 'desc' => 'descripcion_robotica', 'label' => 'Equipo de cirugía robótica'],
                ['key' => 'otras_unidades_especiales', 'desc' => 'descripcion_otras_unidades_especiales', 'label' => 'Otras unidades especiales'],
            ],
        ];
    }

    /**
     * Columnas booleanas nuevas (no existían en el catálogo anterior).
     *
     * @return list<string>
     */
    public static function newBooleanColumns(): array
    {
        return [
            'cirugia_general',
            'medicina_interna',
            'obstetricia_ginecologia',
            'pediatria',
            'otorrinolaringologia',
            'traumatologia_ortopedia',
            'neumonologia',
            'gastroenterologia',
            'cardiocirugia',
            'cardiologia',
            'psiquiatria',
            'anestesia_reanimacion',
            'imagenologia_avanzada',
            'unidad_uci',
            'banco_sangre',
            'nefrologia',
            'radioterapia',
            'quimioterapia',
        ];
    }

    /**
     * @return list<string>
     */
    public static function newDescriptionColumns(): array
    {
        return array_map(
            static fn (string $column): string => 'descripcion_'.$column,
            self::newBooleanColumns(),
        );
    }

    /**
     * Campos relevantes para indicadores de actualización de infraestructura.
     *
     * @return list<string>
     */
    public static function relevantFieldNames(): array
    {
        $fields = [];

        foreach (self::groups() as $items) {
            foreach ($items as $item) {
                $fields[] = $item['key'];
                $fields[] = $item['desc'];
            }
        }

        return $fields;
    }
}
