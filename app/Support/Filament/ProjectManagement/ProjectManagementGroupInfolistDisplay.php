<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

use App\Models\ProjectManagement\Group;
use App\Models\RrhhColaborador;

final class ProjectManagementGroupInfolistDisplay
{
    /**
     * @return array{
     *     group_name: string,
     *     group_color: string,
     *     total: int,
     *     label: string,
     *     tone: string,
     *     has_members: bool,
     *     members: list<array{id: int, name: string, initials: string, avatar_url: string|null}>
     * }
     */
    public static function membersPayload(Group $record): array
    {
        $memberIds = ProjectManagementGroupMembers::memberIds($record);

        if ($memberIds === []) {
            return [
                'group_name' => (string) $record->name,
                'group_color' => ProjectManagementGroupTable::resolveColor($record),
                'total' => 0,
                'label' => 'Sin integrantes',
                'tone' => 'gray',
                'has_members' => false,
                'members' => [],
            ];
        }

        $members = RrhhColaborador::query()
            ->find($memberIds)
            ->filter(fn (RrhhColaborador $collaborator): bool => $collaborator->fullName !== ProjectManagementCollaboratorSelect::EXCLUDED_COLLABORATOR_NAME)
            ->sortBy(fn (RrhhColaborador $collaborator): string => (string) $collaborator->fullName)
            ->map(fn (RrhhColaborador $collaborator): array => ProjectManagementCollaboratorAvatar::profile($collaborator))
            ->values()
            ->all();

        $total = count($members);

        $label = match ($total) {
            1 => '1 integrante',
            default => "{$total} integrantes",
        };

        return [
            'group_name' => (string) $record->name,
            'group_color' => ProjectManagementGroupTable::resolveColor($record),
            'total' => $total,
            'label' => $label,
            'tone' => $total >= 3 ? 'success' : 'info',
            'has_members' => $total > 0,
            'members' => $members,
        ];
    }
}
