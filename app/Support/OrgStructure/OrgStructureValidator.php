<?php

declare(strict_types=1);

namespace App\Support\OrgStructure;

use App\Enums\OrgAssignmentRole;
use App\Enums\OrgPositionKind;

/**
 * Revisa que un árbol de puestos sea publicable.
 *
 * - Errores (bloquean publicar): sin puestos, más de un puesto principal, puestos
 *   huérfanos, ciclos, staff con subordinados, más ocupantes que plazas y
 *   ocupantes que ya no están activos en RRHH.
 * - Avisos (no bloquean): una persona titular en dos puestos, interino con titular
 *   presente, cargo distinto al de RRHH y colaboradores activos sin puesto.
 *
 * Trabaja sobre los nodos ya armados por OrgChartPayload: no consulta la base.
 */
final class OrgStructureValidator
{
    private const MAX_LISTED_UNASSIGNED = 8;

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<array{id: int, name: string, cargo: string|null}>  $unassigned
     * @return list<array{level: string, code: string, message: string, key: string|null}>
     */
    public static function issues(array $nodes, array $unassigned = []): array
    {
        $issues = [];

        if ($nodes === []) {
            return [self::error('empty', 'El organigrama no tiene puestos. Agrega el puesto principal.')];
        }

        $byId = [];

        foreach ($nodes as $node) {
            $byId[$node['id']] = $node;
        }

        $roots = array_values(array_filter($nodes, fn (array $node): bool => $node['parent'] === null));

        if (count($roots) > 1) {
            $issues[] = self::error('multiple_roots', 'Hay '.count($roots).' puestos sin jefe ('.implode(', ', array_map(fn (array $node): string => '«'.$node['title'].'»', $roots)).'). Debe haber un solo puesto principal.');
        }

        $childCount = [];

        foreach ($nodes as $node) {
            if ($node['parent'] === null) {
                continue;
            }

            if (! isset($byId[$node['parent']])) {
                $issues[] = self::error('orphan', '«'.$node['title'].'» depende de un puesto que ya no existe.', $node['key']);

                continue;
            }

            $childCount[$node['parent']] = ($childCount[$node['parent']] ?? 0) + 1;
        }

        foreach ($nodes as $node) {
            if (self::isInCycle($node['id'], $byId)) {
                $issues[] = self::error('cycle', '«'.$node['title'].'» quedó dependiendo de sí mismo a través de sus subordinados.', $node['key']);
            }

            if ($node['kind'] === OrgPositionKind::Staff->value && ($childCount[$node['id']] ?? 0) > 0) {
                $issues[] = self::error('staff_children', '«'.$node['title'].'» es staff y tiene subordinados.', $node['key']);
            }

            if (count($node['occupants']) > $node['seats']) {
                $issues[] = self::error('over_seats', '«'.$node['title'].'» tiene '.count($node['occupants']).' ocupantes y '.$node['seats'].' plaza(s).', $node['key']);
            }

            $hasHolder = false;

            foreach ($node['occupants'] as $occupant) {
                if (! $occupant['active']) {
                    $issues[] = self::error('inactive_occupant', $occupant['name'].' ocupa «'.$node['title'].'» pero ya no está activo en RRHH.', $node['key']);
                }

                if ($occupant['role'] === OrgAssignmentRole::Holder->value) {
                    $hasHolder = true;
                }
            }

            foreach ($node['occupants'] as $occupant) {
                if ($occupant['role'] === OrgAssignmentRole::Interim->value && $hasHolder && $node['seats'] <= 1) {
                    $issues[] = self::warning('interim_with_holder', $occupant['name'].' está como interino en «'.$node['title'].'», que ya tiene titular.', $node['key']);
                }

                if ($occupant['role'] === OrgAssignmentRole::Holder->value
                    && $node['cargo_id'] !== null
                    && $occupant['rrhh_cargo_id'] !== null
                    && (int) $occupant['rrhh_cargo_id'] !== (int) $node['cargo_id']) {
                    $issues[] = self::warning(
                        'cargo_mismatch',
                        $occupant['name'].' aparece en RRHH como «'.($occupant['rrhh_cargo'] ?? 'otro cargo').'» y aquí ocupa «'.($node['cargo'] ?? $node['title']).'».',
                        $node['key'],
                    );
                }
            }
        }

        $duals = [];

        foreach ($nodes as $node) {
            foreach ($node['occupants'] as $occupant) {
                if ($occupant['dual'] && $occupant['role'] === OrgAssignmentRole::Holder->value) {
                    $duals[$occupant['id']]['name'] = $occupant['name'];
                    $duals[$occupant['id']]['titles'][] = '«'.$node['title'].'»';
                }
            }
        }

        foreach ($duals as $dual) {
            $issues[] = self::warning('dual_holder', $dual['name'].' es titular de '.count($dual['titles']).' puestos: '.implode(', ', $dual['titles']).'.');
        }

        if ($unassigned !== []) {
            $names = array_map(fn (array $person): string => $person['name'], array_slice($unassigned, 0, self::MAX_LISTED_UNASSIGNED));
            $more = count($unassigned) > self::MAX_LISTED_UNASSIGNED ? ' y '.(count($unassigned) - self::MAX_LISTED_UNASSIGNED).' más' : '';

            $issues[] = self::warning('unassigned', count($unassigned).' colaborador(es) activo(s) sin puesto: '.implode(', ', $names).$more.'.');
        }

        return $issues;
    }

    /**
     * @param  list<array{level: string, code: string, message: string, key: string|null}>  $issues
     * @return list<string>
     */
    public static function errorMessages(array $issues): array
    {
        return array_values(array_map(
            fn (array $issue): string => $issue['message'],
            array_filter($issues, fn (array $issue): bool => $issue['level'] === 'error'),
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $byId
     */
    private static function isInCycle(int $startId, array $byId): bool
    {
        $seen = [];
        $current = $byId[$startId]['parent'] ?? null;

        while ($current !== null) {
            if ($current === $startId) {
                return true;
            }

            if (isset($seen[$current]) || ! isset($byId[$current])) {
                return false;
            }

            $seen[$current] = true;
            $current = $byId[$current]['parent'];
        }

        return false;
    }

    /**
     * @return array{level: string, code: string, message: string, key: string|null}
     */
    private static function error(string $code, string $message, ?string $key = null): array
    {
        return ['level' => 'error', 'code' => $code, 'message' => $message, 'key' => $key];
    }

    /**
     * @return array{level: string, code: string, message: string, key: string|null}
     */
    private static function warning(string $code, string $message, ?string $key = null): array
    {
        return ['level' => 'warning', 'code' => $code, 'message' => $message, 'key' => $key];
    }
}
