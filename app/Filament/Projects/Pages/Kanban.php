<?php

declare(strict_types=1);

namespace App\Filament\Projects\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Activities\ActivityResource;
use App\Models\ProjectManagement\Activity;
use App\Models\ProjectManagement\Project;
use App\Support\Filament\ProjectManagement\ProjectManagementActivityAssignmentDisplay;
use App\Support\Filament\ProjectManagement\ProjectManagementKanbanActivityModalActions;
use App\Support\Filament\ProjectManagement\ProjectManagementKanbanFiles;
use App\Support\Filament\ProjectManagement\ProjectManagementKanbanTimeline;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class Kanban extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-view-columns';

    protected static string|UnitEnum|null $navigationGroup = 'GESTION DE PROYECTOS';

    protected static ?string $navigationLabel = 'Kanban';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Kanban de actividades';

    protected string $view = 'filament.projects.pages.kanban';

    public string $search = '';

    public string $projectFilter = 'all';

    public string $statusFilter = 'all';

    public string $sortBy = 'priority_desc';

    public string $viewMode = 'board';

    public string $filesCategory = 'all';

    public string $filesSort = 'newest';

    public string $filesLayout = 'grid';

    /**
     * @var array<int, int>
     */
    public array $pinnedFileIds = [];

    /**
     * @var array<string, string>
     */
    private const STATUSES = [
        'todo' => 'Por hacer',
        'in_progress' => 'En progreso',
        'review' => 'En revisión',
        'done' => 'Finalizada',
    ];

    /**
     * @var array<string, int>
     */
    private const PRIORITY_ORDER = [
        'high' => 3,
        'medium' => 2,
        'low' => 1,
    ];

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function getProjectOptionsProperty(): array
    {
        return Project::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, int $id): array => [(string) $id => $name])
            ->all();
    }

    public function getHasActiveFiltersProperty(): bool
    {
        return trim($this->search) !== ''
            || $this->projectFilter !== 'all'
            || $this->statusFilter !== 'all'
            || $this->sortBy !== 'priority_desc';
    }

    /**
     * @return array<string, string>
     */
    public function getStatusOptionsProperty(): array
    {
        return self::STATUSES;
    }

    /**
     * @return Collection<int, Activity>
     */
    public function getFilteredActivitiesProperty(): Collection
    {
        $activities = Activity::query()
            ->with(['project:id,name,icon,color', 'subproject:id,name', 'executor'])
            ->when(
                trim($this->search) !== '',
                fn (Builder $query): Builder => $query->where(function (Builder $nestedQuery): void {
                    $searchTerm = '%'.trim($this->search).'%';
                    $nestedQuery
                        ->where('title', 'like', $searchTerm)
                        ->orWhere('description', 'like', $searchTerm)
                        ->orWhereHas('project', fn (Builder $projectQuery): Builder => $projectQuery->where('name', 'like', $searchTerm))
                        ->orWhereHas('subproject', fn (Builder $subprojectQuery): Builder => $subprojectQuery->where('name', 'like', $searchTerm));
                }),
            )
            ->when(
                $this->projectFilter !== 'all',
                fn (Builder $query): Builder => $query->where('project_id', (int) $this->projectFilter),
            )
            ->when(
                $this->statusFilter !== 'all',
                fn (Builder $query): Builder => $query->where('status', $this->statusFilter),
            )
            ->get();

        ProjectManagementActivityAssignmentDisplay::preload($activities);

        if ($this->sortBy === 'due_asc') {
            return $activities->sortBy(fn (Activity $activity): string => (string) ($activity->due_date?->format('Y-m-d') ?? '9999-12-31'))->values();
        }

        if ($this->sortBy === 'due_desc') {
            return $activities->sortByDesc(fn (Activity $activity): string => (string) ($activity->due_date?->format('Y-m-d') ?? '0000-01-01'))->values();
        }

        if ($this->sortBy === 'priority_desc') {
            return $activities->sortByDesc(fn (Activity $activity): int => self::PRIORITY_ORDER[$activity->priority] ?? 0)->values();
        }

        if ($this->sortBy === 'priority_asc') {
            return $activities->sortBy(fn (Activity $activity): int => self::PRIORITY_ORDER[$activity->priority] ?? 0)->values();
        }

        return $activities;
    }

    /**
     * @return array<string, Collection<int, Activity>>
     */
    public function getGroupedActivitiesProperty(): array
    {
        $activities = $this->filteredActivities;

        /** @var array<string, Collection<int, Activity>> $grouped */
        $grouped = collect(self::STATUSES)
            ->mapWithKeys(
                fn (string $label, string $status): array => [
                    $status => $activities->where('status', $status)->values(),
                ],
            )
            ->all();

        return $grouped;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTimelinePayloadProperty(): array
    {
        return ProjectManagementKanbanTimeline::build($this->filteredActivities);
    }

    public function getFilesPayloadProperty(): array
    {
        $payload = ProjectManagementKanbanFiles::build(
            $this->filteredActivities,
            $this->filesCategory,
            $this->filesSort,
            trim($this->search),
        );

        if ($this->pinnedFileIds !== []) {
            $pinned = collect($payload['files'])
                ->filter(fn (array $file): bool => in_array($file['id'], $this->pinnedFileIds, true))
                ->sortBy(fn (array $file): int => array_search($file['id'], $this->pinnedFileIds, true))
                ->values();

            $rest = collect($payload['files'])
                ->reject(fn (array $file): bool => in_array($file['id'], $this->pinnedFileIds, true))
                ->values();

            $payload['files'] = $pinned->merge($rest)->all();
        }

        return $payload;
    }

    public function setViewMode(string $viewMode): void
    {
        if (! in_array($viewMode, ['board', 'timeline', 'files'], true)) {
            return;
        }

        $this->viewMode = $viewMode;
    }

    public function setFilesCategory(string $filesCategory): void
    {
        if (! in_array($filesCategory, ['all', 'image', 'document'], true)) {
            return;
        }

        $this->filesCategory = $filesCategory;
    }

    public function setFilesLayout(string $filesLayout): void
    {
        if (! in_array($filesLayout, ['grid', 'list'], true)) {
            return;
        }

        $this->filesLayout = $filesLayout;
    }

    public function togglePinFile(int $fileId): void
    {
        if ($fileId <= 0) {
            return;
        }

        if (in_array($fileId, $this->pinnedFileIds, true)) {
            $this->pinnedFileIds = array_values(array_filter(
                $this->pinnedFileIds,
                fn (int $id): bool => $id !== $fileId,
            ));

            return;
        }

        $this->pinnedFileIds[] = $fileId;
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'projectFilter', 'statusFilter', 'sortBy']);
        $this->projectFilter = 'all';
        $this->statusFilter = 'all';
        $this->sortBy = 'priority_desc';
    }

    public function getCreateActivityUrlProperty(): string
    {
        return ActivityResource::getUrl('create', panel: 'projects');
    }

    /**
     * @return array<string, string>
     */
    public function getSortOptionsProperty(): array
    {
        return [
            'priority_desc' => 'Prioridad (Alta primero)',
            'priority_asc' => 'Prioridad (Baja primero)',
            'due_asc' => 'Fecha límite (Más próxima)',
            'due_desc' => 'Fecha límite (Más lejana)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function addActivityNoteAction(): Action
    {
        return ProjectManagementKanbanActivityModalActions::makeAddNoteAction();
    }

    public function uploadActivityDocumentAction(): Action
    {
        return ProjectManagementKanbanActivityModalActions::makeUploadDocumentAction();
    }

    public function moveActivity(int $activityId, string $status): void
    {
        if (! array_key_exists($status, self::STATUSES)) {
            return;
        }

        $activity = Activity::query()->find($activityId);

        if ($activity === null) {
            return;
        }

        if ($activity->status === $status) {
            return;
        }

        $activity->update(['status' => $status]);

        $this->skipRender();

        Notification::make()
            ->title('Actividad movida')
            ->body('Estatus actualizado a '.self::STATUSES[$status].'.')
            ->success()
            ->send();
    }

    /**
     * @return array<string, string>
     */
    public function columnTone(string $status): array
    {
        return match ($status) {
            'todo' => [
                'badge' => 'text-slate-600 border-slate-200 bg-slate-100 dark:text-slate-300 dark:border-slate-700/70 dark:bg-slate-800/60',
                'bar' => 'bg-slate-500',
            ],
            'in_progress' => [
                'badge' => 'text-amber-800 border-amber-200 bg-amber-50 dark:text-amber-200 dark:border-amber-700/70 dark:bg-amber-950/40',
                'bar' => 'bg-amber-500',
            ],
            'review' => [
                'badge' => 'text-fuchsia-800 border-fuchsia-200 bg-fuchsia-50 dark:text-fuchsia-200 dark:border-fuchsia-700/70 dark:bg-fuchsia-950/40',
                'bar' => 'bg-fuchsia-500',
            ],
            'done' => [
                'badge' => 'text-emerald-800 border-emerald-200 bg-emerald-50 dark:text-emerald-200 dark:border-emerald-700/70 dark:bg-emerald-950/40',
                'bar' => 'bg-emerald-500',
            ],
            default => [
                'badge' => 'text-slate-600 border-slate-200 bg-slate-100 dark:text-slate-300 dark:border-slate-700/70 dark:bg-slate-800/60',
                'bar' => 'bg-slate-500',
            ],
        };
    }
}
