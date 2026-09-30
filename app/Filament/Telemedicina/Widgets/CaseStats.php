<?php

namespace App\Filament\Telemedicina\Widgets;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use App\Support\Telemedicine\TelemedicineCaseDerivedService;
use App\Support\Telemedicine\TelemedicineCaseFilamentListQuery;
use App\Support\Telemedicine\TelemedicineCaseFollowUpSchedule;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

/**
 * Tarjetas del escritorio médico. Cuentan sobre los mismos casos que muestra
 * la tabla del escritorio, así el médico valida los números de primera mano;
 * las tarjetas de estado filtran esa tabla al pulsarlas.
 */
class CaseStats extends StatsOverviewWidget
{
    public const FILTER_EVENT = 'telemedicine-dashboard-filter';

    public const FILTER_ASSIGNED = 'assigned';

    public const FILTER_FOLLOW_UP = 'follow_up';

    public const FILTER_OVERDUE = 'overdue';

    public const FILTER_AMD_PENDING = 'amd_pending';

    /**
     * Filtro activo en la tabla, para resaltar la tarjeta correspondiente.
     */
    public ?string $activeFilter = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $description = 'Toque una tarjeta de estado para ver sólo esos casos en la tabla.';

    /**
     * Los médicos TDG ven en la tabla los casos de todo el equipo TDG; las
     * tarjetas cuentan lo mismo, así que el título lo dice.
     */
    protected function getHeading(): ?string
    {
        return TelemedicineCaseFilamentListQuery::userIsInTdgTelemedicinaContext(Auth::user())
            ? 'Casos del equipo TDG'
            : 'Mis casos';
    }

    #[On(self::FILTER_EVENT)]
    public function syncActiveFilter(?string $filter = null): void
    {
        $this->activeFilter = $filter;
    }

    public function getSectionContentComponent(): Component
    {
        return Section::make()
            ->heading($this->getHeading())
            ->description($this->getDescription())
            ->schema($this->getCachedStats())
            ->columns($this->getColumns())
            ->contained(false)
            ->gridContainer()
            ->extraAttributes([
                'class' => 'fi-telemedicine-case-stats-ios',
            ]);
    }

    protected function getStats(): array
    {
        return array_values(array_filter([
            $this->filterStat('CASOS ASIGNADOS', $this->countAssigned(), 'Pendientes de primera consulta', 'heroicon-m-user-plus', 'warning', 'fi-telemedicine-case-stat-ios--assigned', self::FILTER_ASSIGNED),
            $this->filterStat('EN SEGUIMIENTO', $this->countFollowUp(), 'Casos activos en control', 'heroicon-m-arrow-path', 'info', 'fi-telemedicine-case-stat-ios--followup', self::FILTER_FOLLOW_UP),
            $this->filterStat('SEGUIMIENTOS VENCIDOS', $this->countOverdue(), 'Su próximo seguimiento ya pasó', 'heroicon-m-clock', 'danger', 'fi-telemedicine-case-stat-ios--overdue', self::FILTER_OVERDUE),
            $this->filterStat('AMD POR REALIZAR', $this->countAmdPending(), 'Asistencias médicas domiciliarias pendientes', 'heroicon-m-home-modern', 'primary', 'fi-telemedicine-case-stat-ios--amd', self::FILTER_AMD_PENDING),
            $this->plainStat('ALTAS MÉDICAS', $this->countDischarged(), 'Casos cerrados', 'heroicon-m-check-badge', 'success', 'fi-telemedicine-case-stat-ios--discharge'),
            $this->userDoctorBelongsToSupplier()
                ? $this->plainStat('TRASLADOS EN AMBULANCIA', $this->countAmbulanceTransfers(), 'Acompañamientos en traslado', 'heroicon-m-truck', 'danger', 'fi-telemedicine-case-stat-ios--transport-ambulance')
                : null,
        ]));
    }

