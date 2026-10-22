<?php

declare(strict_types=1);

namespace App\Support\OrgStructure;

/**
 * Compara dos versiones del organigrama puesto por puesto usando `position_key`,
 * que se conserva al copiar la vigente a un borrador.
 */
final class OrgStructureComparator
{
    private const MAX_CHANGES_LISTED = 300;

    /**
     * @param  list<array<string, mixed>>  $baseNodes
     * @param  list<array<string, mixed>>  $draftNodes
     * @return array{summary: array{added: int, removed: int, moved: int, renamed: int, occupants: int}, status_by_key: array<string, string>, removed: list<array{key: string, title: string, parent_title: string|null}>, changes: list<string>}
     */
    public static function compare(array $baseNodes, array $draftNodes): array
    {
        $base = self::indexByKey($baseNodes);
        $draft = self::indexByKey($draftNodes);

        $summary = ['added' => 0, 'removed' => 0, 'moved' => 0, 'renamed' => 0, 'occupants' => 0];
        $statusByKey = [];
        $removed = [];
        $changes = [];

        foreach ($draft as $key => $node) {
            if (! isset($base[$key])) {
                $summary['added']++;
                $statusByKey[$key] = 'added';
                $changes[] = 'Nuevo puesto «'.$node['title'].'»'.($node['parent_title'] !== null ? ' bajo «'.$node['parent_title'].'»' : '').'.';

                continue;
            }

            $before = $base[$key];

            if ($before['parent_key'] !== $node['parent_key']) {
                $summary['moved']++;
                $statusByKey[$key] = 'moved';
                $changes[] = '«'.$node['title'].'» pasa de «'.($before['parent_title'] ?? 'sin jefe').'» a «'.($node['parent_title'] ?? 'sin jefe').'».';
            }

            if ($before['title'] !== $node['title']) {
                $summary['renamed']++;
                $statusByKey[$key] ??= 'changed';
                $changes[] = '«'.$before['title'].'» ahora se llama «'.$node['title'].'».';
            }

            if ($before['occupants'] !== $node['occupants']) {
                $summary['occupants']++;
                $statusByKey[$key] ??= 'changed';
                $changes[] = self::describeOccupantChange($node['title'], $before['occupant_names'], $node['occupant_names']);
            }
        }

        foreach ($base as $key => $node) {
            if (isset($draft[$key])) {
                continue;
            }

            $summary['removed']++;
            $removed[] = ['key' => $key, 'title' => $node['title'], 'parent_title' => $node['parent_title']];
            $changes[] = 'Sale el puesto «'.$node['title'].'».';
        }

        return [
            'summary' => $summary,
            'status_by_key' => $statusByKey,
            'removed' => $removed,
            'changes' => array_slice($changes, 0, self::MAX_CHANGES_LISTED),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, array{title: string, parent_key: string|null, parent_title: string|null, occupants: list<string>, occupant_names: list<string>}>
     */
    private static function indexByKey(array $nodes): array
    {
        $byId = [];

        foreach ($nodes as $node) {
            $byId[$node['id']] = $node;
        }

        $indexed = [];

        foreach ($nodes as $node) {
            $parent = $node['parent'] !== null ? ($byId[$node['parent']] ?? null) : null;
            $occupants = array_map(fn (array $occupant): string => $occupant['id'].':'.$occupant['role'], $node['occupants']);
            sort($occupants);

            $indexed[$node['key']] = [
                'title' => $node['title'],
                'parent_key' => $parent['key'] ?? null,
                'parent_title' => $parent['title'] ?? null,
                'occupants' => $occupants,
                'occupant_names' => array_map(
                    fn (array $occupant): string => $occupant['name'].($occupant['role'] === 'INTERINO' ? ' (interino)' : ''),
                    $node['occupants'],
                ),
            ];
        }

        return $indexed;
    }

    /**
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    private static function describeOccupantChange(string $title, array $before, array $after): string
    {
        $left = array_values(array_diff($before, $after));
        $entered = array_values(array_diff($after, $before));
        $parts = [];

        if ($entered !== []) {
            $parts[] = 'entra '.implode(', ', $entered);
        }

        if ($left !== []) {
            $parts[] = 'sale '.implode(', ', $left);
        }

        return '«'.$title.'»: '.($parts === [] ? 'cambia el tipo de ocupación' : implode('; ', $parts)).'.';
    }
}
