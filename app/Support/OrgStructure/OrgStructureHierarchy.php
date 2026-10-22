<?php

declare(strict_types=1);

namespace App\Support\OrgStructure;

/**
 * Quién es el jefe de quién según el organigrama vigente. Es el dato que pueden
 * usar otros módulos (vacaciones, helpdesk, reportes por equipo).
 *
 * El jefe no se guarda en RRHH: es quien ocupa como titular el puesto superior.
 * Si ese puesto está vacante se sigue subiendo hasta encontrar a alguien.
 */
final class OrgStructureHierarchy
{
    /**
     * @return array{id: int, name: string, position: string}|null
     */
    public static function managerOf(int $colaboradorId): ?array
    {
        $nodes = self::currentNodes();

        if ($nodes === []) {
            return null;
        }

        $byId = array_column($nodes, null, 'id');

        foreach ($nodes as $node) {
            if (! self::holds($node, $colaboradorId)) {
                continue;
            }

            $parentId = $node['parent'];
            $guard = 0;

            while ($parentId !== null && isset($byId[$parentId]) && $guard++ < 100) {
                $parent = $byId[$parentId];

                foreach ($parent['occupants'] as $occupant) {
                    if ($occupant['active'] && $occupant['id'] !== $colaboradorId) {
                        return ['id' => $occupant['id'], 'name' => $occupant['name'], 'position' => $parent['title']];
                    }
                }

                $parentId = $parent['parent'];
            }

            return null;
        }

        return null;
    }

    /**
     * Colaboradores que ocupan los puestos que dependen directo de los puestos de esta persona.
     *
     * @return list<array{id: int, name: string, position: string}>
     */
    public static function directReportsOf(int $colaboradorId): array
    {
        $nodes = self::currentNodes();
        $ownIds = [];

        foreach ($nodes as $node) {
            if (self::holds($node, $colaboradorId)) {
                $ownIds[$node['id']] = true;
            }
        }

        $reports = [];

        foreach ($nodes as $node) {
            if ($node['parent'] === null || ! isset($ownIds[$node['parent']])) {
                continue;
            }

            foreach ($node['occupants'] as $occupant) {
                if ($occupant['active'] && $occupant['id'] !== $colaboradorId) {
                    $reports[$occupant['id']] = ['id' => $occupant['id'], 'name' => $occupant['name'], 'position' => $node['title']];
                }
            }
        }

        return array_values($reports);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function holds(array $node, int $colaboradorId): bool
    {
        foreach ($node['occupants'] as $occupant) {
            if ($occupant['id'] === $colaboradorId && $occupant['active']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function currentNodes(): array
    {
        $current = OrgStructureCache::currentVersion();

        return $current === null ? [] : OrgChartPayload::build($current)['nodes'];
    }
}
