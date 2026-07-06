<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages\Concerns;

use App\Enums\TdgCalendarDepartment;
use App\Enums\TdgCalendarGuardShift;
use App\Enums\TdgCalendarOffice;
use App\Models\RrhhColaborador;
use App\Models\TdgCalendarDay;
use App\Models\TdgCalendarDepartmentAssignment;
use App\Models\TdgCalendarDepartmentColaboradorAssignment;
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

    public string $agendaFilterSystemsColaborador = '';

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

    /**
     * @var array<string, array<int, int>>
     */
    public array $departmentCollaboratorAssignmentsForm = [];

    /**
     * @var array<int, string>
     */
    public array $officeReplicationDates = [];

    public string $officeReplicationMonth = '';

    /**
     * @var array<int, string>
     */
    public array $guardReplicationDates = [];

    public string $guardReplicationMonth = '';

    /**
     * @var array<int, string>
     */
    public array $departmentReplicationDates = [];

    public string $departmentReplicationMonth = '';

    /** @var array<int, array{id:int,name:string,email:string|null,avatar_url:string|null,initials:string}>|null */
    protected ?array $collaboratorOptionsCache = null;

    /** @var array<int, array{id:int,name:string,email:string|null,avatar_url:string|null,initials:string}>|null */
    protected ?array $operationsCollaboratorOptionsCache = null;

    /** @var array<int, array{id:int,name:string,email:string|null,avatar_url:string|null,initials:string}>|null */
    protected ?array $systemsCollaboratorOptionsCache = null;

    /** @var array<string, array<string, mixed>>|null */
    protected ?array $tdgMonthDayPayloadCache = null;

    public function mountTdgHybridCalendar(): void
    {
        $this->resetOfficeAssignmentsForm();
        $this->resetGuardAssignmentsForm();
        $this->departmentAssignmentsForm = [];
        $this->departmentCollaboratorAssignmentsForm = [];
        $this->officeReplicationDates = [];
        $this->officeReplicationMonth = now()->format('Y-m');
        $this->guardReplicationDates = [];
        $this->guardReplicationMonth = now()->format('Y-m');
        $this->departmentReplicationDates = [];
        $this->departmentReplicationMonth = now()->format('Y-m');
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
        return $this->agendaFilterCategory === 'departments';
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
        $this->agendaFilterSystemsColaborador = '';
        $this->tdgMonthDayPayloadCache = null;
    }

    public function updatedAgendaFilterCategory(): void
    {
        if ($this->agendaFilterCategory === 'offices') {
            $this->agendaFilterGuardShift = '';
            $this->agendaFilterDepartment = '';
            $this->agendaFilterSystemsColaborador = '';
        } elseif ($this->agendaFilterCategory === 'guards') {
            $this->agendaFilterOffice = '';
            $this->agendaFilterDepartment = '';
            $this->agendaFilterSystemsColaborador = '';
        } elseif ($this->agendaFilterCategory === 'departments') {
            $this->agendaFilterOffice = '';
            $this->agendaFilterGuardShift = '';
        } else {
            $this->agendaFilterOffice = '';
            $this->agendaFilterGuardShift = '';
            $this->agendaFilterDepartment = '';
            $this->agendaFilterSystemsColaborador = '';
        }

        $this->tdgMonthDayPayloadCache = null;
    }

    public function updatedAgendaFilterOffice(): void
    {
        if ($this->agendaFilterOffice !== '') {
            $this->agendaFilterCategory = 'offices';
            $this->agendaFilterGuardShift = '';
            $this->agendaFilterDepartment = '';
            $this->agendaFilterSystemsColaborador = '';
        }

        $this->tdgMonthDayPayloadCache = null;
    }

    public function updatedAgendaFilterGuardShift(): void
    {
        if ($this->agendaFilterGuardShift !== '') {
            $this->agendaFilterCategory = 'guards';
            $this->agendaFilterOffice = '';
            $this->agendaFilterDepartment = '';
            $this->agendaFilterSystemsColaborador = '';
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

    public function updatedAgendaFilterSystemsColaborador(): void
    {
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

    public function getOfficeReplicationMonthLabelProperty(): string
    {
        return $this->replicationMonthLabel($this->officeReplicationMonth);
    }

    /**
     * @return array<int, array{date: string, day_number: int, is_current_month: bool, is_source_day: bool, is_selected: bool, is_disabled: bool}>
     */
    public function getOfficeReplicationCalendarDaysProperty(): array
    {
        return $this->buildReplicationCalendarDays($this->officeReplicationMonth, $this->officeReplicationDates);
    }

    public function getGuardReplicationMonthLabelProperty(): string
    {
        return $this->replicationMonthLabel($this->guardReplicationMonth);
    }

    /**
     * @return array<int, array{date: string, day_number: int, is_current_month: bool, is_source_day: bool, is_selected: bool, is_disabled: bool}>
     */
    public function getGuardReplicationCalendarDaysProperty(): array
    {
        return $this->buildReplicationCalendarDays($this->guardReplicationMonth, $this->guardReplicationDates);
    }

    public function previousGuardReplicationMonth(): void
    {
        $this->guardReplicationMonth = $this->resolveReplicationMonthCursor($this->guardReplicationMonth)
            ->subMonth()
            ->format('Y-m');
    }

    public function nextGuardReplicationMonth(): void
    {
        $this->guardReplicationMonth = $this->resolveReplicationMonthCursor($this->guardReplicationMonth)
            ->addMonth()
            ->format('Y-m');
    }

    public function toggleGuardReplicationDate(string $date): void
    {
        $this->guardReplicationDates = $this->toggleReplicationDate($date, $this->guardReplicationDates);
    }

    public function toggleGuardReplicationWeekday(int $isoWeekday): void
    {
        $this->guardReplicationDates = $this->toggleReplicationWeekday(
            $isoWeekday,
            $this->guardReplicationMonth,
            $this->guardReplicationDates,
        );
    }

    public function clearGuardReplicationDates(): void
    {
        $this->guardReplicationDates = [];
    }

    public function replicateGuardAssignmentsToSelectedDays(): void
    {
        $targetDates = $this->resolveReplicationTargetDates($this->guardReplicationDates);

        if ($targetDates->isEmpty()) {
            Notification::make()
                ->title('Selecciona días destino')
                ->body('Elige al menos un día en el mini calendario para replicar las guardias.')
                ->warning()
                ->send();

            return;
        }

        $guardSnapshot = $this->resolveGuardAssignmentsSnapshot();

        if (! $this->guardAssignmentsFormHasCollaborators($guardSnapshot)) {
            Notification::make()
                ->title('Sin colaboradores en guardias')
                ->body('Asigna al menos una guardia en el día origen antes de replicar.')
                ->warning()
                ->send();

            return;
        }

        $replicatedCount = 0;

        foreach ($targetDates as $targetDate) {
            $day = TdgCalendarDay::query()->firstOrCreate(
                ['calendar_date' => $targetDate],
                ['updated_by_user_id' => Auth::id()],
            );

            $day->update(['updated_by_user_id' => Auth::id()]);
            $this->syncGuardAssignments($day, $guardSnapshot);
            $replicatedCount++;
        }

        $this->tdgMonthDayPayloadCache = null;
        $this->guardReplicationDates = [];

        Notification::make()
            ->title('Guardias replicadas')
            ->body($replicatedCount === 1
                ? 'Las guardias se copiaron a 1 día.'
                : "Las guardias se copiaron a {$replicatedCount} días.")
            ->success()
            ->send();
    }

    public function getDepartmentReplicationMonthLabelProperty(): string
    {
        return $this->replicationMonthLabel($this->departmentReplicationMonth);
    }

    /**
     * @return array<int, array{date: string, day_number: int, is_current_month: bool, is_source_day: bool, is_selected: bool, is_disabled: bool}>
     */
    public function getDepartmentReplicationCalendarDaysProperty(): array
    {
        return $this->buildReplicationCalendarDays($this->departmentReplicationMonth, $this->departmentReplicationDates);
    }

    public function previousDepartmentReplicationMonth(): void
    {
        $this->departmentReplicationMonth = $this->resolveReplicationMonthCursor($this->departmentReplicationMonth)
            ->subMonth()
            ->format('Y-m');
    }

    public function nextDepartmentReplicationMonth(): void
    {
        $this->departmentReplicationMonth = $this->resolveReplicationMonthCursor($this->departmentReplicationMonth)
            ->addMonth()
            ->format('Y-m');
    }

    public function toggleDepartmentReplicationDate(string $date): void
    {
        $this->departmentReplicationDates = $this->toggleReplicationDate($date, $this->departmentReplicationDates);
    }

    public function toggleDepartmentReplicationWeekday(int $isoWeekday): void
    {
        $this->departmentReplicationDates = $this->toggleReplicationWeekday(
            $isoWeekday,
            $this->departmentReplicationMonth,
            $this->departmentReplicationDates,
        );
    }

    public function clearDepartmentReplicationDates(): void
    {
        $this->departmentReplicationDates = [];
    }

    public function replicateDepartmentAssignmentsToSelectedDays(): void
    {
        $targetDates = $this->resolveReplicationTargetDates($this->departmentReplicationDates);

        if ($targetDates->isEmpty()) {
            Notification::make()
                ->title('Selecciona días destino')
                ->body('Elige al menos un día en el mini calendario para replicar la agenda de departamentos.')
                ->warning()
                ->send();

            return;
        }

        $departmentSnapshot = $this->normalizeDepartmentCollaboratorAssignmentsForm(
            $this->departmentCollaboratorAssignmentsForm,
        );
        $departmentListSnapshot = collect($this->departmentAssignmentsForm)
            ->filter(fn (string $department): bool => $department !== '')
            ->unique()
            ->values()
            ->all();

        if ($departmentListSnapshot === [] || ! $this->departmentCollaboratorAssignmentsFormHasCollaborators($departmentSnapshot)) {
            Notification::make()
                ->title('Sin colaboradores de sistemas')
                ->body('Selecciona departamentos y asigna al menos un colaborador del área de sistemas antes de replicar.')
                ->warning()
                ->send();

            return;
        }

        $replicatedCount = 0;

        foreach ($targetDates as $targetDate) {
            $day = TdgCalendarDay::query()->firstOrCreate(
                ['calendar_date' => $targetDate],
                ['updated_by_user_id' => Auth::id()],
            );

            $day->update(['updated_by_user_id' => Auth::id()]);
            $this->syncDepartmentAssignments($day, $departmentListSnapshot);
            $this->syncDepartmentCollaboratorAssignments($day, $departmentSnapshot, $departmentListSnapshot);
            $replicatedCount++;
        }

        $this->tdgMonthDayPayloadCache = null;
        $this->departmentReplicationDates = [];

        Notification::make()
            ->title('Departamentos replicados')
            ->body($replicatedCount === 1
                ? 'La agenda de departamentos se copió a 1 día.'
                : "La agenda de departamentos se copió a {$replicatedCount} días.")
            ->success()
            ->send();
    }

    public function previousOfficeReplicationMonth(): void
    {
        $this->officeReplicationMonth = $this->resolveReplicationMonthCursor($this->officeReplicationMonth)
            ->subMonth()
            ->format('Y-m');
    }

    public function nextOfficeReplicationMonth(): void
    {
        $this->officeReplicationMonth = $this->resolveReplicationMonthCursor($this->officeReplicationMonth)
            ->addMonth()
            ->format('Y-m');
    }

    public function toggleOfficeReplicationDate(string $date): void
    {
        $this->officeReplicationDates = $this->toggleReplicationDate($date, $this->officeReplicationDates);
    }

    public function toggleOfficeReplicationWeekday(int $isoWeekday): void
    {
        $this->officeReplicationDates = $this->toggleReplicationWeekday(
            $isoWeekday,
            $this->officeReplicationMonth,
            $this->officeReplicationDates,
        );
    }

    public function clearOfficeReplicationDates(): void
    {
        $this->officeReplicationDates = [];
    }

    public function replicateOfficeAssignmentsToSelectedDays(): void
    {
        $targetDates = $this->resolveReplicationTargetDates($this->officeReplicationDates);

        if ($targetDates->isEmpty()) {
            Notification::make()
                ->title('Selecciona días destino')
                ->body('Elige al menos un día en el mini calendario para replicar la configuración de oficinas.')
                ->warning()
                ->send();

            return;
        }

        $officeSnapshot = $this->normalizeOfficeAssignmentsForm($this->officeAssignmentsForm);

        if (! $this->officeAssignmentsFormHasCollaborators($officeSnapshot)) {
            Notification::make()
                ->title('Sin colaboradores en oficinas')
                ->body('Asigna colaboradores a las oficinas del día origen antes de replicar.')
                ->warning()
                ->send();

            return;
        }

        $replicatedCount = 0;

        foreach ($targetDates as $targetDate) {
            $day = TdgCalendarDay::query()->firstOrCreate(
                ['calendar_date' => $targetDate],
                ['updated_by_user_id' => Auth::id()],
            );

            $day->update(['updated_by_user_id' => Auth::id()]);
            $this->syncOfficeAssignments($day, $officeSnapshot);
            $replicatedCount++;
        }

        $this->tdgMonthDayPayloadCache = null;
        $this->officeReplicationDates = [];

        Notification::make()
            ->title('Configuración replicada')
            ->body($replicatedCount === 1
                ? 'Las asignaciones de oficinas se copiaron a 1 día.'
                : "Las asignaciones de oficinas se copiaron a {$replicatedCount} días.")
            ->success()
            ->send();
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
        $excludeShiftEnum = TdgCalendarGuardShift::tryFrom($excludeShift);

        return collect($this->guardAssignmentsForm)
            ->reject(function (mixed $id, string $shift) use ($excludeShift, $excludeShiftEnum): bool {
                if ($shift === $excludeShift) {
                    return true;
                }

                if (! $this->useSameGuardCollaborator) {
                    return false;
                }

                $shiftEnum = TdgCalendarGuardShift::tryFrom($shift);

                return $excludeShiftEnum?->isDaytimeOperationsShift()
                    && $shiftEnum?->isDaytimeOperationsShift();
            })
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

    public function assignGuardCollaborator(string $guardShift, int $colaboradorId): void
    {
        if (! in_array($guardShift, TdgCalendarGuardShift::values(), true)) {
            return;
        }

        $this->removeColaboradorFromOtherGuardShifts($guardShift, $colaboradorId);

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
        $this->officeReplicationDates = [];
        $this->officeReplicationMonth = Carbon::parse($this->selectedDate)->format('Y-m');
        $this->guardReplicationDates = [];
        $this->guardReplicationMonth = Carbon::parse($this->selectedDate)->format('Y-m');
        $this->departmentReplicationDates = [];
        $this->departmentReplicationMonth = Carbon::parse($this->selectedDate)->format('Y-m');
        $this->hydrateDayFormsFromDatabase();
        $this->isDayModalOpen = true;
        $this->dispatch('tdg-modal-workspace-changed', workspace: $this->modalWorkspace);
    }

    public function closeDayModal(): void
    {
        $this->isDayModalOpen = false;
        $this->collaboratorSearch = '';
        $this->officeReplicationDates = [];
        $this->guardReplicationDates = [];
        $this->departmentReplicationDates = [];
        $this->tdgMonthDayPayloadCache = null;
    }

    public function setModalWorkspace(string $workspace): void
    {
        if (! in_array($workspace, ['offices', 'guards', 'departments'], true)) {
            return;
        }

        $this->modalWorkspace = $workspace;
        $this->collaboratorSearch = '';
        $this->dispatch('tdg-modal-workspace-changed', workspace: $this->modalWorkspace);
        $this->skipRender();
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
            'departmentCollaboratorAssignmentsForm' => ['array'],
            'departmentCollaboratorAssignmentsForm.*' => ['array'],
            'departmentCollaboratorAssignmentsForm.*.*' => ['integer', 'exists:rrhh_colaboradors,id'],
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
        $this->syncDepartmentCollaboratorAssignments($day);

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
                    'filter_avatars' => [],
                ];

            $weekVisuals = $this->buildTdgDayVisuals(true, $matchesFilters ? $payload : null);

            $days[] = [
                'date' => $dateKey,
                'day_label' => Str::upper($cursor->translatedFormat('D')),
                'day_number' => (int) $cursor->format('j'),
                'is_today' => $cursor->isToday(),
                'is_selected' => $dateKey === $this->selectedWeekDate,
                'activity_count' => (int) ($weekVisuals['activity_count'] ?? $scoped['filtered_assignment_count']),
                'task_primary' => $weekVisuals['task_primary'] ?? null,
                'task_secondary' => $weekVisuals['task_secondary'] ?? null,
                'avatars' => $weekVisuals['avatars'] ?? [],
                'avatars_overflow' => $weekVisuals['avatars_overflow'] ?? 0,
                'avatars_tooltip' => $weekVisuals['avatars_tooltip'] ?? [],
                'social_platforms' => [],
                'social_badges' => $scoped['department_badges'],
                'department_label_mode' => $weekVisuals['department_label_mode'] ?? 'short',
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
     * @return array<string, string>
     */
    public function getDepartmentFilterOptionsProperty(): array
    {
        return collect($this->departmentCatalog)
            ->mapWithKeys(fn (array $meta, string $department): array => [
                $department => (string) ($meta['display_label'] ?? $meta['short_label'] ?? ''),
            ])
            ->all();
    }

    /**
     * @return array<string, array{label: string, short_label: string, display_label: string, color: string, chip_class: string, dot_class: string}>
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
     * @return array<int, array{id:int,name:string,email:string|null,avatar_url:string|null,initials:string}>
     */
    public function getSystemsCollaboratorOptionsProperty(): array
    {
        if ($this->systemsCollaboratorOptionsCache !== null) {
            return $this->systemsCollaboratorOptionsCache;
        }

        $this->systemsCollaboratorOptionsCache = $this->querySystemsColaboradores()
            ->orderBy('fullName')
            ->get(['id', 'fullName', 'emailCorporativo', 'emailPersonal', 'avatar'])
            ->map(fn (RrhhColaborador $colaborador): array => $this->mapColaboradorOption($colaborador))
            ->all();

        return $this->systemsCollaboratorOptionsCache;
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

            unset($this->departmentCollaboratorAssignmentsForm[$department]);

            return;
        }

        $this->departmentAssignmentsForm = $current
            ->push($department)
            ->unique()
            ->values()
            ->all();

        if (! array_key_exists($department, $this->departmentCollaboratorAssignmentsForm)) {
            $this->departmentCollaboratorAssignmentsForm[$department] = [];
        }
    }

    public function assignDepartmentCollaborator(string $department, int $colaboradorId): void
    {
        if (! in_array($department, TdgCalendarDepartment::values(), true)) {
            return;
        }

        if (! in_array($department, $this->departmentAssignmentsForm, true)) {
            $this->departmentAssignmentsForm = collect($this->departmentAssignmentsForm)
                ->push($department)
                ->unique()
                ->values()
                ->all();
        }

        $current = collect($this->departmentCollaboratorAssignmentsForm[$department] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0);

        if ($current->contains($colaboradorId)) {
            $this->departmentCollaboratorAssignmentsForm[$department] = $current
                ->reject(fn (int $id): bool => $id === $colaboradorId)
                ->values()
                ->all();

            return;
        }

        $this->departmentCollaboratorAssignmentsForm[$department] = $current
            ->push($colaboradorId)
            ->unique()
            ->values()
            ->all();
    }

    public function removeDepartmentCollaborator(string $department, int $colaboradorId): void
    {
        if (! array_key_exists($department, $this->departmentCollaboratorAssignmentsForm)) {
            return;
        }

        $this->departmentCollaboratorAssignmentsForm[$department] = collect($this->departmentCollaboratorAssignmentsForm[$department] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->reject(fn (int $id): bool => $id === $colaboradorId)
            ->values()
            ->all();
    }

    public function clearDepartmentCollaborators(string $department): void
    {
        if (! array_key_exists($department, $this->departmentCollaboratorAssignmentsForm)) {
            return;
        }

        $this->departmentCollaboratorAssignmentsForm[$department] = [];
    }

    public function isDepartmentCollaboratorSelected(string $department, int $colaboradorId): bool
    {
        return in_array($colaboradorId, $this->departmentCollaboratorAssignmentsForm[$department] ?? [], true);
    }

    /**
     * @return array<int, array{id:int,name:string,email:string|null,avatar_url:string|null,initials:string}>
     */
    public function resolveSelectedDepartmentCollaborators(string $department): array
    {
        $ids = collect($this->departmentCollaboratorAssignmentsForm[$department] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        return collect($this->systemsCollaboratorOptions)
            ->whereIn('id', $ids)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id:int,name:string,email:string|null,avatar_url:string|null,initials:string}>
     */
    public function filteredCollaboratorOptionsForDepartment(string $department): array
    {
        $available = collect($this->systemsCollaboratorOptions)
            ->reject(fn (array $collaborator): bool => $this->isDepartmentCollaboratorSelected($department, (int) $collaborator['id']));

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
            : (string) ($badge['display_label'] ?? $badge['short_label'] ?? '');
        $primaryDepartment = isset($departmentBadges[0]) ? $resolveDepartmentDisplayLabel($departmentBadges[0]) : null;
        $secondaryDepartment = isset($departmentBadges[1]) ? $resolveDepartmentDisplayLabel($departmentBadges[1]) : null;
        $category = $this->resolveAgendaFilterCategory();

        if ($category === 'departments') {
            $departmentColaboradorCount = $this->countDepartmentColaboradorAssignmentsForFilter(
                collect($payload['department_colaborador_assignments'] ?? []),
            );
            $departmentBadgeCount = count($departmentBadges);
            $assignmentCount = max($departmentColaboradorCount, $departmentBadgeCount, $assignmentCount);
        }

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
            'task_primary' => $usesFullDepartmentLabels ? $primaryDepartment : null,
            'task_secondary' => $usesFullDepartmentLabels ? $secondaryDepartment : null,
            'avatars' => $avatars,
            'progress_width' => $progressWidth,
            'progress_tone' => $progressTone,
            'has_indicator' => $matchesFilters && (
                $officeCount > 0
                || $guardCount > 0
                || $departmentBadges !== []
                || $officeBadges !== []
                || $filterAvatars !== []
            ),
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

    protected function resolveAgendaFilterSystemsColaboradorId(): ?int
    {
        if ($this->agendaFilterCategory !== 'departments') {
            return null;
        }

        $colaboradorId = (int) $this->agendaFilterSystemsColaborador;

        return $colaboradorId > 0 ? $colaboradorId : null;
    }

    /**
     * @return array<string, string>
     */
    public function getSystemsColaboradorFilterOptionsProperty(): array
    {
        return collect($this->systemsCollaboratorOptions)
            ->mapWithKeys(fn (array $colaborador): array => [
                (string) $colaborador['id'] => (string) $colaborador['name'],
            ])
            ->all();
    }

    public function getAgendaFilterSystemsColaboradorLabelProperty(): ?string
    {
        $colaboradorId = $this->resolveAgendaFilterSystemsColaboradorId();

        if ($colaboradorId === null) {
            return null;
        }

        return $this->systemsColaboradorFilterOptions[(string) $colaboradorId] ?? null;
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
                'department_badges' => $this->filterDepartmentBadgesForCategory($departmentBadges->all(), $payload),
                'office_badges' => [],
                'office_count' => 0,
                'guard_count' => 0,
                'filter_avatars' => $this->buildDepartmentColaboradorAvatarsFromPayload(
                    collect($payload['department_colaborador_assignments'] ?? []),
                ),
                'filtered_assignment_count' => max(
                    $this->countDepartmentColaboradorAssignmentsForFilter(
                        collect($payload['department_colaborador_assignments'] ?? []),
                    ),
                    count($this->filterDepartmentBadgesForCategory($departmentBadges->all(), $payload)),
                ),
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
            'departments' => $this->dayMatchesDepartmentAgendaFilters($payload),
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function dayMatchesDepartmentAgendaFilters(array $payload): bool
    {
        $assignmentCount = $this->countDepartmentColaboradorAssignmentsForFilter(
            collect($payload['department_colaborador_assignments'] ?? []),
        );

        if ($assignmentCount > 0) {
            return true;
        }

        if ($this->resolveAgendaFilterSystemsColaboradorId() !== null) {
            return false;
        }

        return count($this->filterDepartmentBadgesForCategory($payload['department_badges'] ?? [], $payload)) > 0;
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
    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<int, array<string, mixed>>
     */
    protected function filterDepartmentBadgesForCategory(array $departmentBadges, ?array $payload = null): array
    {
        $badges = collect($departmentBadges);

        if ($this->agendaFilterDepartment !== '') {
            $badges = $badges->filter(
                fn (array $badge): bool => ($badge['platform'] ?? '') === $this->agendaFilterDepartment,
            );
        }

        $colaboradorId = $this->resolveAgendaFilterSystemsColaboradorId();

        if ($colaboradorId === null) {
            return $badges->values()->all();
        }

        $departmentsForColaborador = $this->resolveDepartmentsForSystemsColaboradorFromPayload($payload, $colaboradorId);

        if ($departmentsForColaborador === []) {
            return [];
        }

        $filtered = $badges
            ->filter(fn (array $badge): bool => in_array((string) ($badge['platform'] ?? ''), $departmentsForColaborador, true))
            ->values();

        if ($filtered->isNotEmpty()) {
            return $filtered->all();
        }

        return collect($departmentsForColaborador)
            ->map(function (string $department): array {
                $meta = TdgCalendarDepartmentCatalog::for($department);

                return [
                    'platform' => $department,
                    'modifier' => $meta['modifier'],
                    'label' => $meta['label'],
                    'short_label' => $meta['short_label'],
                    'display_label' => $meta['display_label'] ?? $meta['short_label'],
                    'color' => $meta['color'],
                    'chip_class' => $meta['chip_class'],
                    'dot_class' => $meta['dot_class'],
                    'media' => [],
                    'media_count' => 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<int, string>
     */
    protected function resolveDepartmentsForSystemsColaboradorFromPayload(?array $payload, int $colaboradorId): array
    {
        if ($payload === null) {
            return [];
        }

        return collect($payload['department_colaborador_assignments'] ?? [])
            ->filter(function (mixed $assignment) use ($colaboradorId): bool {
                if (! $assignment instanceof TdgCalendarDepartmentColaboradorAssignment) {
                    return false;
                }

                if ((int) $assignment->rrhh_colaborador_id !== $colaboradorId) {
                    return false;
                }

                $department = $assignment->department?->value ?? (string) $assignment->getRawOriginal('department');

                if ($this->agendaFilterDepartment !== '' && $department !== $this->agendaFilterDepartment) {
                    return false;
                }

                return $department !== '';
            })
            ->map(fn (TdgCalendarDepartmentColaboradorAssignment $assignment): string => $assignment->department?->value ?? (string) $assignment->getRawOriginal('department'))
            ->unique()
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

                $detailFallback = $this->resolveAgendaFilterCategory() === 'departments'
                    ? 'Sin departamento'
                    : 'Sin oficina';

                return [
                    'name' => (string) $avatar['name'],
                    'offices' => $offices !== '' ? $offices : $detailFallback,
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
                'departmentColaboradorAssignments.colaborador:id,fullName,emailCorporativo,emailPersonal,avatar',
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
                        'display_label' => $meta['display_label'] ?? $meta['short_label'],
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
            $departmentColaboradorCount = $day->departmentColaboradorAssignments->count();
            $departmentCount = max(count($departmentBadges), $departmentColaboradorCount);
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
                'department_colaborador_assignments' => $day->departmentColaboradorAssignments,
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
        $this->departmentCollaboratorAssignmentsForm = [];
        $this->useSameGuardCollaborator = false;

        $day = TdgCalendarDay::query()
            ->whereDate('calendar_date', $this->selectedDate)
            ->with(['officeAssignments', 'guardAssignments', 'departmentAssignments', 'departmentColaboradorAssignments'])
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

        foreach ($day->departmentColaboradorAssignments as $assignment) {
            $department = $assignment->department?->value ?? (string) $assignment->getRawOriginal('department');
            $colaboradorId = (int) $assignment->rrhh_colaborador_id;

            if (! in_array($department, $this->departmentAssignmentsForm, true)) {
                $this->departmentAssignmentsForm[] = $department;
            }

            if (! isset($this->departmentCollaboratorAssignmentsForm[$department])) {
                $this->departmentCollaboratorAssignmentsForm[$department] = [];
            }

            if (! in_array($colaboradorId, $this->departmentCollaboratorAssignmentsForm[$department], true)) {
                $this->departmentCollaboratorAssignmentsForm[$department][] = $colaboradorId;
            }
        }
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
        $currentShift = TdgCalendarGuardShift::tryFrom($guardShift);

        foreach (TdgCalendarGuardShift::values() as $otherShift) {
            if ($otherShift === $guardShift) {
                continue;
            }

            if ($this->useSameGuardCollaborator) {
                $otherShiftEnum = TdgCalendarGuardShift::tryFrom($otherShift);

                if ($currentShift?->isDaytimeOperationsShift() && $otherShiftEnum?->isDaytimeOperationsShift()) {
                    continue;
                }
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

    private function syncOfficeAssignments(TdgCalendarDay $day, ?array $officeAssignmentsForm = null): void
    {
        $form = $officeAssignmentsForm ?? $this->officeAssignmentsForm;

        $desired = collect($form)
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

    private function syncGuardAssignments(TdgCalendarDay $day, ?array $guardAssignmentsForm = null): void
    {
        $form = $guardAssignmentsForm ?? $this->guardAssignmentsForm;

        $desired = collect($form)
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

    /**
     * @param  array<int, string>|null  $departmentAssignmentsForm
     */
    private function syncDepartmentAssignments(TdgCalendarDay $day, ?array $departmentAssignmentsForm = null): void
    {
        $departments = collect($departmentAssignmentsForm ?? $this->departmentAssignmentsForm)
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
     * @param  array<string, array<int, int>>|null  $departmentCollaboratorAssignmentsForm
     * @param  array<int, string>|null  $departmentAssignmentsForm
     */
    private function syncDepartmentCollaboratorAssignments(
        TdgCalendarDay $day,
        ?array $departmentCollaboratorAssignmentsForm = null,
        ?array $departmentAssignmentsForm = null,
    ): void {
        $allowedDepartments = collect($departmentAssignmentsForm ?? $this->departmentAssignmentsForm)
            ->filter(fn (string $department): bool => $department !== '')
            ->unique()
            ->values()
            ->all();

        $form = $departmentCollaboratorAssignmentsForm ?? $this->departmentCollaboratorAssignmentsForm;

        $desired = collect($form)
            ->only($allowedDepartments)
            ->flatMap(function (mixed $colaboradorIds, string $department): Collection {
                $ids = is_array($colaboradorIds)
                    ? $colaboradorIds
                    : (filled($colaboradorIds) ? [(int) $colaboradorIds] : []);

                return collect($ids)
                    ->map(fn (mixed $id): int => (int) $id)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->unique()
                    ->map(fn (int $id): array => [
                        'department' => $department,
                        'rrhh_colaborador_id' => $id,
                    ]);
            });

        $day->departmentColaboradorAssignments()->delete();

        foreach ($desired as $assignment) {
            TdgCalendarDepartmentColaboradorAssignment::query()->create([
                'tdg_calendar_day_id' => $day->id,
                'department' => $assignment['department'],
                'rrhh_colaborador_id' => $assignment['rrhh_colaborador_id'],
            ]);
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
     * @return Builder<RrhhColaborador>
     */
    private function querySystemsColaboradores(): Builder
    {
        return $this->queryActiveColaboradores()
            ->whereHas('departamento', function (Builder $query): void {
                $query->whereRaw('UPPER(description) LIKE ?', ['%SISTEMA%']);
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
            'departments' => $this->buildDepartmentColaboradorAvatarsFromPayload(collect($day->departmentColaboradorAssignments)),
            default => $this->mergeCalendarAvatars(
                $this->buildOfficeFilterAvatarsFromPayload(collect($day->officeAssignments)),
                $this->buildGuardColaboradorAvatarsFromPayload(collect($day->guardAssignments)),
                $this->buildDepartmentColaboradorAvatarsFromPayload(collect($day->departmentColaboradorAssignments)),
            ),
        };
    }

    /**
     * @param  array<int, array{name: string|null, email: string|null, avatar_url: string|null, initials: string, activity_titles: array<int, string>}>  ...$avatarGroups
     * @return array<int, array{name: string|null, email: string|null, avatar_url: string|null, initials: string, activity_titles: array<int, string>}>
     */
    private function mergeCalendarAvatars(array ...$avatarGroups): array
    {
        /** @var array<string, array{name: string|null, email: string|null, avatar_url: string|null, initials: string, activity_titles: array<int, string>}> $byNameKey */
        $byNameKey = [];

        foreach (array_merge(...$avatarGroups) as $avatar) {
            $nameKey = Str::lower((string) ($avatar['name'] ?? ''));

            if ($nameKey === '') {
                continue;
            }

            if (! array_key_exists($nameKey, $byNameKey)) {
                $byNameKey[$nameKey] = $avatar;

                continue;
            }

            $existingTitles = $byNameKey[$nameKey]['activity_titles'] ?? [];
            $incomingTitles = $avatar['activity_titles'] ?? [];

            $byNameKey[$nameKey]['activity_titles'] = collect($existingTitles)
                ->merge($incomingTitles)
                ->filter(fn (mixed $title): bool => is_string($title) && $title !== '')
                ->unique()
                ->values()
                ->all();
        }

        return collect($byNameKey)
            ->sortBy(fn (array $avatar): string => Str::lower((string) $avatar['name']))
            ->values()
            ->all();
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
            'departments' => $this->countDepartmentColaboradorAssignmentsForFilter(
                collect($day->departmentColaboradorAssignments),
            ),
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
    private function replicationMonthLabel(string $monthYm): string
    {
        try {
            return Carbon::parse($monthYm.'-01')->translatedFormat('F Y');
        } catch (\Throwable) {
            return now()->translatedFormat('F Y');
        }
    }

    private function resolveReplicationMonthCursor(string $monthYm): Carbon
    {
        try {
            return Carbon::parse($monthYm.'-01')->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    /**
     * @param  array<int, string>  $selectedDates
     * @return array<int, array{date: string, day_number: int, is_current_month: bool, is_source_day: bool, is_selected: bool, is_disabled: bool}>
     */
    private function buildReplicationCalendarDays(string $monthYm, array $selectedDates): array
    {
        $month = $this->resolveReplicationMonthCursor($monthYm);
        $start = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $sourceDate = Carbon::parse($this->selectedDate)->toDateString();
        $days = [];
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            $dateKey = $cursor->toDateString();

            $days[] = [
                'date' => $dateKey,
                'day_number' => (int) $cursor->format('j'),
                'is_current_month' => $cursor->isSameMonth($month),
                'is_source_day' => $dateKey === $sourceDate,
                'is_selected' => in_array($dateKey, $selectedDates, true),
                'is_disabled' => $dateKey === $sourceDate,
            ];

            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @param  array<int, string>  $selectedDates
     * @return array<int, string>
     */
    private function toggleReplicationDate(string $date, array $selectedDates): array
    {
        $normalizedDate = Carbon::parse($date)->toDateString();

        if ($normalizedDate === Carbon::parse($this->selectedDate)->toDateString()) {
            return $selectedDates;
        }

        if (in_array($normalizedDate, $selectedDates, true)) {
            return collect($selectedDates)
                ->reject(fn (string $value): bool => $value === $normalizedDate)
                ->values()
                ->all();
        }

        return collect($selectedDates)
            ->push($normalizedDate)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $selectedDates
     * @return array<int, string>
     */
    private function toggleReplicationWeekday(int $isoWeekday, string $monthYm, array $selectedDates): array
    {
        if ($isoWeekday < 1 || $isoWeekday > 7) {
            return $selectedDates;
        }

        $month = $this->resolveReplicationMonthCursor($monthYm);
        $sourceDate = Carbon::parse($this->selectedDate)->toDateString();
        $datesInMonth = collect();
        $cursor = $month->copy()->startOfMonth();

        while ($cursor->isSameMonth($month)) {
            if ($cursor->isoWeekday() === $isoWeekday && $cursor->toDateString() !== $sourceDate) {
                $datesInMonth->push($cursor->toDateString());
            }

            $cursor->addDay();
        }

        if ($datesInMonth->isEmpty()) {
            return $selectedDates;
        }

        $allSelected = $datesInMonth->every(
            fn (string $date): bool => in_array($date, $selectedDates, true),
        );

        if ($allSelected) {
            return collect($selectedDates)
                ->reject(fn (string $date): bool => $datesInMonth->contains($date))
                ->values()
                ->all();
        }

        return collect($selectedDates)
            ->merge($datesInMonth)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $selectedDates
     * @return Collection<int, string>
     */
    private function resolveReplicationTargetDates(array $selectedDates): Collection
    {
        return collect($selectedDates)
            ->map(fn (string $date): string => Carbon::parse($date)->toDateString())
            ->reject(fn (string $date): bool => $date === Carbon::parse($this->selectedDate)->toDateString())
            ->unique()
            ->values();
    }

    /**
     * @return array<string, int|null>
     */
    private function resolveGuardAssignmentsSnapshot(): array
    {
        $form = $this->normalizeGuardAssignmentsForm($this->guardAssignmentsForm);

        if ($this->useSameGuardCollaborator) {
            $proveedoresId = $form[TdgCalendarGuardShift::Proveedores->value] ?? null;

            if ($proveedoresId !== null) {
                $form[TdgCalendarGuardShift::IlsCapitado->value] = $proveedoresId;
            }
        }

        return $form;
    }

    /**
     * @param  array<string, int|null>  $guardAssignmentsForm
     */
    private function guardAssignmentsFormHasCollaborators(array $guardAssignmentsForm): bool
    {
        return collect($guardAssignmentsForm)
            ->contains(fn (mixed $id): bool => filled($id) && (int) $id > 0);
    }

    /**
     * @param  array<string, int|null>  $guardAssignmentsForm
     * @return array<string, int|null>
     */
    private function normalizeGuardAssignmentsForm(array $guardAssignmentsForm): array
    {
        $normalized = [];

        foreach (TdgCalendarGuardShift::values() as $shift) {
            $value = $guardAssignmentsForm[$shift] ?? null;
            $normalized[$shift] = filled($value) && (int) $value > 0 ? (int) $value : null;
        }

        return $normalized;
    }

    /**
     * @param  array<string, array<int, int>>  $officeAssignmentsForm
     * @return array<string, array<int, int>>
     */
    private function normalizeOfficeAssignmentsForm(array $officeAssignmentsForm): array
    {
        $normalized = [];

        foreach (TdgCalendarOffice::values() as $office) {
            $normalized[$office] = collect($officeAssignmentsForm[$office] ?? [])
                ->map(fn (mixed $id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        return $normalized;
    }

    /**
     * @param  array<string, array<int, int>>  $officeAssignmentsForm
     */
    private function officeAssignmentsFormHasCollaborators(array $officeAssignmentsForm): bool
    {
        return collect($officeAssignmentsForm)
            ->flatten()
            ->contains(fn (mixed $id): bool => filled($id) && (int) $id > 0);
    }

    /**
     * @param  array<string, array<int, int>>  $departmentCollaboratorAssignmentsForm
     * @return array<string, array<int, int>>
     */
    private function normalizeDepartmentCollaboratorAssignmentsForm(array $departmentCollaboratorAssignmentsForm): array
    {
        $normalized = [];

        foreach (TdgCalendarDepartment::values() as $department) {
            $normalized[$department] = collect($departmentCollaboratorAssignmentsForm[$department] ?? [])
                ->map(fn (mixed $id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        return $normalized;
    }

    /**
     * @param  array<string, array<int, int>>  $departmentCollaboratorAssignmentsForm
     */
    private function departmentCollaboratorAssignmentsFormHasCollaborators(array $departmentCollaboratorAssignmentsForm): bool
    {
        return collect($departmentCollaboratorAssignmentsForm)
            ->flatten()
            ->contains(fn (mixed $id): bool => filled($id) && (int) $id > 0);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TdgCalendarDepartmentColaboradorAssignment>  $assignments
     * @return array<int, array{name: string|null, email: string|null, avatar_url: string|null, initials: string, activity_titles: array<int, string>}>
     */
    private function buildDepartmentColaboradorAvatarsFromPayload(Collection $assignments): array
    {
        /** @var array<int, array{name: string|null, email: string|null, avatar_url: string|null, initials: string, activity_titles: array<int, string>}> $byColaboradorId */
        $byColaboradorId = [];

        foreach ($assignments as $assignment) {
            $department = $assignment->department?->value ?? (string) $assignment->getRawOriginal('department');

            if ($this->agendaFilterDepartment !== '' && $department !== $this->agendaFilterDepartment) {
                continue;
            }

            if ($assignment->colaborador === null) {
                continue;
            }

            $colaboradorId = (int) $assignment->rrhh_colaborador_id;
            $systemsColaboradorId = $this->resolveAgendaFilterSystemsColaboradorId();

            if ($systemsColaboradorId !== null && $colaboradorId !== $systemsColaboradorId) {
                continue;
            }
            $meta = TdgCalendarDepartmentCatalog::for($department);
            $departmentLabel = $this->usesDepartmentFullLabelsInCalendar()
                ? (string) ($meta['label'] ?? $meta['short_label'] ?? '')
                : (string) ($meta['display_label'] ?? $meta['short_label'] ?? strtoupper(substr($department, 0, 3)));

            if (! array_key_exists($colaboradorId, $byColaboradorId)) {
                $byColaboradorId[$colaboradorId] = $this->buildCalendarAvatarData(
                    $assignment->colaborador,
                    $departmentLabel,
                );

                continue;
            }

            $titles = $byColaboradorId[$colaboradorId]['activity_titles'];

            if (! in_array($departmentLabel, $titles, true)) {
                $byColaboradorId[$colaboradorId]['activity_titles'][] = $departmentLabel;
            }
        }

        return collect($byColaboradorId)
            ->filter(fn (array $avatar): bool => filled($avatar['name']))
            ->sortBy(fn (array $avatar): string => Str::lower((string) $avatar['name']))
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TdgCalendarDepartmentColaboradorAssignment>  $assignments
     */
    private function countDepartmentColaboradorAssignmentsForFilter(Collection $assignments): int
    {
        return $assignments
            ->filter(function (mixed $assignment): bool {
                if (! $assignment instanceof TdgCalendarDepartmentColaboradorAssignment) {
                    return false;
                }

                $department = $assignment->department?->value ?? (string) $assignment->getRawOriginal('department');

                if ($this->agendaFilterDepartment !== '' && $department !== $this->agendaFilterDepartment) {
                    return false;
                }

                $systemsColaboradorId = $this->resolveAgendaFilterSystemsColaboradorId();

                if ($systemsColaboradorId !== null && (int) $assignment->rrhh_colaborador_id !== $systemsColaboradorId) {
                    return false;
                }

                return true;
            })
            ->count();
    }

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
