<?php

declare(strict_types=1);

namespace App\Support\OrgStructure;

/**
 * Organigrama de muestra para «Ver un ejemplo» en el estudio.
 *
 * Se arma en memoria, sin tocar la base: las personas son nombres de ejemplo y
 * los cambios que se hagan viven solo en el navegador. Sirve para conocer el
 * estudio antes de crear un organigrama real.
 */
final class OrgStructureDemo
{
    /**
     * @var list<array{0: string, 1: string|null, 2: string|null, 3: string, 4: string|null, 5?: string}>
     */
    private const TREE = [
        ['Presidencia', null, 'Ana Martínez', 'LINEA', null],
        ['Asesoría Legal', 'Presidencia', null, 'STAFF', 'slate'],
        ['Gerencia Comercial', 'Presidencia', 'Luis Herrera', 'LINEA', 'green'],
        ['Gerencia de Operaciones', 'Presidencia', 'Carmen Díaz', 'LINEA', 'amber'],
        ['Gerencia de Administración', 'Presidencia', null, 'LINEA', 'red'],
        ['Coordinación de Ventas', 'Gerencia Comercial', 'Pedro Salas', 'LINEA', 'green'],
        ['Coordinación de Alianzas', 'Gerencia Comercial', null, 'LINEA', 'green'],
        ['Coordinación de Proveedores', 'Gerencia de Operaciones', 'Rosa León', 'LINEA', 'amber', 'INTERINO'],
        ['Coordinación de Logística', 'Gerencia de Operaciones', 'Jorge Peña', 'LINEA', 'amber'],
        ['Analista de compras', 'Coordinación de Proveedores', 'Elena Ruiz', 'LINEA', 'amber'],
        ['Analista de red', 'Coordinación de Proveedores', null, 'LINEA', 'amber'],
        ['Cobranza', 'Gerencia de Administración', 'Marta Gil', 'LINEA', 'red'],
        ['Contabilidad', 'Gerencia de Administración', 'Raúl Soto', 'LINEA', 'red'],
        ['Nómina', 'Gerencia de Administración', 'Inés Mora', 'LINEA', 'red'],
        ['Tesorería', 'Gerencia de Administración', null, 'LINEA', 'red'],
        ['Compras', 'Gerencia de Administración', 'Tomás Vega', 'LINEA', 'red'],
        ['Archivo', 'Gerencia de Administración', 'Lucía Pardo', 'LINEA', 'red'],
    ];

    /**
     * @var list<array{0: string, 1: string}>
     */
    private const UNPLACED = [
        ['Sofía Ramos', 'Analista'],
        ['Diego Campos', 'Asistente'],
        ['Valeria Núñez', 'Analista'],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        $nodes = [];
        $ids = [];
        $sortByParent = [];
        $nextId = 1;

        foreach (self::TREE as $row) {
            [$title, $parentTitle, $person, $kind, $color] = $row;
            $role = $row[5] ?? 'TITULAR';
            $id = $nextId++;
            $ids[$title] = $id;
            $parentId = $parentTitle !== null ? $ids[$parentTitle] : null;
            $sortByParent[$parentId ?? 0] = ($sortByParent[$parentId ?? 0] ?? -1) + 1;

            $nodes[] = [
                'id' => $id,
                'key' => sprintf('00000000-0000-4000-8000-%012d', $id),
                'parent' => $parentId,
                'title' => $title,
                'kind' => $kind,
                'seats' => 1,
                'color' => $color,
                'sort' => $sortByParent[$parentId ?? 0],
                'cargo_id' => null,
                'cargo' => null,
                'depto_id' => null,
                'depto' => null,
                'occupants' => $person === null ? [] : [self::occupant($id, $person, $role)],
                'vacancies' => $person === null ? 1 : 0,
            ];
        }

        $unassigned = [];

        foreach (self::UNPLACED as $index => [$name, $cargo]) {
            $unassigned[] = ['id' => 9001 + $index, 'name' => $name, 'initials' => OrgStructureRrhh::initials($name), 'avatar' => null, 'cargo_id' => null, 'cargo' => $cargo, 'depto_id' => null];
        }

        $people = count(array_filter($nodes, fn (array $node): bool => $node['occupants'] !== []));
        $areas = [
            ['id' => 1, 'name' => 'Comercial', 'people' => 3],
            ['id' => 2, 'name' => 'Operaciones', 'people' => 5],
            ['id' => 3, 'name' => 'Administración', 'people' => 6],
            ['id' => 4, 'name' => 'Tecnología', 'people' => 0],
        ];
        $cargos = [
            ['id' => 1, 'name' => 'Analista de datos', 'departamento_id' => 4, 'people' => 0],
            ['id' => 2, 'name' => 'Asistente administrativo', 'departamento_id' => 3, 'people' => 1],
            ['id' => 3, 'name' => 'Ejecutivo de ventas', 'departamento_id' => 1, 'people' => 1],
        ];

        return [
            'mode' => 'edit',
            'demo' => true,
            'payload' => [
                'version' => ['id' => 0, 'name' => 'Organigrama de ejemplo', 'status' => 'BORRADOR', 'status_label' => 'Ejemplo', 'lock_version' => 0, 'based_on' => null, 'published_at' => null],
                'nodes' => $nodes,
                'stats' => [
                    'positions' => count($nodes),
                    'occupied' => $people,
                    'vacancies' => count($nodes) - $people,
                    'interims' => 1,
                    'people' => $people,
                    'unassigned' => count($unassigned),
                    'errors' => 0,
                    'warnings' => 1,
                ],
                'issues' => [[
                    'level' => 'warning',
                    'code' => 'unassigned',
                    'message' => count($unassigned).' colaborador(es) activo(s) sin puesto.',
                    'key' => null,
                ]],
                'unassigned' => $unassigned,
            ],
            'readOnly' => false,
            'readOnlyReason' => null,
            'reviewNotes' => null,
            'hasBase' => false,
            'palette' => ['areas' => $areas, 'cargos' => $cargos],
            'cargoOptions' => array_map(fn (array $cargo): array => ['id' => $cargo['id'], 'name' => $cargo['name'], 'departamento_id' => $cargo['departamento_id']], $cargos),
            'deptOptions' => array_map(fn (array $area): array => ['id' => $area['id'], 'name' => $area['name']], $areas),
            'colors' => OrgStructureEditor::COLORS,
            'heartbeatMs' => 0,
            'publishAction' => null,
            'canExport' => false,
            'canManageViews' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function occupant(int $positionId, string $name, string $role): array
    {
        return [
            'assignment_id' => $positionId * 100,
            'id' => 8000 + $positionId,
            'name' => $name,
            'initials' => OrgStructureRrhh::initials($name),
            'avatar' => null,
            'role' => $role,
            'role_label' => $role === 'INTERINO' ? 'Interino' : 'Titular',
            'phone' => null,
            'email' => null,
            'since' => null,
            'active' => true,
            'dual' => false,
            'rrhh_cargo_id' => null,
            'rrhh_cargo' => null,
        ];
    }
}
