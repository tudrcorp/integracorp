<?php

declare(strict_types=1);

namespace App\Support\OrgStructure;

use App\Enums\OrgAssignmentRole;
use App\Enums\OrgPositionKind;
use App\Models\OrgPosition;
use App\Models\OrgPositionAssignment;
use App\Models\OrgStructureVersion;
use App\Models\RrhhColaborador;
use Illuminate\Support\Facades\Cache;

/**
 * Arma el árbol de una versión tal como lo dibuja la pantalla y el PDF.
 *
 * Son pocas consultas por versión (puestos, ocupantes, sus fichas de RRHH, los
 * activos de RRHH y los catálogos de cargos y departamentos) sin importar el
 * tamaño de la empresa, y el resultado se cachea firmado con (lock_version, sello
 * de RRHH). Ver el organigrama vigente es casi siempre una lectura de caché.
 *
 * @phpstan-type Occupant array{assignment_id: int, id: int, name: string, initials: string, avatar: string|null, role: string, role_label: string, phone: string|null, email: string|null, since: string|null, active: bool, dual: bool, rrhh_cargo_id: int|null, rrhh_cargo: string|null}
 * @phpstan-type Node array{id: int, key: string, parent: int|null, title: string, kind: string, seats: int, color: string|null, sort: int, cargo_id: int|null, cargo: string|null, depto_id: int|null, depto: string|null, occupants: list<Occupant>, vacancies: int}
 */
final class OrgChartPayload
{
    /**
     * @return array{version: array<string, mixed>, nodes: list<Node>, stats: array<string, int>, issues: list<array{level: string, code: string, message: string, key: string|null}>, unassigned: list<array{id: int, name: string, initials: string, avatar: string|null, cargo_id: int|null, cargo: string|null, depto_id: int|null}>}
     */
    public static function build(OrgStructureVersion $version): array
    {
        $key = OrgStructureCache::payloadKey($version);
        $signature = OrgStructureCache::payloadSignature($version);
        $cached = Cache::get($key);

        if (is_array($cached) && ($cached['signature'] ?? null) === $signature && is_array($cached['payload'] ?? null)) {
            return $cached['payload'];
        }

        $payload = self::compute($version);
        Cache::put($key, ['signature' => $signature, 'payload' => $payload], OrgStructureCache::PAYLOAD_TTL_SECONDS);

        return $payload;
    }

