<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

use App\Models\ProjectManagement\Department;
use Illuminate\Support\Str;

final class ProjectManagementDepartmentTable
{
    public const DEFAULT_COLOR = '#475569';

    /**
     * @var list<string>
     */
    private const DEPARTMENT_COLORS = [
        '#475569',
        '#4f46e5',
        '#0891b2',
        '#0d9488',
        '#7c3aed',
        '#b45309',
    ];

    public static function resolveColor(Department $department): string
    {
        $index = abs(crc32((string) $department->id.(string) $department->name)) % count(self::DEPARTMENT_COLORS);

        return self::DEPARTMENT_COLORS[$index];
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
     * @return array{
     *     percent: int|null,
     *     label: string,
     *     tone: string,
     *     done: int,
     *     open: int,
     *     total: int
     * }
     */
    public static function workloadMeta(Department $department): array
    {
        $total = (int) ($department->executed_activities_count ?? 0);
        $done = (int) ($department->executed_activities_done_count ?? 0);
        $open = (int) ($department->executed_activities_open_count ?? max(0, $total - $done));

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
