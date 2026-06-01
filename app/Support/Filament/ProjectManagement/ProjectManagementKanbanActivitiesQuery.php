<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

use App\Models\ProjectManagement\Activity;
use Illuminate\Database\Eloquent\Builder;

final class ProjectManagementKanbanActivitiesQuery
{
    /**
     * @var array<string, int>
     */
    private const PRIORITY_ORDER = [
        'high' => 3,
        'medium' => 2,
        'low' => 1,
    ];

    public static function base(): Builder
    {
        return Activity::query()
            ->with(['project:id,name,icon,color', 'subproject:id,name', 'executor']);
    }

    public static function applyFilters(
        Builder $query,
        string $search,
        string $archivedFilter,
        string $projectFilter,
        string $statusFilter,
    ): Builder {
        return $query
            ->when(
                $archivedFilter === 'archived',
                fn (Builder $builder): Builder => $builder->whereNotNull('kanban_archived_at'),
            )
            ->when(
                $archivedFilter === 'active',
                fn (Builder $builder): Builder => $builder->whereNull('kanban_archived_at'),
            )
            ->when(
                trim($search) !== '',
                function (Builder $builder) use ($search): Builder {
                    $searchTerm = '%'.trim($search).'%';

                    return $builder->where(function (Builder $nestedQuery) use ($searchTerm): void {
                        $nestedQuery
                            ->where('title', 'like', $searchTerm)
                            ->orWhere('description', 'like', $searchTerm)
                            ->orWhereHas('project', fn (Builder $projectQuery): Builder => $projectQuery->where('name', 'like', $searchTerm))
                            ->orWhereHas('subproject', fn (Builder $subprojectQuery): Builder => $subprojectQuery->where('name', 'like', $searchTerm));
                    });
                },
            )
            ->when(
                $projectFilter !== 'all',
                fn (Builder $builder): Builder => $builder->where('project_id', (int) $projectFilter),
            )
            ->when(
                $statusFilter !== 'all',
                fn (Builder $builder): Builder => $builder->where('status', $statusFilter),
            );
    }

    public static function applySort(Builder $query, string $sortBy): Builder
    {
        if ($sortBy === 'due_asc') {
            return $query
                ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_date');
        }

        if ($sortBy === 'due_desc') {
            return $query
                ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('due_date');
        }

        if ($sortBy === 'priority_asc') {
            return $query->orderByRaw(
                'CASE priority WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 0 END',
                ['low', 'medium', 'high'],
            );
        }

        return $query->orderByRaw(
            'CASE priority WHEN ? THEN 3 WHEN ? THEN 2 WHEN ? THEN 1 ELSE 0 END DESC',
            ['high', 'medium', 'low'],
        );
    }

    /**
     * @return array<string, int>
     */
    public static function priorityOrder(): array
    {
        return self::PRIORITY_ORDER;
    }
}
