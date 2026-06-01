<?php

declare(strict_types=1);

namespace App\Filament\Projects\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Activities\ActivityResource;
use App\Filament\Projects\Resources\ProjectManagement\Activities\Tables\ActivitiesTable;
use App\Models\ProjectManagement\Activity;
use App\Models\ProjectManagement\Project;
use App\Support\Filament\ProjectManagement\ProjectManagementActivityAssignmentDisplay;
use App\Support\Filament\ProjectManagement\ProjectManagementKanbanActivitiesQuery;
use App\Support\Filament\ProjectManagement\ProjectManagementKanbanActivityModalActions;
use App\Support\Filament\ProjectManagement\ProjectManagementKanbanFiles;
use App\Support\Filament\ProjectManagement\ProjectManagementKanbanTimeline;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class Kanban extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

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

    public string $archivedFilter = 'active';

    public string $viewMode = 'board';

    public string $filesCategory = 'all';

    public string $filesSort = 'newest';

    public string $filesLayout = 'grid';

    /**
     * @var array<int, int>
     */
    public array $pinnedFileIds = [];

    public function mount(): void
    {
        $this->pinnedFileIds = $this->loadPinnedFileIdsFromSession();
    }

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
     * @var array<string, string>
     */
    private const ARCHIVED_FILTERS = [
        'active' => 'En tablero',
        'archived' => 'Archivadas',
        'all' => 'Todas',
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
            || $this->archivedFilter !== 'active'
            || $this->sortBy !== 'priority_desc';
    }

    /**
     * @return array<string, string>
     */
    public function getArchivedFilterOptionsProperty(): array
    {
        return self::ARCHIVED_FILTERS;
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
        $activities = $this->getKanbanActivitiesQuery()->get();

        ProjectManagementActivityAssignmentDisplay::preload($activities);

        return $activities;
    }

    public function table(Table $table): Table
    {
        return ActivitiesTable::configureForKanban($table);
    }

    protected function getTableQuery(): Builder
    {
        return $this->getKanbanActivitiesQuery();
    }

    protected function getKanbanActivitiesQuery(): Builder
    {
        $query = ProjectManagementKanbanActivitiesQuery::base();

        ProjectManagementKanbanActivitiesQuery::applyFilters(
            $query,
            $this->search,
            $this->archivedFilter,
            $this->projectFilter,
            $this->statusFilter,
        );

        return ProjectManagementKanbanActivitiesQuery::applySort($query, $this->sortBy);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedProjectFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedArchivedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->tableSort = null;
        $this->resetPage();
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

        $payload['files'] = ProjectManagementKanbanFiles::prioritizePinned(
            $payload['files'],
            $this->normalizedPinnedFileIds,
        );

        return $payload;
    }

    /**
     * @return array<int, int>
     */
    public function getNormalizedPinnedFileIdsProperty(): array
    {
        return collect($this->pinnedFileIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function setViewMode(string $viewMode): void
    {
        if (! in_array($viewMode, ['board', 'timeline', 'files', 'list'], true)) {
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

    public function updatedPinnedFileIds(): void
    {
        $this->pinnedFileIds = collect($this->pinnedFileIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $this->persistPinnedFileIdsToSession();
    }

    /**
     * @return array<int, int>
     */
    private function loadPinnedFileIdsFromSession(): array
    {
        $stored = session($this->pinnedFileIdsSessionKey(), []);

        if (! is_array($stored)) {
            return [];
        }

        return collect($stored)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function persistPinnedFileIdsToSession(): void
    {
        if (auth()->id() === null) {
            return;
        }

        session([$this->pinnedFileIdsSessionKey() => $this->normalizedPinnedFileIds]);
    }

    private function pinnedFileIdsSessionKey(): string
    {
        return 'projects.kanban.pinned_file_ids.'.(string) auth()->id();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'projectFilter', 'statusFilter', 'archivedFilter', 'sortBy']);
        $this->projectFilter = 'all';
        $this->statusFilter = 'all';
        $this->archivedFilter = 'active';
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

        $activity->update([
            'status' => $status,
            'kanban_archived_at' => $status === 'done' ? $activity->kanban_archived_at : null,
        ]);

        Notification::make()
            ->title('Actividad movida')
            ->body('Estatus actualizado a '.self::STATUSES[$status].'.')
            ->success()
            ->send();
    }

    public function archiveActivityFromKanban(int $activityId): void
    {
        $activity = Activity::query()->find($activityId);

        if ($activity === null) {
            return;
        }

        if ($activity->status !== 'done') {
            Notification::make()
                ->title('Solo actividades finalizadas')
                ->body('Únicamente puede archivar actividades en estatus Finalizada.')
                ->warning()
                ->send();

            return;
        }

        if ($activity->kanban_archived_at !== null) {
            return;
        }

        $activity->update([
            'kanban_archived_at' => now(),
        ]);

        Notification::make()
            ->title('Actividad archivada')
            ->body('La actividad ya no aparecerá en el Kanban. Sigue disponible en el proyecto.')
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