    /**
     * @return array{version: array<string, mixed>, nodes: list<Node>, stats: array<string, int>, issues: list<array{level: string, code: string, message: string, key: string|null}>, unassigned: list<array{id: int, name: string, initials: string, avatar: string|null, cargo_id: int|null, cargo: string|null, depto_id: int|null}>}
     */
    public static function compute(OrgStructureVersion $version): array
    {
        $positions = OrgPosition::query()
            ->where('version_id', $version->id)
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $assignments = OrgPositionAssignment::query()
            ->active()
            ->where('version_id', $version->id)
            ->orderByDesc('role')
            ->orderBy('id')
            ->get();

        $colaboradores = RrhhColaborador::query()
            ->whereIn('id', $assignments->pluck('rrhh_colaborador_id')->unique()->values()->all())
            ->get(OrgStructureRrhh::PUBLIC_COLUMNS)
            ->keyBy('id');

        $cargos = OrgStructureRrhh::cargoNames();
        $departamentos = OrgStructureRrhh::departamentoNames();

        $holderPositionsByPerson = $assignments
            ->filter(fn (OrgPositionAssignment $assignment): bool => $assignment->role === OrgAssignmentRole::Holder)
            ->groupBy('rrhh_colaborador_id')
            ->map(fn ($group): int => $group->count());

        $assignmentsByPosition = $assignments->groupBy('position_id');
        $nodes = [];

        foreach ($positions as $position) {
            $occupants = [];

            foreach ($assignmentsByPosition->get($position->id, []) as $assignment) {
                /** @var RrhhColaborador|null $colaborador */
                $colaborador = $colaboradores->get($assignment->rrhh_colaborador_id);
                $name = $colaborador?->fullName !== null ? (string) $colaborador->fullName : 'Colaborador #'.$assignment->rrhh_colaborador_id;
                $rrhhCargoId = filled($colaborador?->cargo_id) ? (int) $colaborador->cargo_id : null;

                $occupants[] = [
                    'assignment_id' => (int) $assignment->id,
                    'id' => (int) $assignment->rrhh_colaborador_id,
                    'name' => $name,
                    'initials' => OrgStructureRrhh::initials($name),
                    'avatar' => OrgStructureRrhh::avatarUrl($colaborador?->avatar),
                    'role' => $assignment->role->value,
                    'role_label' => $assignment->role->label(),
                    'phone' => filled($colaborador?->telefonoCorporativo) ? (string) $colaborador->telefonoCorporativo : null,
                    'email' => filled($colaborador?->emailCorporativo) ? (string) $colaborador->emailCorporativo : null,
                    'since' => $assignment->starts_on?->format('d/m/Y'),
                    'active' => $colaborador !== null && OrgStructureRrhh::isActive($colaborador),
                    'dual' => ($holderPositionsByPerson->get($assignment->rrhh_colaborador_id, 0)) > 1,
                    'rrhh_cargo_id' => $rrhhCargoId,
                    'rrhh_cargo' => $rrhhCargoId !== null ? ($cargos[$rrhhCargoId] ?? null) : null,
                ];
            }

            $nodes[] = [
                'id' => (int) $position->id,
                'key' => (string) $position->position_key,
                'parent' => $position->parent_id !== null ? (int) $position->parent_id : null,
                'title' => (string) $position->title,
                'kind' => $position->kind instanceof OrgPositionKind ? $position->kind->value : (string) $position->kind,
                'seats' => (int) $position->seats,
                'color' => $position->color,
                'sort' => (int) $position->sort_order,
                'cargo_id' => $position->rrhh_cargo_id,
                'cargo' => $position->rrhh_cargo_id !== null ? ($cargos[$position->rrhh_cargo_id] ?? null) : null,
                'depto_id' => $position->rrhh_departamento_id,
                'depto' => $position->rrhh_departamento_id !== null ? ($departamentos[$position->rrhh_departamento_id] ?? null) : null,
                'occupants' => $occupants,
                'vacancies' => max(0, (int) $position->seats - count($occupants)),
            ];
        }

        $assignedIds = $assignments->pluck('rrhh_colaborador_id')->map(fn (mixed $id): int => (int) $id)->unique()->all();

        $unassigned = OrgStructureRrhh::whereActive(RrhhColaborador::query())
            ->whereNotIn('id', $assignedIds === [] ? [0] : $assignedIds)
            ->orderBy('fullName')
            ->get(['id', 'fullName', 'cargo_id', 'departmento_id', 'avatar'])
            ->map(fn (RrhhColaborador $colaborador): array => [
                'id' => (int) $colaborador->id,
                'name' => (string) $colaborador->fullName,
                'initials' => OrgStructureRrhh::initials((string) $colaborador->fullName),
                'avatar' => OrgStructureRrhh::avatarUrl($colaborador->avatar),
                'cargo_id' => filled($colaborador->cargo_id) ? (int) $colaborador->cargo_id : null,
                'cargo' => $cargos[(int) $colaborador->cargo_id] ?? null,
                'depto_id' => filled($colaborador->departmento_id) ? (int) $colaborador->departmento_id : null,
            ])
            ->values()
            ->all();

        $occupiedPositions = 0;
        $people = [];
        $interims = 0;
        $vacancies = 0;

        foreach ($nodes as $node) {
            $vacancies += $node['vacancies'];

            if ($node['occupants'] !== []) {
                $occupiedPositions++;
            }

            foreach ($node['occupants'] as $occupant) {
                $people[$occupant['id']] = true;

                if ($occupant['role'] === OrgAssignmentRole::Interim->value) {
                    $interims++;
                }
            }
        }

        $issues = OrgStructureValidator::issues($nodes, $unassigned);

        return [
            'version' => [
                'id' => (int) $version->id,
                'name' => (string) $version->name,
                'status' => $version->status->value,
                'status_label' => $version->status->label(),
                'lock_version' => (int) $version->lock_version,
                'based_on' => $version->based_on_version_id,
                'published_at' => $version->published_at?->format('d/m/Y'),
            ],
            'nodes' => $nodes,
            'stats' => [
                'positions' => count($nodes),
                'occupied' => $occupiedPositions,
                'vacancies' => $vacancies,
                'interims' => $interims,
                'people' => count($people),
                'unassigned' => count($unassigned),
                'errors' => count(array_filter($issues, fn (array $issue): bool => $issue['level'] === 'error')),
                'warnings' => count(array_filter($issues, fn (array $issue): bool => $issue['level'] === 'warning')),
            ],
            'issues' => $issues,
            'unassigned' => $unassigned,
        ];
    }

    /**
     * Versión liviana para quien solo consulta: sin la lista de colaboradores sin
     * puesto ni el detalle de avisos internos.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function forViewer(array $payload): array
    {
        unset($payload['unassigned'], $payload['issues']);

        return $payload;
    }
}
