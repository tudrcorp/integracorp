<?php

declare(strict_types=1);

namespace App\Support\CommercialStructure;

use App\Filament\Shared\CommercialStructure\CommercialHierarchyFlowchart;
use App\Models\Agency;

/**
 * Aplana el árbol del diagrama de jerarquía comercial en filas ordenadas por recorrido en
 * profundidad, que es como se lee una jerarquía: cada nodo aparece debajo de su superior y
 * antes de los nodos de la siguiente rama.
 *
 * Cada fila lleva su nivel, de quién depende y la ruta completa, para que un PDF o un Excel
 * —donde no hay líneas de conexión que seguir— sigan mostrando la estructura sin ambigüedad.
 */
final class CommercialHierarchyExportRows
{
    public const SEPARATOR = ' › ';

    /**
     * @return list<array{level: int, type: string, code: string, name: string, status: string, structure: string, parent_code: string, path: string, entity_type: string, entity_id: int|null}>
     */
    public static function forAgency(Agency $agency): array
    {
        return self::fromTree(CommercialHierarchyFlowchart::hierarchyTreeForAgency($agency));
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return list<array{level: int, type: string, code: string, name: string, status: string, structure: string, parent_code: string, path: string, entity_type: string, entity_id: int|null}>
     */
    public static function fromTree(array $tree): array
    {
        $rows = [];

        $headquarters = $tree['headquarters'] ?? null;
        $master = $tree['master'] ?? null;

        $headquartersRow = null;

        if (is_array($headquarters)) {
            $headquartersRow = self::row($headquarters, 'Casa matriz', 1, '', []);
            $rows[] = $headquartersRow;
        }

        $masterLevel = $headquartersRow !== null ? 2 : 1;
        $masterAncestors = $headquartersRow !== null ? [$headquartersRow['code']] : [];
        $masterRow = null;

        if (is_array($master)) {
            $masterRow = self::row($master, 'Agencia master', $masterLevel, (string) ($headquartersRow['code'] ?? ''), $masterAncestors);
            $rows[] = $masterRow;

            foreach ($tree['master_direct_agents'] ?? [] as $branch) {
                foreach (self::agentBranchRows($branch, $masterLevel + 1, $masterRow) as $agentRow) {
                    $rows[] = $agentRow;
                }
            }
        }

        $generalLevel = $masterRow !== null ? $masterLevel + 1 : $masterLevel;
        $generalParentCode = (string) ($masterRow['code'] ?? $headquartersRow['code'] ?? '');
        $generalAncestors = $masterRow !== null
            ? [...$masterAncestors, $masterRow['code']]
            : $masterAncestors;

        foreach ($tree['generals'] ?? [] as $generalBranch) {
            $agencyNode = $generalBranch['agency'] ?? null;

            if (! is_array($agencyNode)) {
                continue;
            }

            $generalRow = self::row($agencyNode, 'Agencia general', $generalLevel, $generalParentCode, $generalAncestors);
            $rows[] = $generalRow;

            foreach ($generalBranch['agents'] ?? [] as $branch) {
                foreach (self::agentBranchRows($branch, $generalLevel + 1, $generalRow) as $agentRow) {
                    $rows[] = $agentRow;
                }
            }
        }

        return $rows;
    }

    /**
     * Totales por tipo calculados sobre las filas exportadas, no sobre la base: así el
     * resumen del reporte siempre cuadra con lo que el propio documento lista.
     *
     * @param  list<array{type: string}>  $rows
     * @return array{generals: int, agents: int, subagents: int, total: int}
     */
    public static function totals(array $rows): array
    {
        $count = static fn (string $type): int => count(array_filter(
            $rows,
            static fn (array $row): bool => $row['type'] === $type,
        ));

        return [
            'generals' => $count('Agencia general'),
            'agents' => $count('Agente'),
            'subagents' => $count('Subagente'),
            'total' => count($rows),
        ];
    }

    /**
     * @param  array{agent?: array<string, mixed>, subagents?: list<array<string, mixed>>}  $branch
     * @param  array{code: string, path: string}  $parentRow
     * @return list<array{level: int, type: string, code: string, name: string, status: string, structure: string, parent_code: string, path: string, entity_type: string, entity_id: int|null}>
     */
    private static function agentBranchRows(array $branch, int $level, array $parentRow): array
    {
        $agentNode = $branch['agent'] ?? null;

        if (! is_array($agentNode)) {
            return [];
        }

        $ancestors = self::pathSegments($parentRow['path']);
        $agentRow = self::row($agentNode, 'Agente', $level, $parentRow['code'], $ancestors);
        $rows = [$agentRow];

        foreach ($branch['subagents'] ?? [] as $subagentNode) {
            if (! is_array($subagentNode)) {
                continue;
            }

            $rows[] = self::row(
                $subagentNode,
                'Subagente',
                $level + 1,
                $agentRow['code'],
                self::pathSegments($agentRow['path']),
            );
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $ancestorCodes
     * @return array{level: int, type: string, code: string, name: string, status: string, structure: string, parent_code: string, path: string, entity_type: string, entity_id: int|null}
     */
    private static function row(array $node, string $type, int $level, string $parentCode, array $ancestorCodes): array
    {
        $code = trim((string) ($node['subtitle'] ?? ''));
        $name = trim((string) ($node['name'] ?? ''));

        /** El nodo del master llega con `structure` vacía a propósito: el diagrama la pinta aparte. */
        $structure = trim((string) ($node['structure'] ?? ''));

        return [
            'level' => $level,
            'type' => $node['title'] === 'Subagente' ? 'Subagente' : $type,
            'code' => $code !== '' ? $code : 'Sin código',
            'name' => $name !== '' ? $name : 'Sin nombre',
            'status' => trim((string) ($node['status'] ?? '')) ?: 'Sin estado',
            'structure' => $structure,
            'parent_code' => $parentCode,
            'path' => implode(self::SEPARATOR, [...$ancestorCodes, $code !== '' ? $code : 'Sin código']),
            'entity_type' => (string) ($node['entity_type'] ?? 'agency'),
            'entity_id' => isset($node['entity_id']) ? (int) $node['entity_id'] : null,
        ];
    }

    /**
     * @return list<string>
     */
    private static function pathSegments(string $path): array
    {
        return $path === '' ? [] : explode(self::SEPARATOR, $path);
    }
}
