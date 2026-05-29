<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Activities\Concerns;

use App\Models\ProjectManagement\Group;
use App\Models\RrhhColaborador;
use App\Support\Filament\ProjectManagement\ProjectManagementGroupMembers;

trait InteractsWithActivityAssignmentForm
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeActivityAssignmentFormData(array $data): array
    {
        $assignmentType = (string) ($data['assignment_type'] ?? 'collaborator');

        if ($assignmentType === 'team') {
            $group = Group::query()->find((int) ($data['executor_group_id'] ?? 0));

            $data['executor_type'] = Group::class;
            $data['executor_id'] = (int) ($group?->id ?? 0);
            $data['assigned_collaborator_ids'] = ProjectManagementGroupMembers::memberIds($group);
        } else {
            $collaboratorIds = collect($data['assigned_collaborator_ids'] ?? [])
                ->map(fn (mixed $id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();

            $data['assigned_collaborator_ids'] = $collaboratorIds;
            $data['executor_type'] = $collaboratorIds !== []
                ? RrhhColaborador::class
                : null;
            $data['executor_id'] = $collaboratorIds[0] ?? null;
        }

        unset($data['executor_group_id']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function hydrateActivityAssignmentFormData(array $data): array
    {
        if (($data['executor_type'] ?? null) === Group::class && filled($data['executor_id'] ?? null)) {
            $group = Group::query()->find((int) $data['executor_id']);

            $data['assignment_type'] = 'team';
            $data['executor_group_id'] = (int) $data['executor_id'];
            $data['assigned_collaborator_ids'] = ProjectManagementGroupMembers::memberIds($group);
        } else {
            $data['assignment_type'] = 'collaborator';
            $data['assigned_collaborator_ids'] = is_array($data['assigned_collaborator_ids'] ?? null)
                ? array_values(array_map('intval', $data['assigned_collaborator_ids']))
                : [];

            if (($data['executor_type'] ?? null) === RrhhColaborador::class
                && filled($data['executor_id'] ?? null)
                && $data['assigned_collaborator_ids'] === []) {
                $data['assigned_collaborator_ids'] = [(int) $data['executor_id']];
            }
        }

        return $data;
    }
}
