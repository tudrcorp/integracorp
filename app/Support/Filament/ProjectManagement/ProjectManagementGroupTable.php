<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

use App\Models\ProjectManagement\Group;
use App\Models\RrhhColaborador;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ProjectManagementGroupTable
{
    public const DEFAULT_COLOR = '#6366f1';

    /**
     * @var list<string>
     */
    private const TEAM_COLORS = [
        '#6366f1',
        '#0ea5e9',
        '#14b8a6',
        '#8b5cf6',
        '#ec4899',
        '#f59e0b',
    ];

    public static function resolveColor(Group $group): string
    {
        $index = abs(crc32((string) $group->id.(string) $group->name)) % count(self::TEAM_COLORS);

        return self::TEAM_COLORS[$index];
    }

    public static function normalizeDescriptionText(string $description): string
    {
        $description = trim($description);

        if ($description === '') {
            return '';
        }

        $lines = preg_split("/\r\n|\r|\n/", $description) ?: [];

        return collect($lines)
            ->map(fn (string $line): string => ltrim($line))
            ->implode("\n");
    }

    /**
     * @return Collection<int, string>
     */
    public static function resolveMemberNames(Group $group): Collection
    {
        $memberIds = ProjectManagementGroupMembers::memberIds($group);

        if ($memberIds === []) {
            return collect();
        }

        return RrhhColaborador::query()
            ->find($memberIds)
            ->filter(fn (RrhhColaborador $collaborator): bool => $collaborator->fullName !== ProjectManagementCollaboratorSelect::EXCLUDED_COLLABORATOR_NAME)
            ->sortBy(fn (RrhhColaborador $collaborator): string => (string) $collaborator->fullName)
            ->pluck('fullName')
            ->values();
    }

    /**
     * @return array{
     *     total: int,
     *     label: string,
     *     subtitle: string,
     *     tone: string,
     *     visible_members: list<array{id: int, name: string, initials: string, avatar_url: string|null}>,
     *     overflow_count: int,
     *     tooltip_items: list<array{name: string}>,
     *     names_preview: string
     * }
     */
    public static function membersMeta(Group $group, int $visibleLimit = 4): array
    {
        $memberIds = ProjectManagementGroupMembers::memberIds($group);

        if ($memberIds === []) {
            return [
                'total' => 0,
                'label' => 'Sin integrantes',
                'subtitle' => 'Asigna colaboradores al equipo',
                'tone' => 'gray',
                'visible_members' => [],
                'overflow_count' => 0,
                'tooltip_items' => [],
                'names_preview' => 'Sin integrantes asignados',
            ];
        }

        $profiles = RrhhColaborador::query()
            ->find($memberIds)
            ->filter(fn (RrhhColaborador $collaborator): bool => $collaborator->fullName !== ProjectManagementCollaboratorSelect::EXCLUDED_COLLABORATOR_NAME)
            ->sortBy(fn (RrhhColaborador $collaborator): string => (string) $collaborator->fullName)
            ->map(fn (RrhhColaborador $collaborator): array => ProjectManagementCollaboratorAvatar::profile($collaborator))
            ->values();

        $total = $profiles->count();
        $visible = $profiles->take($visibleLimit)->all();
        $overflow = max(0, $total - count($visible));

        $label = match ($total) {
            1 => '1 integrante',
            default => "{$total} integrantes",
        };

        return [
            'total' => $total,
            'label' => $label,
            'subtitle' => ProjectManagementCollaboratorAvatar::namesLine($visible, $overflow),
            'tone' => $total >= 3 ? 'success' : 'info',
            'visible_members' => $visible,
            'overflow_count' => $overflow,
            'tooltip_items' => $profiles->map(fn (array $profile): array => ['name' => $profile['name']])->all(),
            'names_preview' => $profiles->pluck('name')->implode(', '),
        ];
    }

    /**
     * @return array{
     *     percent: int|null,
     *     label: string,
     *     tone: string,
     *     done: int,
     *     open: int,
     *     total: int
     * }
     */
    public static function workloadMeta(Group $group): array
    {
        $total = (int) ($group->executed_activities_count ?? 0);
        $done = (int) ($group->executed_activities_done_count ?? 0);
        $open = (int) ($group->executed_activities_open_count ?? max(0, $total - $done));

        if ($total === 0) {
            return [
                'percent' => null,
                'label' => 'Sin actividades asignadas',
                'tone' => 'muted',
                'done' => 0,
                'open' => 0,
                'total' => 0,
            ];
        }

        $percent = (int) round(($done / $total) * 100);
        $percent = max(0, min(100, $percent));

        $tone = match (true) {
            $percent >= 100 => 'success',
            $done === 0 => 'warning',
            default => 'primary',
        };

        $label = match (true) {
            $percent >= 100 => 'Todas las actividades cerradas',
            $done === 0 => 'Sin actividades cerradas',
            default => "{$done} de {$total} actividades cerradas",
        };

        return [
            'percent' => $percent,
            'label' => $label,
            'tone' => $tone,
            'done' => $done,
            'open' => $open,
            'total' => $total,
        ];
    }

    public static function excerptDescription(?string $description, int $limit = 120): string
    {
        $normalized = self::normalizeDescriptionText((string) $description);

        if ($normalized === '') {
            return '';
        }

        return Str::limit($normalized, $limit);
    }
}
