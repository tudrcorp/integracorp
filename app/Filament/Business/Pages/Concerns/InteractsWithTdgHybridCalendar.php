<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages\Concerns;

use App\Enums\TdgCalendarDepartment;
use App\Enums\TdgCalendarGuardShift;
use App\Enums\TdgCalendarOffice;
use App\Models\RrhhColaborador;
use App\Models\TdgCalendarDay;
use App\Models\TdgCalendarDepartmentAssignment;
use App\Models\TdgCalendarGuardAssignment;
use App\Models\TdgCalendarOfficeAssignment;
use App\Support\TdgCalendarDepartmentCatalog;
use App\Support\TdgCalendarOfficeCatalog;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait InteractsWithTdgHybridCalendar
{
    private const TDG_CALENDAR_AVATAR_VISIBLE_LIMIT = 4;

    use InteractsWithCorporateCalendarShell {
        getCalendarDaysProperty as private shellGetCalendarDaysProperty;
        getCurrentWeekDaysProperty as private shellGetCurrentWeekDaysProperty;
    }

    public bool $isDayModalOpen = false;

    public string $modalWorkspace = 'offices';

    public bool $useSameGuardCollaborator = false;

    public string $collaboratorSearch = '';

    public string $agendaFilterCategory = '';

    public string $agendaFilterOffice = '';

    public string $agendaFilterGuardShift = '';

    public string $agendaFilterDepartment = '';

    /**
     * @var array<string, array<int, int>>
     */
    public array $officeAssignmentsForm = [];

    /**
     * @var array<string, int|null>
     */
    public array $guardAssignmentsForm = [];

    /**
     * @var array<int, string>
     */
    public array $departmentAssignmentsForm = [];

    /** @var array<int, array{id:int,name:string,email:string|null,avatar_url:string|null,initials:string}>|null */
    protected ?array $collaboratorOptionsCache = null;

    /** @var array<int, array{id:int,name:string,email:string|null,avatar_url:string|null,initials:string}>|null */
    protected ?array $operationsCollaboratorOptionsCache = null;

    /** @var array<string, array<string, mixed>>|null */
    protected ?array $tdgMonthDayPayloadCache = null;

    public function mountTdgHybridCalendar(): void
    {
        $this->resetOfficeAssignmentsForm();
        $this->resetGuardAssignmentsForm();
        $this->departmentAssignmentsForm = [];
    }

    public function calendarDayInteractionsEnabled(): bool
    {
        return true;
    }

    public function shouldShowTdgAgendaFilters(): bool
    {
        return true;
    }

    public function usesDepartmentFullLabelsInCalendar(): bool
    {
        return $this->resolveAgendaFilterCategory() === 'departments';
    }

    public function getHasActiveAgendaFiltersProperty(): bool
    {
        return $this->resolveAgendaFilterCategory() !== '';
    }

    public function clearAgendaFilters(): void
    {
        $this->agendaFilterCategory = '';
        $this->agendaFilterOffice = '';
        $this->agendaFilterGuardShift = '';
        $this->agendaFilterDepartment = '';
        $this->tdgMonthDayPayloadCache = null;
    }

    public function updatedAgendaFilterCategory(): void
    {
        if ($this->agendaFilterCategory === 'offices') {
            $this->agendaFilterGuardShift = '';
            $this->agendaFilterDepartment = '';
        } elseif ($this->agendaFilterCategory === 'guards') {
            $this->agendaFilterOffice = '';
            $this->agendaFilterDepartment = '';
        } elseif ($this->agendaFilterCategory === 'departments') {
            $this->agendaFilterOffice = '';
            $this->agendaFilterGuardShift = '';
        } else {
            $this->agendaFilterOffice = '';
            $this->agendaFilterGuardShift = '';
            $this->agendaFilterDepartment = '';
        }

        $this->tdgMonthDayPayloadCache = null;
    }

    public function updatedAgendaFilterOffice(): void
    {
        if ($this->agendaFilterOffice !== '') {
            $this->agendaFilterCategory = 'offices';
            $this->agendaFilterGuardShift = '';
            $this->agendaFilterDepartment = '';
        }

        $this->tdgMonthDayPayloadCache = null;
    }

    public function updatedAgendaFilterGuardShift(): void
    {
        if ($this->agendaFilterGuardShift !== '') {
            $this->agendaFilterCategory = 'guards';
            $this->agendaFilterOffice = '';
            $this->agendaFilterDepartment = '';
        }

        $this->tdgMonthDayPayloadCache = null;
    }

    public function updatedAgendaFilterDepartment(): void
    {
        if ($this->agendaFilterDepartment !== '') {
            $this->agendaFilterCategory = 'departments';
            $this->agendaFilterOffice = '';
            $this->agendaFilterGuardShift = '';
        }

        $this->tdgMonthDayPayloadCache = null;
    }

    public function assignOfficeCollaborator(string $office, int $colaboradorId): void
    {
        if (! in_array($office, TdgCalendarOffice::values(), true)) {
            return;
        }

        $current = collect($this->officeAssignmentsForm[$office] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0);

        if ($current->contains($colaboradorId)) {
            $this->officeAssignmentsForm[$office] = $current
                ->reject(fn (int $id): bool => $id === $colaboradorId)
                ->values()
                ->all();

            return;
        }

        $this->removeColaboradorFromOtherOffices($office, $colaboradorId);

        $this->officeAssignmentsForm[$office] = $current
            ->push($colaboradorId)
            ->unique()
            ->values()
            ->all();
    }

    public function removeOfficeCollaborator(string $office, int $colaboradorId): void
    {
        if (! array_key_exists($office, $this->officeAssignmentsForm)) {
            return;
        }

        $this->officeAssignmentsForm[$office] = collect($this->officeAssignmentsForm[$office] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->reject(fn (int $id): bool => $id === $colaboradorId)
            ->values()
            ->all();
    }

    public function clearOfficeCollaborators(string $office): void
    {
        if (! array_key_exists($office, $this->officeAssignmentsForm)) {
            return;
        }

        $this->officeAssignmentsForm[$office] = [];
    }

    public function isOfficeCollaboratorSelected(string $office, int $colaboradorId): bool
    {
        return in_array($colaboradorId, $this->officeAssignmentsForm[$office] ?? [], true);
    }

    /**
     * @return array<int, array{id:int,name:string,email:string|null,avatar_url:string|null,initials:string}>
     */
    public function resolveSelectedOfficeCollaborators(string $office): array
    {
        $ids = collect($this->officeAssignmentsForm[$office] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        return collect($this->collaboratorOptions)
            ->whereIn('id', $ids)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function colaboradorIdsAssignedToOtherOffices(string $excludeOffice): array
    {
        return collect($this->officeAssignmentsForm)
            ->reject(fn (mixed $ids, string $office): bool => $office === $excludeOffice)
            ->flatMap(function (mixed $ids): array {
                if (! is_array($ids)) {
                    return filled($ids) ? [(int) $ids] : [];
                }

                return collect($ids)
                    ->map(fn (mixed $id): int => (int) $id)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->all();
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id:int,name:string,email:string|null,avatar_url:string|null,initials:string}>
     */
    public function filteredCollaboratorOptionsForOffice(string $office): array
    {
        $excludedIds = $this->colaboradorIdsAssignedToOtherOffices($office);

        $available = collect($this->collaboratorOptions)
            ->reject(fn (array $collaborator): bool => in_array((int) $collaborator['id'], $excludedIds, true))
            ->reject(fn (array $collaborador): bool => $this->isOfficeCollaboratorSelected($office, (int) $collaborador['id']));

        $term = Str::lower(trim($this->collaboratorSearch));

        if ($term === '') {
            return $available->take(30)->values()->all();
        }

        return $available
            ->filter(function (array $collaborator) use ($term): bool {
                $name = Str::lower((string) ($collaborator['name'] ?? ''));
                $email = Str::lower((string) ($collaborator['email'] ?? ''));

                return Str::contains($name, $term) || Str::contains($email, $term);
            })
            ->take(40)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function colaboradorIdsAssignedToOtherGuardShifts(string $excludeShift): array
    {
        if ($this->useSameGuardCollaborator) {
            return [];
        }

        return collect($this->guardAssignmentsForm)
            ->reject(fn (mixed $id, string $shift): bool => $shift === $excludeShift)
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id:int,name:string,email:string|null,avatar_url:string|null,initials:string}>
     */
    public function filteredCollaboratorOptionsForGuardShift(string $guardShift): array
    {
        $excludedIds = $this->colaboradorIdsAssignedToOtherGuardShifts($guardShift);
        $selectedId = (int) ($this->guardAssignmentsForm[$guardShift] ?? 0);

        $available = collect($this->collaboratorOptions)
            ->reject(fn (array $collaborator): bool => in_array((int) $collaborator['id'], $excludedIds, true))
            ->reject(fn (array $collaborator): bool => $selectedId > 0 && (int) $collaborator['id'] === $selectedId);

        $term = Str::lower(trim($this->collaboratorSearch));

        if ($term === '') {
            return $available->values()->all();
        }

        return $available
            ->filter(function (array $collaborator) use ($term): bool {
                $name = Str::lower((string) ($collaborator['name'] ?? ''));
                $email = Str::lower((string) ($collaborator['email'] ?? ''));

                return Str::contains($name, $term) || Str::contains($email, $term);
            })
            ->values()
            ->all();
    }

    public function assignGuardCollaborator(string $guardShift, int $colaboradorId): void
    {
        if (! in_array($guardShift, TdgCalendarGuardShift::values(), true)) {
            return;
        }

        if (! $this->useSameGuardCollaborator) {
            $this->removeColaboradorFromOtherGuardShifts($guardShift, $colaboradorId);
        }

        $this->guardAssignmentsForm[$guardShift] = $colaboradorId;

        if ($this->useSameGuardCollaborator && $guardShift === TdgCalendarGuardShift::Proveedores->value) {
            $this->guardAssignmentsForm[TdgCalendarGuardShift::IlsCapitado->value] = $colaboradorId;
        }
    }

    public function clearGuardCollaborator(string $guardShift): void
    {
        if (! array_key_exists($guardShift, $this->guardAssignmentsForm)) {
            return;
        }

        $this->guardAssignmentsForm[$guardShift] = null;

        if ($this->useSameGuardCollaborator && $guardShift === TdgCalendarGuardShift::Proveedores->value) {
            $this->guardAssignmentsForm[TdgCalendarGuardShift::IlsCapitado->value] = null;
        }
    }

    /**
     * @return array{id:int,name:string,email:string|null,avatar_url:string|null,initials:string}|null
     */
    public function resolveSelectedCollaborator(?int $colaboradorId): ?array
    {
        if ($colaboradorId === null || $colaboradorId <= 0) {
            return null;
        }

        return collect($this->collaboratorOptions)->firstWhere('id', $colaboradorId);
    }

    public function openDayModal(string $date, string $workspace = 'offices'): void
    {
        $this->selectedDate = Carbon::parse($date)->toDateString();
        $this->selectedWeekDate = $this->selectedDate;
        $this->modalWorkspace = in_array($workspace, ['offices', 'guards', 'departments'], true) ? $workspace : 'offices';
        $this->collaboratorSearch = '';
        $this->hydrateDayFormsFromDatabase();
        $this->isDayModalOpen = true;
    }

    public function closeDayModal(): void
    {
        $this->isDayModalOpen = false;
        $this->collaboratorSearch = '';
        $this->tdgMonthDayPayloadCache = null;
    }

    public function setModalWorkspace(string $workspace): void
    {
        if (! in_array($workspace, ['offices', 'guards', 'departments'], true)) {
            return;
        }

        $this->modalWorkspace = $workspace;
        $this->collaboratorSearch = '';
    }

    public function updatedUseSameGuardCollaborator(bool $value): void
    {
        if (! $value) {
            return;
        }

        $primaryShift = TdgCalendarGuardShift::Proveedores->value;
        $secondaryShift = TdgCalendarGuardShift::IlsCapitado->value;
        $primaryCollaboratorId = $this->guardAssignmentsForm[$primaryShift] ?? null;

        if ($primaryCollaboratorId !== null) {
            $this->guardAssignmentsForm[$secondaryShift] = $primaryCollaboratorId;
        }
    }

    public function saveDayAssignments(): void
    {
        $validated = $this->validate([
            'selectedDate' => ['required', 'date'],
            'officeAssignmentsForm' => ['array'],
            'officeAssignmentsForm.*' => ['array'],
            'officeAssignmentsForm.*.*' => ['integer', 'exists:rrhh_colaboradors,id'],
            'guardAssignmentsForm' => ['array'],
            'guardAssignmentsForm.*' => ['nullable', 'integer', 'exists:rrhh_colaboradors,id'],
            'departmentAssignmentsForm' => ['array'],
            'departmentAssignmentsForm.*' => [Rule::in(TdgCalendarDepartment::values())],
            'useSameGuardCollaborator' => ['boolean'],
        ]);

        if ($this->useSameGuardCollaborator) {
            $proveedoresId = $this->guardAssignmentsForm[TdgCalendarGuardShift::Proveedores->value] ?? null;
            if ($proveedoresId !== null) {
                $this->guardAssignmentsForm[TdgCalendarGuardShift::IlsCapitado->value] = $proveedoresId;
            }
        }

        $calendarDate = (string) $validated['selectedDate'];

        $duplicateColaboradorIds = collect($this->officeAssignmentsForm)
            ->flatMap(function (mixed $ids): array {
                if (! is_array($ids)) {
                    return filled($ids) ? [(int) $ids] : [];
                }

                return collect($ids)
                    ->map(fn (mixed $id): int => (int) $id)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->all();
            })
            ->duplicates()
            ->unique()
            ->values();

        if ($duplicateColaboradorIds->isNotEmpty()) {
            Notification::make()
                ->title('Colaborador duplicado')
                ->body('Un colaborador no puede asistir a más de una oficina el mismo día.')
                ->warning()
                ->send();

            return;
        }

        $hasOfficeAssignments = collect($this->officeAssignmentsForm)
            ->flatten()
            ->contains(fn (mixed $id): bool => filled($id) && (int) $id > 0);
        $hasGuardAssignments = collect($this->guardAssignmentsForm)
            ->contains(fn (mixed $id): bool => filled($id));
        $hasDepartmentAssignments = $this->departmentAssignmentsForm !== [];

        if (! $hasOfficeAssignments && ! $hasGuardAssignments && ! $hasDepartmentAssignments) {
            TdgCalendarDay::query()
                ->whereDate('calendar_date', $calendarDate)
                ->each(fn (TdgCalendarDay $day): mixed => $day->delete());

            $this->tdgMonthDayPayloadCache = null;

            Notification::make()
                ->title('Día limpiado')
                ->body('Se eliminaron las asignaciones del día seleccionado.')
                ->success()
                ->send();

            return;
        }

        $day = TdgCalendarDay::query()->firstOrCreate(
            ['calendar_date' => $calendarDate],
            ['updated_by_user_id' => Auth::id()],
        );

        $day->update(['updated_by_user_id' => Auth::id()]);

        $this->syncOfficeAssignments($day);
        $this->syncGuardAssignments($day);
        $this->syncDepartmentAssignments($day);

        $this->tdgMonthDayPayloadCache = null;
        $this->hydrateDayFormsFromDatabase();

        Notification::make()
            ->title('Agenda guardada')
            ->body('Las asignaciones del día fueron actualizadas correctamente.')
            ->success()
            ->send();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCalendarDaysProperty(): array
    {
        $cursor = $this->resolveCorporateCalendarCursor();
        $start = $cursor->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $cursor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $payloadByDate = $this->resolveTdgMonthDayPayload($start, $end);

        $days = [];
        $day = $start->copy();

        while ($day->lessThanOrEqualTo($end)) {
            $isCurrentMonth = $day->isSameMonth($cursor);
            $isToday = $day->isToday();
            $isPastDate = $day->lt(now()->startOfDay());
            $dateKey = $day->toDateString();

            $payload = $payloadByDate[$dateKey] ?? null;

            $days[] = [
                'date' => $dateKey,
                'day_number' => (int) $day->format('j'),
                'is_current_month' => $isCurrentMonth,
                'is_today' => $isToday,
                'is_past_date' => $isPastDate,
                ...$this->buildTdgDayVisuals($isCurrentMonth, $payload),
                'is_filtered_out' => $isCurrentMonth && $this->hasActiveAgendaFilters && ($payload === null || ! $this->dayMatchesAgendaFilters($payload)),
            ];

            $day->addDay();
        }

        return $days;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCurrentWeekDaysProperty(): array
    {
        $baseDate = now();
        $startOfWeek = $baseDate->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $baseDate->copy()->endOfWeek(Carbon::SUNDAY);
        $payloadByDate = $this->resolveTdgMonthDayPayload($startOfWeek, $endOfWeek);

        $days = [];
        $cursor = $startOfWeek->copy();

        while ($cursor->lessThanOrEqualTo($endOfWeek)) {
            $dateKey = $cursor->toDateString();
            $payload = $payloadByDate[$dateKey] ?? null;

            $matchesFilters = $payload !== null && $this->dayMatchesAgendaFilters($payload);
            $scoped = $matchesFilters && $payload !== null
                ? $this->scopeDayPayloadToAgendaFilterCategory($payload)
                : [
                    'filtered_assignment_count' => 0,
                    'department_badges' => [],
                ];

            $days[] = [
                'date' => $dateKey,
                'day_label' => Str::upper($cursor->translatedFormat('D')),
                'day_number' => (int) $cursor->format('j'),
                'is_today' => $cursor->isToday(),
                'is_selected' => $dateKey === $this->selectedWeekDate,
                'activity_count' => (int) $scoped['filtered_assignment_count'],
                'social_platforms' => [],
                'social_badges' => $scoped['department_badges'],
                'department_label_mode' => $this->usesDepartmentFullLabelsInCalendar() ? 'full' : 'short',
                'is_filtered_out' => $this->hasActiveAgendaFilters && ($payload === null || ! $matchesFilters),
            ];

            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @return array<string, string>
     */
    public function getOfficeOptionsProperty(): array
    {
        return TdgCalendarOffice::options();
    }

    /**
     * @return array<string, string>
     */
    public function getGuardShiftOptionsProperty(): array
    {
        return TdgCalendarGuardShift::options();
    }

    /**
     * @return array<string, string>
     */
    public function getDepartmentOptionsProperty(): array
    {
        return TdgCalendarDepartment::options();
    }

    /**
     * @return array<string, array{label: string, short_label: string, color: string, chip_class: string, dot_class: string}>
     */
    public function getDepartmentCatalogProperty(): array
    {
        return TdgCalendarDepartmentCatalog::metadata();
    }

    /**
     * @return array<int, array{id:int,name:string,email:string|null}>
     */
    public function getCollaboratorOptionsProperty(): array
    {
        if ($this->collaboratorOptionsCache !== null) {
            return $this->collaboratorOptionsCache;
        }

        $this->collaboratorOptionsCache = $this->queryActiveColaboradores()
            ->orderBy('fullName')
            ->get(['id', 'fullName', 'emailCorporativo', 'emailPersonal', 'avatar'])
            ->map(fn (RrhhColaborador $colaborador): array => $this->mapColaboradorOption($colaborador))
            ->all();

        return $this->collaboratorOptionsCache;
    }

    /**
     * @return array<int, array{id:int,name:string,email:string|null}>
     */
    public function getOperationsCollaboratorOptionsProperty(): array
    {
        if ($this->operationsCollaboratorOptionsCache !== null) {
            return $this->operationsCollaboratorOptionsCache;
        }

        $this->operationsCollaboratorOptionsCache = $this->queryActiveColaboradores()
            ->whereHas('departamento', function (Builder $query): void {
                $query->whereRaw('UPPER(description) LIKE ?', ['%OPERACION%']);
            })
            ->orderBy('fullName')
            ->get(['id', 'fullName', 'emailCorporativo', 'emailPersonal', 'avatar'])
            ->map(fn (RrhhColaborador $colaborador): array => $this->mapColaboradorOption($colaborador))
            ->all();

        if ($this->operationsCollaboratorOptionsCache === []) {
            $this->operationsCollaboratorOptionsCache = $this->collaboratorOptions;
        }

        return $this->operationsCollaboratorOptionsCache;
    }

    /**
     * @return array<int, array{id:int,name:string,email:string|null}>
     */
    public function getFilteredCollaboratorOptionsProperty(): array
    {
        $options = $this->modalWorkspace === 'guards'
            ? $this->operationsCollaboratorOptions
            : $this->collaboratorOptions;

        $selectedIds = collect($this->officeAssignmentsForm)
            ->flatten()
            ->merge($this->guardAssignmentsForm)
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $selectedCollaborators = collect($options)
            ->whereIn('id', $selectedIds)
            ->values();

        $term = Str::lower(trim($this->collaboratorSearch));
        if ($term === '') {
            return $selectedCollaborators
                ->concat(collect($options)->take(30))
                ->unique('id')
                ->values()
                ->all();
        }

        $filtered = collect($options)
            ->filter(function (array $collaborator) use ($term): bool {
                $name = Str::lower((string) ($collaborator['name'] ?? ''));
                $email = Str::lower((string) ($collaborator['email'] ?? ''));

                return Str::contains($name, $term) || Str::contains($email, $term);
            })
            ->take(40)
            ->values();

        return $selectedCollaborators
            ->concat($filtered)
            ->unique('id')
            ->values()
            ->all();
    }

    public function toggleDepartment(string $department): void
    {
        if (! in_array($department, TdgCalendarDepartment::values(), true)) {
            return;
        }

        $current = collect($this->departmentAssignmentsForm);

        if ($current->contains($department)) {
            $this->departmentAssignmentsForm = $current
                ->reject(fn (string $value): bool => $value === $department)
                ->values()
                ->all();

            return;
        }

        $this->departmentAssignmentsForm = $current
            ->push($department)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    protected function buildTdgDayVisuals(bool $isCurrentMonth, ?array $payload): array
    {
        if (! $isCurrentMonth || $payload === null) {
            return [
                'activity_count' => 0,
                'task_primary' => null,
                'task_secondary' => null,
                'avatars' => [],
                'progress_width' => 0,
                'progress_tone' => 'none',
                'has_indicator' => false,
                'social_platforms' => [],
                'social_badges' => [],
                'has_social_publications' => false,
                'department_badges' => [],
                'office_count' => 0,
                'guard_count' => 0,
                'avatars_overflow' => 0,
                'avatars_tooltip' => [],
                'department_label_mode' => 'short',
                'is_filtered_out' => false,
            ];
        }

        $matchesFilters = $this->dayMatchesAgendaFilters($payload);
        $scoped = $matchesFilters
            ? $this->scopeDayPayloadToAgendaFilterCategory($payload)
            : [
                'department_badges' => [],
                'office_badges' => [],
                'office_count' => 0,
                'guard_count' => 0,
                'filter_avatars' => [],
                'filtered_assignment_count' => 0,
            ];

        $departmentBadges = $scoped['department_badges'];
        $officeBadges = $scoped['office_badges'] ?? [];
        $officeCount = (int) $scoped['office_count'];
        $guardCount = (int) $scoped['guard_count'];
        $assignmentCount = (int) $scoped['filtered_assignment_count'];
        $filterAvatars = $scoped['filter_avatars'];
        $avatarPresentation = $this->presentColaboradorAvatarsForDayDisplay($filterAvatars);
        $avatars = $filterAvatars;
        $usesFullDepartmentLabels = $this->usesDepartmentFullLabelsInCalendar();
        $resolveDepartmentDisplayLabel = fn (array $badge): string => $usesFullDepartmentLabels
            ? (string) ($badge['label'] ?? $badge['short_label'] ?? '')
            : (string) ($badge['short_label'] ?? '');
        $primaryDepartment = isset($departmentBadges[0]) ? $resolveDepartmentDisplayLabel($departmentBadges[0]) : null;
        $secondaryDepartment = isset($departmentBadges[1]) ? $resolveDepartmentDisplayLabel($departmentBadges[1]) : null;
        $category = $this->resolveAgendaFilterCategory();

        $progressWidth = $assignmentCount === 0
            ? 0
            : min(100, max(16, (int) floor(($assignmentCount / 6) * 100)));

        $progressTone = match ($category) {
            'guards' => $guardCount > 0 ? 'amber' : 'none',
            'offices' => $officeCount > 0 ? 'cyan' : 'none',
            'departments' => $departmentBadges !== [] ? 'cyan' : 'none',
            default => $departmentBadges !== [] ? 'cyan' : ($guardCount > 0 ? 'amber' : 'none'),
        };

        return [
            'activity_count' => $assignmentCount,
            'task_primary' => $primaryDepartment,
            'task_secondary' => $secondaryDepartment,
            'avatars' => $avatars,
            'progress_width' => $progressWidth,
            'progress_tone' => $progressTone,
            'has_indicator' => $matchesFilters && ($officeCount > 0 || $guardCount > 0 || $departmentBadges !== [] || $officeBadges !== []),
            'social_platforms' => [],
            'social_badges' => [],
            'has_social_publications' => false,
            'department_badges' => $departmentBadges,
            'office_badges' => $officeBadges,
            'office_count' => $officeCount,
            'guard_count' => $guardCount,
            'avatars_overflow' => $avatarPresentation['overflow_count'],
            'avatars_tooltip' => $avatarPresentation['tooltip_lines'],
            'department_label_mode' => $usesFullDepartmentLabels ? 'full' : 'short',
            'is_filtered_out' => ! $matchesFilters,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveAgendaFilterCategory(): string
    {
        if (in_array($this->agendaFilterCategory, ['offices', 'guards', 'departments'], true)) {
            return $this->agendaFilterCategory;
        }

        if ($this->agendaFilterOffice !== '') {
            return 'offices';
        }

        if ($this->agendaFilterGuardShift !== '') {
            return 'guards';
        }

        if ($this->agendaFilterDepartment !== '') {
            return 'departments';
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{department_badges: array<int, array<string, mixed>>, office_count: int, guard_count: int, filter_avatars: array<int, array<string, mixed>>, filtered_assignment_count: int}
     */
    protected function scopeDayPayloadToAgendaFilterCategory(array $payload): array
    {
        $category = $this->resolveAgendaFilterCategory();

        if ($category === '') {
            return [
                'department_badges' => $payload['department_badges'] ?? [],
                'office_badges' => $payload['office_badges'] ?? [],
                'office_count' => (int) ($payload['office_count'] ?? 0),
                'guard_count' => (int) ($payload['guard_count'] ?? 0),
                'filter_avatars' => $payload['filter_avatars'] ?? [],
                'filtered_assignment_count' => (int) ($payload['filtered_assignment_count'] ?? $payload['assignment_count'] ?? 0),
            ];
        }

        $officeBadges = collect($payload['office_badges'] ?? []);

        $departmentBadges = collect($payload['department_badges'] ?? []);
        $officeAssignments = collect($payload['office_assignments'] ?? []);
        $guardAssignments = collect($payload['guard_assignments'] ?? []);

        return match ($category) {
            'offices' => [
                'department_badges' => [],
                'office_badges' => $this->filterOfficeBadgesForCategory($officeBadges->all()),
                'office_count' => $this->countOfficeAssignmentsForFilter($officeAssignments),
                'guard_count' => 0,
                'filter_avatars' => $this->buildOfficeFilterAvatarsFromPayload($officeAssignments),
                'filtered_assignment_count' => $this->countOfficeAssignmentsForFilter($officeAssignments),
            ],
            'guards' => [
                'department_badges' => [],
                'office_badges' => [],
                'office_count' => 0,
                'guard_count' => $this->countGuardAssignmentsForFilter($guardAssignments),
                'filter_avatars' => $this->buildGuardColaboradorAvatarsFromPayload($guardAssignments),
                'filtered_assignment_count' => $this->countGuardAssignmentsForFilter($guardAssignments),
            ],
            'departments' => [
                'department_badges' => $this->filterDepartmentBadgesForCategory($departmentBadges->all()),
                'office_badges' => [],
                'office_count' => 0,
                'guard_count' => 0,
                'filter_avatars' => [],
                'filtered_assignment_count' => count($this->filterDepartmentBadgesForCategory($departmentBadges->all())),
            ],
            default => [
                'department_badges' => $payload['department_badges'] ?? [],
                'office_badges' => $payload['office_badges'] ?? [],
                'office_count' => (int) ($payload['office_count'] ?? 0),
                'guard_count' => (int) ($payload['guard_count'] ?? 0),
                'filter_avatars' => $payload['filter_avatars'] ?? [],
                'filtered_assignment_count' => (int) ($payload['filtered_assignment_count'] ?? $payload['assignment_count'] ?? 0),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function dayMatchesAgendaFilters(array $payload): bool
    {
        $category = $this->resolveAgendaFilterCategory();

        if ($category === '') {
            return true;
        }

        return match ($category) {
            'offices' => $this->countOfficeAssignmentsForFilter(collect($payload['office_assignments'] ?? [])) > 0,
            'guards' => $this->countGuardAssignmentsForFilter(collect($payload['guard_assignments'] ?? [])) > 0,
            'departments' => count($this->filterDepartmentBadgesForCategory($payload['department_badges'] ?? [])) > 0,
            default => true,
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $officeAssignments
     */
    protected function countOfficeAssignmentsForFilter(Collection $officeAssignments): int
    {
        return $officeAssignments
            ->filter(function (mixed $assignment): bool {
                if ($this->agendaFilterOffice === '') {
                    return true;
                }

                $office = $assignment->office?->value ?? (string) $assignment->getRawOriginal('office');

                return $office === $this->agendaFilterOffice;
            })
            ->count();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $guardAssignments
     */
    protected function countGuardAssignmentsForFilter(Collection $guardAssignments): int
    {
        return $guardAssignments
            ->filter(function (mixed $assignment): bool {
                if ($this->agendaFilterGuardShift === '') {
                    return true;
                }

                $shift = $assignment->guard_shift?->value ?? (string) $assignment->getRawOriginal('guard_shift');

                return $shift === $this->agendaFilterGuardShift;
            })
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildOfficeBadgesForDay(TdgCalendarDay $day): array
    {
        return $day->officeAssignments
            ->map(fn (TdgCalendarOfficeAssignment $assignment): string => $assignment->office?->value ?? (string) $assignment->getRawOriginal('office'))
            ->filter(fn (string $office): bool => $office !== '')
            ->unique()
            ->values()
            ->map(function (string $office): array {
                $meta = TdgCalendarOfficeCatalog::for($office);

                return [
                    'platform' => $office,
                    'modifier' => $meta['modifier'],
                    'label' => $meta['label'],
                    'short_label' => $meta['short_label'],
                    'color' => $meta['color'],
                    'chip_class' => $meta['chip_class'],
                    'dot_class' => $meta['dot_class'],
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $officeBadges
     * @return array<int, array<string, mixed>>
     */
    protected function filterOfficeBadgesForCategory(array $officeBadges): array
    {
        if ($this->agendaFilterOffice === '') {
            return $officeBadges;
        }

        return collect($officeBadges)
            ->filter(fn (array $badge): bool => ($badge['platform'] ?? '') === $this->agendaFilterOffice)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $departmentBadges
     * @return array<int, array<string, mixed>>
     */
    protected function filterDepartmentBadgesForCategory(array $departmentBadges): array
    {
        if ($this->agendaFilterDepartment === '') {
            return $departmentBadges;
        }

        return collect($departmentBadges)
            ->filter(fn (array $badge): bool => ($badge['platform'] ?? '') === $this->agendaFilterDepartment)
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $officeAssignments
     * @return array<int, array{name:string|null,email:string|null,avatar_url:string|null,initials:string,activity_titles:array<int,string>}>
     */
    protected function buildOfficeFilterAvatarsFromPayload(Collection $officeAssignments): array
    {
        /** @var array<int, array{name: string|null, email: string|null, avatar_url: string|null, initials: string, activity_titles: array<int, string>}> $byColaboradorId */
        $byColaboradorId = [];

        foreach ($officeAssignments as $assignment) {
            $office = $assignment->office?->value ?? (string) $assignment->getRawOriginal('office');

            if ($this->agendaFilterOffice !== '' && $office !== $this->agendaFilterOffice) {
                continue;
            }

            if ($assignment->colaborador === null) {
                continue;
            }

            $colaboradorId = (int) $assignment->rrhh_colaborador_id;
            $officeLabel = TdgCalendarOfficeCatalog::for($office)['label'];

            if (! array_key_exists($colaboradorId, $byColaboradorId)) {
                $byColaboradorId[$colaboradorId] = $this->buildCalendarAvatarData(
                    $assignment->colaborador,
                    $officeLabel,
                );

                continue;
            }

            $titles = $byColaboradorId[$colaboradorId]['activity_titles'];

            if (! in_array($officeLabel, $titles, true)) {
                $byColaboradorId[$colaboradorId]['activity_titles'][] = $officeLabel;
            }
        }

        return collect($byColaboradorId)
            ->filter(fn (array $avatar): bool => filled($avatar['name']))
            ->sortBy(fn (array $avatar): string => Str::lower((string) $avatar['name']))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{name: string|null, email: string|null, avatar_url: string|null, initials: string, activity_titles: array<int, string>}>  $avatars
     * @return array{visible: array<int, array<string, mixed>>, overflow_count: int, tooltip_lines: array<int, array{name: string, offices: string}>}
     */
    protected function presentColaboradorAvatarsForDayDisplay(array $avatars): array
    {
        $all = collect($avatars)
            ->filter(fn (array $avatar): bool => filled($avatar['name']))
            ->values();

        $tooltipLines = $all
            ->map(function (array $avatar): array {
                $offices = collect($avatar['activity_titles'] ?? [])
                    ->filter(fn (mixed $title): bool => is_string($title) && $title !== '')
                    ->unique()
                    ->values()
                    ->implode(', ');

                return [
                    'name' => (string) $avatar['name'],
                    'offices' => $offices !== '' ? $offices : 'Sin oficina',
                ];
            })
            ->all();

        return [
            'visible' => $all->take(self::TDG_CALENDAR_AVATAR_VISIBLE_LIMIT)->all(),
            'overflow_count' => max(0, $all->count() - self::TDG_CALENDAR_AVATAR_VISIBLE_LIMIT),
            'tooltip_lines' => $tooltipLines,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function resolveTdgMonthDayPayload(Carbon $start, Carbon $end): array
    {
        $cacheKey = $start->toDateString().':'.$end->toDateString();

        if ($this->tdgMonthDayPayloadCache !== null && ($this->tdgMonthDayPayloadCache['__key'] ?? null) === $cacheKey) {
            return $this->tdgMonthDayPayloadCache['days'] ?? [];
        }

        $days = TdgCalendarDay::query()
            ->whereDate('calendar_date', '>=', $start->toDateString())
            ->whereDate('calendar_date', '<=', $end->toDateString())
            ->with([
                'officeAssignments.colaborador:id,fullName,emailCorporativo,emailPersonal,avatar',
                'guardAssignments.colaborador:id,fullName,emailCorporativo,emailPersonal,avatar',
                'departmentAssignments',
            ])
            ->get();

        $payloadByDate = [];

        foreach ($days as $day) {
            $dateKey = $day->calendar_date->toDateString();
            $departmentBadges = $day->departmentAssignments
                ->map(function (TdgCalendarDepartmentAssignment $assignment): array {
                    $department = $assignment->department?->value ?? (string) $assignment->getRawOriginal('department');
                    $meta = TdgCalendarDepartmentCatalog::for($department);

                    return [
                        'platform' => $department,
                        'modifier' => $meta['modifier'],
                        'label' => $meta['label'],
                        'short_label' => $meta['short_label'],
                        'color' => $meta['color'],
                        'chip_class' => $meta['chip_class'],
                        'dot_class' => $meta['dot_class'],
                        'media' => [],
                        'media_count' => 0,
                    ];
                })
                ->values()
                ->all();

            $officeBadges = $this->buildOfficeBadgesForDay($day);
            $officeCount = $day->officeAssignments->count();
            $guardCount = $day->guardAssignments->count();
            $departmentCount = count($departmentBadges);
            $filterAvatars = $this->buildFilterAvatarsForDay($day, $departmentBadges);
            $filteredAssignmentCount = $this->countFilteredAssignmentsForDay(
                $day,
                $departmentBadges,
                $filterAvatars,
            );

            $payloadByDate[$dateKey] = [
                'assignment_count' => $officeCount + $guardCount + $departmentCount,
                'filtered_assignment_count' => $filteredAssignmentCount,
                'office_count' => $officeCount,
                'guard_count' => $guardCount,
                'department_badges' => $departmentBadges,
                'office_badges' => $officeBadges,
                'office_assignments' => $day->officeAssignments,
                'guard_assignments' => $day->guardAssignments,
                'filter_avatars' => $filterAvatars,
            ];
        }

        $this->tdgMonthDayPayloadCache = [
            '__key' => $cacheKey,
            'days' => $payloadByDate,
        ];

        return $payloadByDate;
    }

    private function hydrateDayFormsFromDatabase(): void
    {
        $this->resetOfficeAssignmentsForm();
        $this->resetGuardAssignmentsForm();
        $this->departmentAssignmentsForm = [];
        $this->useSameGuardCollaborator = false;

        $day = TdgCalendarDay::query()
            ->whereDate('calendar_date', $this->selectedDate)
            ->with(['officeAssignments', 'guardAssignments', 'departmentAssignments'])
            ->first();

        if ($day === null) {
            return;
        }

        foreach ($day->officeAssignments as $assignment) {
            $office = $assignment->office?->value ?? (string) $assignment->getRawOriginal('office');
            $colaboradorId = (int) $assignment->rrhh_colaborador_id;

            if (! isset($this->officeAssignmentsForm[$office])) {
                $this->officeAssignmentsForm[$office] = [];
            }

            if (! in_array($colaboradorId, $this->officeAssignmentsForm[$office], true)) {
                $this->officeAssignmentsForm[$office][] = $colaboradorId;
            }
        }

        foreach ($day->guardAssignments as $assignment) {
            $shift = $assignment->guard_shift?->value ?? (string) $assignment->getRawOriginal('guard_shift');
            $this->guardAssignmentsForm[$shift] = (int) $assignment->rrhh_colaborador_id;
        }

        $proveedoresId = $this->guardAssignmentsForm[TdgCalendarGuardShift::Proveedores->value] ?? null;
        $ilsCapitadoId = $this->guardAssignmentsForm[TdgCalendarGuardShift::IlsCapitado->value] ?? null;
        $this->useSameGuardCollaborator = $proveedoresId !== null
            && $ilsCapitadoId !== null
            && $proveedoresId === $ilsCapitadoId;

        $this->departmentAssignmentsForm = $day->departmentAssignments
            ->map(fn (TdgCalendarDepartmentAssignment $assignment): string => $assignment->department?->value ?? (string) $assignment->getRawOriginal('department'))
            ->values()
            ->all();
    }

    private function resetOfficeAssignmentsForm(): void
    {
        $this->officeAssignmentsForm = [];
        foreach (TdgCalendarOffice::cases() as $office) {
            $this->officeAssignmentsForm[$office->value] = [];
        }
    }

    private function resetGuardAssignmentsForm(): void
    {
        $this->guardAssignmentsForm = [];
        foreach (TdgCalendarGuardShift::cases() as $shift) {
            $this->guardAssignmentsForm[$shift->value] = null;
        }
    }

    private function removeColaboradorFromOtherGuardShifts(string $guardShift, int $colaboradorId): void
    {
        foreach (TdgCalendarGuardShift::values() as $otherShift) {
            if ($otherShift === $guardShift) {
                continue;
            }

            if ((int) ($this->guardAssignmentsForm[$otherShift] ?? 0) === $colaboradorId) {
                $this->guardAssignmentsForm[$otherShift] = null;
            }
        }
    }

    private function removeColaboradorFromOtherOffices(string $office, int $colaboradorId): void
    {
        foreach (TdgCalendarOffice::values() as $otherOffice) {
            if ($otherOffice === $office) {
                continue;
            }

            $this->officeAssignmentsForm[$otherOffice] = collect($this->officeAssignmentsForm[$otherOffice] ?? [])
                ->map(fn (mixed $id): int => (int) $id)
                ->reject(fn (int $id): bool => $id === $colaboradorId)
                ->values()
                ->all();
        }
    }

    private function syncOfficeAssignments(TdgCalendarDay $day): void
    {
        $desired = collect($this->officeAssignmentsForm)
            ->flatMap(function (mixed $colaboradorIds, string $office): Collection {
                $ids = is_array($colaboradorIds)
                    ? $colaboradorIds
                    : (filled($colaboradorIds) ? [(int) $colaboradorIds] : []);

                return collect($ids)
                    ->map(fn (mixed $id): int => (int) $id)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->unique()
                    ->map(fn (int $id): array => [
                        'office' => $office,
                        'rrhh_colaborador_id' => $id,
                    ]);
            });

        $day->officeAssignments()->delete();

        foreach ($desired as $assignment) {
            TdgCalendarOfficeAssignment::query()->create([
                'tdg_calendar_day_id' => $day->id,
                'office' => $assignment['office'],
                'rrhh_colaborador_id' => $assignment['rrhh_colaborador_id'],
            ]);
        }
    }

    private function syncGuardAssignments(TdgCalendarDay $day): void
    {
        $desired = collect($this->guardAssignmentsForm)
            ->filter(fn (mixed $colaboradorId): bool => filled($colaboradorId))
            ->map(fn (mixed $colaboradorId, string $shift): array => [
                'guard_shift' => $shift,
                'rrhh_colaborador_id' => (int) $colaboradorId,
            ]);

        $day->guardAssignments()
            ->whereNotIn('guard_shift', $desired->pluck('guard_shift')->all())
            ->delete();

        foreach ($desired as $assignment) {
            TdgCalendarGuardAssignment::query()->updateOrCreate(
                [
                    'tdg_calendar_day_id' => $day->id,
                    'guard_shift' => $assignment['guard_shift'],
                ],
                [
                    'rrhh_colaborador_id' => $assignment['rrhh_colaborador_id'],
                ],
            );
        }
    }

    private function syncDepartmentAssignments(TdgCalendarDay $day): void
    {
        $departments = collect($this->departmentAssignmentsForm)
            ->filter(fn (string $department): bool => $department !== '')
            ->unique()
            ->values();

        $day->departmentAssignments()
            ->whereNotIn('department', $departments->all())
            ->delete();

        foreach ($departments as $department) {
            TdgCalendarDepartmentAssignment::query()->updateOrCreate(
                [
                    'tdg_calendar_day_id' => $day->id,
                    'department' => $department,
                ],
                [],
            );
        }
    }

    /**
     * @return Builder<RrhhColaborador>
     */
    private function queryActiveColaboradores(): Builder
    {
        return RrhhColaborador::query()
            ->where(function (Builder $statusQuery): void {
                $statusQuery->whereNull('status')
                    ->orWhereRaw('UPPER(status) IN (?, ?)', ['ACTIVO', 'ACTIVE']);
            });
    }

    /**
     * @param  array<int, array<string, mixed>>  $departmentBadges
     * @return array<int, array{name:string|null,email:string|null,avatar_url:string|null,initials:string,activity_titles:array<int,string>}>
     */
    private function buildFilterAvatarsForDay(TdgCalendarDay $day, array $departmentBadges): array
    {
        return match ($this->resolveAgendaFilterCategory()) {
            'offices' => $this->buildOfficeFilterAvatarsFromPayload(collect($day->officeAssignments)),
            'guards' => $this->buildGuardColaboradorAvatarsFromPayload(collect($day->guardAssignments)),
            'departments' => [],
            default => collect($this->buildOfficeFilterAvatarsFromPayload(collect($day->officeAssignments)))
                ->merge($this->buildGuardColaboradorAvatarsFromPayload(collect($day->guardAssignments)))
                ->unique(fn (array $avatar): string => Str::lower((string) ($avatar['name'] ?? '')))
                ->values()
                ->all(),
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $guardAssignments
     * @return array<int, array{name: string|null, email: string|null, avatar_url: string|null, initials: string, activity_titles: array<int, string>}>
     */
    private function buildGuardColaboradorAvatarsFromPayload(Collection $guardAssignments): array
    {
        /** @var array<int, array{name: string|null, email: string|null, avatar_url: string|null, initials: string, activity_titles: array<int, string>}> $byColaboradorId */
        $byColaboradorId = [];

        foreach ($guardAssignments as $assignment) {
            $shift = $assignment->guard_shift?->value ?? (string) $assignment->getRawOriginal('guard_shift');

            if ($this->agendaFilterGuardShift !== '' && $shift !== $this->agendaFilterGuardShift) {
                continue;
            }

            if ($assignment->colaborador === null) {
                continue;
            }

            $colaboradorId = (int) $assignment->rrhh_colaborador_id;
            $shiftLabel = TdgCalendarGuardShift::tryFrom($shift)?->shortLabel() ?? 'Guardia';

            if (! array_key_exists($colaboradorId, $byColaboradorId)) {
                $byColaboradorId[$colaboradorId] = $this->buildCalendarAvatarData(
                    $assignment->colaborador,
                    $shiftLabel,
                );

                continue;
            }

            $titles = $byColaboradorId[$colaboradorId]['activity_titles'];

            if (! in_array($shiftLabel, $titles, true)) {
                $byColaboradorId[$colaboradorId]['activity_titles'][] = $shiftLabel;
            }
        }

        return collect($byColaboradorId)
            ->filter(fn (array $avatar): bool => filled($avatar['name']))
            ->sortBy(fn (array $avatar): string => Str::lower((string) $avatar['name']))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $departmentBadges
     * @param  array<int, array<string, mixed>>  $filterAvatars
     */
    private function countFilteredAssignmentsForDay(TdgCalendarDay $day, array $departmentBadges, array $filterAvatars): int
    {
        return match ($this->resolveAgendaFilterCategory()) {
            'offices' => $this->countOfficeAssignmentsForFilter(collect($day->officeAssignments)),
            'guards' => $this->countGuardAssignmentsForFilter(collect($day->guardAssignments)),
            'departments' => count($this->filterDepartmentBadgesForCategory($departmentBadges)),
            default => $day->officeAssignments->count()
                + $day->guardAssignments->count()
                + count($departmentBadges),
        };
    }

    /**
     * @return array{name:string|null,email:string|null,avatar_url:string|null,initials:string,activity_titles:array<int,string>}
     */
    private function buildCalendarAvatarData(RrhhColaborador $colaborador, string $activityTitle): array
    {
        $name = $colaborador->fullName;
        $email = $colaborador->emailCorporativo ?: $colaborador->emailPersonal;
        $avatar = is_string($colaborador->avatar) ? trim($colaborador->avatar) : '';
        $avatarUrl = null;

        if ($avatar !== '') {
            $normalizedPath = ltrim($avatar, '/');
            if (Storage::disk('public')->exists($normalizedPath)) {
                $avatarUrl = url('storage/'.$normalizedPath);
            }
        }

        return [
            'name' => $name,
            'email' => $email,
            'avatar_url' => $avatarUrl,
            'initials' => $this->resolveColaboradorInitials((string) $name),
            'activity_titles' => [$activityTitle],
        ];
    }

    private function resolveColaboradorInitials(string $name): string
    {
        $parts = collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter(fn (?string $part): bool => is_string($part) && $part !== '')
            ->values();

        if ($parts->isEmpty()) {
            return 'NA';
        }

        if ($parts->count() === 1) {
            return Str::upper(Str::substr((string) $parts->first(), 0, 2));
        }

        return Str::upper(
            Str::substr((string) $parts->first(), 0, 1)
            .Str::substr((string) $parts->last(), 0, 1)
        );
    }

    /**
     * @return array{id:int,name:string,email:string|null,avatar_url:string|null,initials:string}
     */
    private function mapColaboradorOption(RrhhColaborador $colaborador): array
    {
        $avatar = is_string($colaborador->avatar) ? trim($colaborador->avatar) : '';
        $avatarUrl = null;

        if ($avatar !== '') {
            $normalizedPath = ltrim($avatar, '/');
            if (Storage::disk('public')->exists($normalizedPath)) {
                $avatarUrl = url('storage/'.$normalizedPath);
            }
        }

        return [
            'id' => (int) $colaborador->id,
            'name' => (string) $colaborador->fullName,
            'email' => $colaborador->emailCorporativo ?: $colaborador->emailPersonal,
            'avatar_url' => $avatarUrl,
            'initials' => $this->resolveColaboradorInitials((string) $colaborador->fullName),
        ];
    }
}
