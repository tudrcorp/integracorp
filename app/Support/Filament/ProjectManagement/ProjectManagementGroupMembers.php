<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

use App\Models\ProjectManagement\Activity;
use App\Models\ProjectManagement\Group;
use App\Models\RrhhColaborador;
use Illuminate\Support\Collection;

final class ProjectManagementGroupMembers
{
    /**
     * @return array<int, int>
     */
    public static function memberIds(?Group $group): array
    {
        if ($group === null) {
            return [];
        }

        return collect($group->collaborator_ids ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public static function isTeamActivity(Activity $activity): bool
    {
        if (($activity->assignment_type ?? 'collaborator') === 'team') {
            return true;
        }

        return $activity->executor_type === Group::class && filled($activity->executor_id);
    }

    public static function resolveGroupForActivity(Activity $activity, ?Collection $groups = null): ?Group
    {
        if ($activity->relationLoaded('executor') && $activity->executor instanceof Group) {
            return $activity->executor;
        }

        if ($activity->executor_type !== Group::class || ! filled($activity->executor_id)) {
            return null;
        }

        $groupId = (int) $activity->executor_id;

        if ($groups !== null) {
            $group = $groups->get($groupId);

            if ($group instanceof Group) {
                return $group;
            }
        }

        return Group::query()->find($groupId);
    }

    /**
     * @return array<int, int>
     */
    public static function memberIdsForActivity(Activity $activity, ?Group $group = null): array
    {
        $group ??= self::resolveGroupForActivity($activity);

        $memberIds = self::memberIds($group);

        if ($memberIds !== []) {
            return $memberIds;
        }

        return collect($activity->assigned_collaborator_ids ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, RrhhColaborador>  $collaborators
     * @return array<int, array{id: int, name: string, initials: string, avatar_url: string|null}>
     */
    public static function profilesForActivity(Activity $activity, Collection $collaborators, ?Group $group = null): array
    {
        $memberIds = self::memberIdsForActivity($activity, $group);

        return collect($memberIds)
            ->map(fn (int $id): ?RrhhColaborador => $collaborators->get($id))
            ->filter(fn (?RrhhColaborador $collaborator): bool => $collaborator instanceof RrhhColaborador)
            ->sortBy(fn (RrhhColaborador $collaborator): string => (string) $collaborator->fullName)
            ->map(fn (RrhhColaborador $collaborator): array => ProjectManagementCollaboratorAvatar::profile($collaborator))
            ->values()
            ->all();
    }
}
