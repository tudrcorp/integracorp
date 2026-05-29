<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

use App\Models\ProjectManagement\Activity;
use App\Models\ProjectManagement\Group;
use App\Models\RrhhColaborador;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class ProjectManagementActivityAssignmentDisplay
{
    public const MAX_VISIBLE_AVATARS = 4;

    /**
     * @param  Collection<int, Activity>|EloquentCollection<int, Activity>  $activities
     */
    public static function preload(Collection|EloquentCollection $activities): void
    {
        if ($activities->isEmpty()) {
            return;
        }

        [$groupIds, $collaboratorIds] = self::collectReferenceIds($activities);

        /** @var Collection<int, Group> $groups */
        $groups = Group::query()
            ->whereIn('id', $groupIds)
            ->get(['id', 'name', 'collaborator_ids'])
            ->keyBy('id');

        foreach ($groups as $group) {
            $collaboratorIds = array_merge(
                $collaboratorIds,
                ProjectManagementGroupMembers::memberIds($group),
            );
        }

        /** @var Collection<int, RrhhColaborador> $collaborators */
        $collaborators = RrhhColaborador::query()
            ->whereIn('id', array_values(array_unique($collaboratorIds)))
            ->where('fullName', '!=', ProjectManagementCollaboratorSelect::EXCLUDED_COLLABORATOR_NAME)
            ->orderBy('fullName')
            ->get(['id', 'fullName', 'avatar'])
            ->keyBy('id');

        foreach ($activities as $activity) {
            $activity->setAttribute(
                'kanban_assignment',
                self::resolve($activity, $groups, $collaborators),
            );
        }
    }

    /**
     * @return array{
     *     mode: string,
     *     heading: string,
     *     title: string,
     *     visible_members: array<int, array{id: int, name: string, initials: string, avatar_url: string|null}>,
     *     all_members: array<int, array{id: int, name: string, initials: string, avatar_url: string|null}>,
     *     overflow_count: int,
     *     total_count: int,
     *     names_line: string
     * }
     */
    public static function for(Activity $activity): array
    {
        $cached = $activity->getAttribute('kanban_assignment');

        if (is_array($cached)) {
            return $cached;
        }

        [$groupIds, $collaboratorIds] = self::collectReferenceIds(collect([$activity]));

        $groups = Group::query()
            ->whereIn('id', $groupIds)
            ->get(['id', 'name', 'collaborator_ids'])
            ->keyBy('id');

        foreach ($groups as $group) {
            $collaboratorIds = array_merge(
                $collaboratorIds,
                ProjectManagementGroupMembers::memberIds($group),
            );
        }

        $collaborators = RrhhColaborador::query()
            ->whereIn('id', array_values(array_unique($collaboratorIds)))
            ->where('fullName', '!=', ProjectManagementCollaboratorSelect::EXCLUDED_COLLABORATOR_NAME)
            ->orderBy('fullName')
            ->get(['id', 'fullName', 'avatar'])
            ->keyBy('id');

        return self::resolve($activity, $groups, $collaborators);
    }

    /**
     * @param  Collection<int, Activity>|EloquentCollection<int, Activity>  $activities
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    private static function collectReferenceIds(Collection|EloquentCollection $activities): array
    {
        $groupIds = [];
        $collaboratorIds = [];

        foreach ($activities as $activity) {
            if (ProjectManagementGroupMembers::isTeamActivity($activity)) {
                $group = ProjectManagementGroupMembers::resolveGroupForActivity($activity);

                if ($group instanceof Group) {
                    $groupIds[] = (int) $group->id;
                } elseif ($activity->executor_type === Group::class && filled($activity->executor_id)) {
                    $groupIds[] = (int) $activity->executor_id;
                }

                $collaboratorIds = array_merge(
                    $collaboratorIds,
                    ProjectManagementGroupMembers::memberIdsForActivity($activity, $group),
                );

                continue;
            }

            $collaboratorIds = array_merge(
                $collaboratorIds,
                self::collaboratorIdsForActivity($activity),
            );
        }

        return [array_values(array_unique($groupIds)), array_values(array_unique($collaboratorIds))];
    }

    /**
     * @return array<int, int>
     */
    private static function collaboratorIdsForActivity(Activity $activity): array
    {
        $ids = collect($activity->assigned_collaborator_ids ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($ids === [] && $activity->executor_type === RrhhColaborador::class && filled($activity->executor_id)) {
            $ids = [(int) $activity->executor_id];
        }

        return $ids;
    }

    /**
     * @param  Collection<int, Group>  $groups
     * @param  Collection<int, RrhhColaborador>  $collaborators
     * @return array{
     *     mode: string,
     *     heading: string,
     *     title: string,
     *     visible_members: array<int, array{id: int, name: string, initials: string, avatar_url: string|null}>,
     *     all_members: array<int, array{id: int, name: string, initials: string, avatar_url: string|null}>,
     *     overflow_count: int,
     *     total_count: int,
     *     names_line: string
     * }
     */
    private static function resolve(Activity $activity, Collection $groups, Collection $collaborators): array
    {
        if (ProjectManagementGroupMembers::isTeamActivity($activity)) {
            $group = ProjectManagementGroupMembers::resolveGroupForActivity($activity, $groups);
            $members = ProjectManagementGroupMembers::profilesForActivity($activity, $collaborators, $group);

            return self::buildPayload(
                mode: 'team',
                heading: 'Equipo ejecutor',
                title: $group?->name ?? 'Equipo asignado',
                members: $members,
            );
        }

        $members = self::membersFromIds(self::collaboratorIdsForActivity($activity), $collaborators);

        if ($members === []) {
            return self::buildPayload(
                mode: 'unassigned',
                heading: 'Ejecutor',
                title: 'Sin asignar',
                members: [],
            );
        }

        if (count($members) === 1) {
            return self::buildPayload(
                mode: 'collaborator',
                heading: 'Ejecutor',
                title: $members[0]['name'],
                members: $members,
            );
        }

        return self::buildPayload(
            mode: 'collaborators',
            heading: 'Ejecutores',
            title: count($members).' colaboradores asignados',
            members: $members,
        );
    }

    /**
     * @param  array<int, int>  $memberIds
     * @param  Collection<int, RrhhColaborador>  $collaborators
     * @return array<int, array{id: int, name: string, initials: string, avatar_url: string|null}>
     */
    private static function membersFromIds(array $memberIds, Collection $collaborators): array
    {
        return collect($memberIds)
            ->map(fn (int $id): ?RrhhColaborador => $collaborators->get($id))
            ->filter(fn (?RrhhColaborador $collaborator): bool => $collaborator instanceof RrhhColaborador)
            ->sortBy(fn (RrhhColaborador $collaborator): string => (string) $collaborator->fullName)
            ->map(fn (RrhhColaborador $collaborator): array => ProjectManagementCollaboratorAvatar::profile($collaborator))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{id: int, name: string, initials: string, avatar_url: string|null}>  $members
     * @return array{
     *     mode: string,
     *     heading: string,
     *     title: string,
     *     visible_members: array<int, array{id: int, name: string, initials: string, avatar_url: string|null}>,
     *     all_members: array<int, array{id: int, name: string, initials: string, avatar_url: string|null}>,
     *     overflow_count: int,
     *     total_count: int,
     *     names_line: string
     * }
     */
    private static function buildPayload(string $mode, string $heading, string $title, array $members): array
    {
        $totalCount = count($members);
        $visibleMembers = array_slice($members, 0, self::MAX_VISIBLE_AVATARS);
        $overflowCount = max(0, $totalCount - count($visibleMembers));

        return [
            'mode' => $mode,
            'heading' => $heading,
            'title' => $title,
            'visible_members' => $visibleMembers,
            'all_members' => $members,
            'overflow_count' => $overflowCount,
            'total_count' => $totalCount,
            'names_line' => ProjectManagementCollaboratorAvatar::namesLine($members, $overflowCount),
        ];
    }
}