    public function getColumns(): int|array
    {
        return $this->userDoctorBelongsToSupplier()
            ? ['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 6]
            : ['default' => 1, 'sm' => 2, 'lg' => 5];
    }

    public function countAssigned(): int
    {
        return $this->scopedQuery()->where('status', 'ASIGNADO')->count();
    }

    public function countFollowUp(): int
    {
        return $this->scopedQuery()->where('status', 'EN SEGUIMIENTO')->count();
    }

    public function countOverdue(): int
    {
        return TelemedicineCaseFollowUpSchedule::whereOverdue($this->scopedQuery())->count();
    }

    /**
     * Casos cuya última consulta dejó AMD como derivado: el médico debe
     * realizar la asistencia domiciliaria. La tabla usa la misma regla.
     */
    public function countAmdPending(): int
    {
        return TelemedicineCaseDerivedService::whereAmdPending($this->scopedQuery())->count();
    }

    public function countDischarged(): int
    {
        return $this->scopedQuery(includeDischarged: true)->where('status', 'ALTA MEDICA')->count();
    }

    public function countAmbulanceTransfers(): int
    {
        $doctorId = $this->currentDoctorId();

        if ($doctorId === null) {
            return 0;
        }

        return TelemedicineCase::query()->where('doctor_id_first_accompaniment', $doctorId)->count();
    }

    /**
     * Médico de un proveedor: tiene proveedor asignado o lo gestiona ATENMEDI.
     */
    public function userDoctorBelongsToSupplier(): bool
    {
        $doctor = $this->currentTelemedicineDoctor();

        // managed_by guarda el nombre completo («CORPORACION VMC, C.A. (ATENMEDI)»), no sólo «ATENMEDI».
        return $doctor !== null
            && ($doctor->supplier_id !== null || str_contains(mb_strtoupper((string) $doctor->managed_by), 'ATENMEDI'));
    }

    /**
     * @return Builder<TelemedicineCase>
     */
    private function scopedQuery(bool $includeDischarged = false): Builder
    {
        return TelemedicineCaseFilamentListQuery::applyDashboardScope(TelemedicineCase::query(), $includeDischarged)
            ->setEagerLoads([]);
    }

    private function filterStat(string $label, int $value, string $description, string $icon, string $color, string $toneClass, string $filter): Stat
    {
        $isActive = $this->activeFilter === $filter;

        return Stat::make($label, $value)
            ->description($isActive ? 'Filtrando la tabla · toque para ver todos' : $description)
            ->descriptionIcon($icon)
            ->color($color)
            ->extraAttributes([
                'class' => implode(' ', array_filter([
                    'fi-telemedicine-case-stat-ios',
                    $toneClass,
                    $isActive ? 'fi-telemedicine-case-stat-ios--active' : null,
                    'cursor-pointer transition-[transform,box-shadow] duration-200 active:scale-[0.98]',
                ])),
                'role' => 'button',
                'aria-pressed' => $isActive ? 'true' : 'false',
                'wire:click' => "\$dispatch('".self::FILTER_EVENT."', { filter: ".($isActive ? 'null' : "'".$filter."'").' })',
            ]);
    }

    private function plainStat(string $label, int $value, string $description, string $icon, string $color, string $toneClass): Stat
    {
        return Stat::make($label, $value)
            ->description($description)
            ->descriptionIcon($icon)
            ->color($color)
            ->extraAttributes([
                'class' => 'fi-telemedicine-case-stat-ios '.$toneClass,
            ]);
    }

    private function currentDoctorId(): ?int
    {
        $id = Auth::user()?->doctor_id;

        return $id !== null ? (int) $id : null;
    }

    private ?TelemedicineDoctor $resolvedDoctor = null;

    private bool $doctorResolved = false;

    private function currentTelemedicineDoctor(): ?TelemedicineDoctor
    {
        if (! $this->doctorResolved) {
            $doctorId = $this->currentDoctorId();
            $this->resolvedDoctor = $doctorId === null ? null : TelemedicineDoctor::query()->find($doctorId);
            $this->doctorResolved = true;
        }

        return $this->resolvedDoctor;
    }
}
