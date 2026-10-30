<?php

declare(strict_types=1);

namespace App\Filament\Operations\Pages;

use App\Models\MedicalGuard;
use App\Models\MedicalGuardSite;
use App\Models\User;
use App\Support\Filament\SharedNavigationGroups;
use App\Support\Operations\MedicalGuards\MedicalGuardAccess;
use App\Support\Operations\MedicalGuards\MedicalGuardCompliance;
use App\Support\Operations\MedicalGuards\MedicalGuardException;
use App\Support\Operations\MedicalGuards\MedicalGuardScheduler;
use App\Support\UserActivity\UserActivityClock;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Calendario de guardias de los médicos de TDG por sede: se marcan los días,
 * se elige médico y horario, y se confirma viendo qué se va a crear.
 */
class GuardiasMedicas extends Page
{
    protected static ?string $navigationLabel = 'Guardias Médicas';

    protected static ?string $title = 'Guardias Médicas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|UnitEnum|null $navigationGroup = SharedNavigationGroups::USER;

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.operations.pages.guardias-medicas';

    protected Width|string|null $maxContentWidth = Width::Full;

    public string $month = '';

    public ?int $siteId = null;

    /** @var list<string> */
    public array $selectedDates = [];

    public static function canAccess(): bool
    {
        return MedicalGuardAccess::canView(self::currentUser());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }

    public function mount(): void
    {
        $this->month = UserActivityClock::now()->format('Y-m');
        $this->siteId = $this->sites()->first()?->id;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Horario y médicos por sede. Estas guardias son la jornada que mide Actividad de usuarios.';
    }

    public function canManageGuards(): bool
    {
        return MedicalGuardAccess::canManage(self::currentUser());
    }

    public function selectSite(int $siteId): void
    {
        if ($this->sites()->contains('id', $siteId)) {
            $this->siteId = $siteId;
            $this->selectedDates = [];
        }
    }

    public function changeMonth(int $offset): void
    {
        $this->month = $this->monthStart()->addMonthsNoOverflow($offset)->format('Y-m');
    }

    public function goToCurrentMonth(): void
    {
        $this->month = UserActivityClock::now()->format('Y-m');
    }

    public function loadGuardsAction(): Action
    {
        return Action::make('loadGuards')
            ->label('Cargar guardias')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->color('primary')
            ->visible(fn (): bool => $this->canManageGuards())
            ->modalHeading(fn (): string => 'Cargar guardias en '.$this->currentSiteName())
            ->modalDescription(fn (): string => $this->selectedDatesLabel())
            ->modalSubmitActionLabel('Confirmar y guardar')
            ->modalWidth(Width::Large)
            ->schema([
                Select::make('doctor_ids')
                    ->label('Médicos')
                    ->helperText('Elige uno o varios: todos quedan de guardia en esos días y ese horario.')
                    ->options(fn (): array => MedicalGuardScheduler::doctorOptions())
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->live(),
                ...$this->scheduleFields(),
                TextInput::make('notes')
                    ->label('Nota (opcional)')
                    ->maxLength(255),
                Placeholder::make('preview')
                    ->hiddenLabel()
                    ->content(fn (Get $get): ?HtmlString => $this->previewContent($get)),
            ])
            ->action(function (array $data, Action $action): void {
                $this->abortUnlessCanManage();

                try {
                    $created = MedicalGuardScheduler::createForDoctors(
                        (int) $this->siteId,
                        array_map(intval(...), (array) $data['doctor_ids']),
                        $this->selectedDates,
                        (string) $data['start'],
                        (string) $data['end'],
                        $data['notes'] ?? null,
                        self::currentUser(),
                    );
                } catch (MedicalGuardException $exception) {
                    $this->notifyProblem($exception->getMessage());
                    $action->halt();

                    return;
                }

                $this->selectedDates = [];

                Notification::make()
                    ->title($created->count() === 1 ? 'Guardia cargada' : $created->count().' guardias cargadas')
                    ->success()
                    ->send();
            });
    }

    public function editGuardAction(): Action
    {
        return Action::make('editGuard')
            ->label('Editar guardia')
            ->visible(fn (): bool => $this->canManageGuards())
            ->modalHeading('Editar guardia')
            ->modalSubmitActionLabel('Guardar cambios')
            ->modalWidth(Width::Large)
            ->fillForm(function (array $arguments): array {
                $guard = MedicalGuard::query()->find($arguments['guard'] ?? 0);

                if ($guard === null) {
                    return [];
                }

                return [
                    'site_id' => $guard->medical_guard_site_id,
                    'doctor_id' => $guard->telemedicine_doctor_id,
                    'date' => $guard->guard_date->toDateString(),
                    'start' => $guard->starts_at->format('H:i'),
                    'end' => $guard->ends_at->format('H:i'),
                    'notes' => $guard->notes,
                ];
            })
            ->schema(fn (array $arguments): array => [
                Grid::make(2)->schema([
                    Select::make('site_id')
                        ->label('Sede')
                        ->options(fn (): array => $this->sites()->pluck('name', 'id')->all())
                        ->required(),
                    DatePicker::make('date')
                        ->label('Fecha de inicio')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->live(),
                ]),
                Select::make('doctor_id')
                    ->label('Médico')
                    ->options(fn (): array => MedicalGuardScheduler::doctorOptions())
                    ->searchable()
                    ->required()
                    ->live(),
                ...$this->scheduleFields(),
                TextInput::make('notes')
                    ->label('Nota (opcional)')
                    ->maxLength(255),
                Placeholder::make('preview')
                    ->hiddenLabel()
                    ->content(fn (Get $get): ?HtmlString => $this->previewContent($get, (int) ($arguments['guard'] ?? 0))),
            ])
            ->action(function (array $data, array $arguments, Action $action): void {
                $this->abortUnlessCanManage();

                $guard = MedicalGuard::query()->find($arguments['guard'] ?? 0);

                if ($guard === null) {
                    $this->notifyProblem('Esa guardia ya no existe. Actualiza la página.');

                    return;
                }

                try {
                    MedicalGuardScheduler::update(
                        $guard,
                        (int) $data['site_id'],
                        (int) $data['doctor_id'],
                        (string) $data['date'],
                        (string) $data['start'],
                        (string) $data['end'],
                        $data['notes'] ?? null,
                        self::currentUser(),
                    );
                } catch (MedicalGuardException $exception) {
                    $this->notifyProblem($exception->getMessage());
                    $action->halt();

                    return;
                }

                Notification::make()->title('Guardia actualizada')->success()->send();
            });
    }

    public function cancelGuardAction(): Action
    {
        return Action::make('cancelGuard')
            ->label('Anular guardia')
            ->color('danger')
            ->visible(fn (): bool => $this->canManageGuards())
            ->requiresConfirmation()
            ->modalHeading('¿Anular esta guardia?')
            ->modalDescription(function (array $arguments): string {
                $guard = MedicalGuard::query()->with('doctor:id,full_name')->find($arguments['guard'] ?? 0);

                return $guard === null
                    ? 'Esa guardia ya no existe.'
                    : trim((string) $guard->doctor?->full_name).', '.$guard->guard_date->format('d/m/Y').', '.$guard->scheduleLabel().'. Saldrá del calendario y dejará de contar como su jornada en Actividad de usuarios.';
            })
            ->modalSubmitActionLabel('Sí, anular')
            ->action(function (array $arguments): void {
                $this->abortUnlessCanManage();

                $guard = MedicalGuard::query()->find($arguments['guard'] ?? 0);

                if ($guard === null) {
                    $this->notifyProblem('Esa guardia ya no existe. Actualiza la página.');

                    return;
                }

                MedicalGuardScheduler::cancel($guard, self::currentUser());

                Notification::make()->title('Guardia anulada')->success()->send();
            });
    }

    public function copyWeekAction(): Action
    {
        return Action::make('copyWeek')
            ->label('Copiar una semana')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->visible(fn (): bool => $this->canManageGuards())
            ->modalHeading(fn (): string => 'Copiar una semana en '.$this->currentSiteName())
            ->modalDescription('Repite las guardias de esa semana, con los mismos médicos y horarios, en las semanas siguientes. Lo que choque con otra guardia se salta y te lo avisamos.')
            ->modalSubmitActionLabel('Copiar')
            ->modalWidth(Width::Medium)
            ->schema([
                DatePicker::make('source_date')
                    ->label('Cualquier día de la semana a copiar')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(fn (): string => UserActivityClock::now()->toDateString())
                    ->required(),
                Select::make('weeks')
                    ->label('Repetir en')
                    ->options(fn (): array => collect(range(1, 8))->mapWithKeys(fn (int $week): array => [$week => $week === 1 ? 'La semana siguiente' : 'Las '.$week.' semanas siguientes'])->all())
                    ->default(1)
                    ->required(),
            ])
            ->action(function (array $data, Action $action): void {
                $this->abortUnlessCanManage();

                try {
                    $result = MedicalGuardScheduler::copyWeek((int) $this->siteId, (string) $data['source_date'], (int) $data['weeks'], self::currentUser());
                } catch (MedicalGuardException $exception) {
                    $this->notifyProblem($exception->getMessage());
                    $action->halt();

                    return;
                }

                $notification = Notification::make()->title($result['created'].' guardias copiadas');

                if ($result['skipped'] !== []) {
                    $notification->warning()->persistent()->body(count($result['skipped']).' no se copiaron: '.implode('; ', array_slice($result['skipped'], 0, 5)).(count($result['skipped']) > 5 ? '…' : '.'));
                } else {
                    $notification->success();
                }

                $notification->send();
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $monthStart = $this->monthStart();
        $gridStart = $monthStart->startOfWeek();
        $gridEnd = $monthStart->endOfMonth()->endOfWeek()->startOfDay();
        $guards = MedicalGuardScheduler::between($gridStart, $gridEnd, $this->siteId);
        $compliance = MedicalGuardCompliance::evaluate($guards);
        $today = UserActivityClock::now()->toDateString();
        $byDate = $guards->groupBy(static fn (MedicalGuard $guard): string => $guard->guard_date->toDateString());
        $days = [];

        for ($day = $gridStart; $day->lessThanOrEqualTo($gridEnd); $day = $day->addDay()) {
            $date = $day->toDateString();
            $days[] = [
                'date' => $date,
                'number' => $day->day,
                'in_month' => $day->month === $monthStart->month,
                'is_today' => $date === $today,
                'guards' => ($byDate[$date] ?? collect())->map(fn (MedicalGuard $guard): array => $this->guardCard($guard, $compliance[$guard->id] ?? null))->all(),
            ];
        }

        return [
            'sites' => $this->sites(),
            'siteName' => $this->currentSiteName(),
            'monthLabel' => ucfirst($monthStart->locale('es')->translatedFormat('F Y')),
            'days' => $days,
            'canManage' => $this->canManageGuards(),
            'hasDoctors' => MedicalGuardScheduler::doctorOptions() !== [],
            'summary' => $this->summary($guards->filter(fn (MedicalGuard $guard): bool => $guard->guard_date->format('Y-m') === $this->month), $compliance),
            'pdfUrl' => route('operations.medical-guards.pdf', ['mes' => $this->month]),
        ];
    }

    /**
     * Inicio, fin y botones rápidos, compartidos por cargar y editar.
     *
     * @return list<ToggleButtons|Grid>
     */
    private function scheduleFields(): array
    {
        return [
            ToggleButtons::make('schedule_preset')
                ->label('Horarios ya usados en esta sede')
                ->options(fn (): array => $this->siteId === null ? [] : MedicalGuardScheduler::recentSchedules($this->siteId))
                ->visible(fn (): bool => $this->siteId !== null && MedicalGuardScheduler::recentSchedules($this->siteId) !== [])
                ->dehydrated(false)
                ->inline()
                ->live()
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    if ($state !== null && str_contains($state, '|')) {
                        [$start, $end] = explode('|', $state);
                        $set('start', $start);
                        $set('end', $end);
                    }
                }),
            Grid::make(2)->schema([
                TimePicker::make('start')
                    ->label('Hora de inicio')
                    ->seconds(false)
                    ->format('H:i')
                    ->required()
                    ->live(),
                TimePicker::make('end')
                    ->label('Hora de fin')
                    ->seconds(false)
                    ->format('H:i')
                    ->helperText('Si es igual o anterior al inicio, termina al día siguiente.')
                    ->required()
                    ->live(),
            ]),
        ];
    }

    private function previewContent(Get $get, int $ignoreGuardId = 0): ?HtmlString
    {
        $doctorIds = $ignoreGuardId > 0
            ? array_filter([(int) $get('doctor_id')])
            : array_map(intval(...), (array) $get('doctor_ids'));
        $start = (string) $get('start');
        $end = (string) $get('end');
        $dates = array_values(array_map(
            static fn (string $date): string => substr($date, 0, 10),
            $ignoreGuardId > 0 ? [(string) $get('date')] : $this->selectedDates,
        ));

        if ($doctorIds === [] || $start === '' || $end === '') {
            return null;
        }

        try {
            $preview = $ignoreGuardId > 0
                ? MedicalGuardScheduler::preview($doctorIds[0], $dates, $start, $end, $ignoreGuardId)
                : MedicalGuardScheduler::previewForDoctors($doctorIds, $dates, $start, $end);
        } catch (MedicalGuardException $exception) {
            return new HtmlString('<span style="color:#dc2626">'.e($exception->getMessage()).'</span>');
        }

        $hours = round($preview['minutes'] / 60, 1);
        $summary = (count($doctorIds) > 1 ? count($doctorIds).' médicos · ' : '')
            .($preview['count'] === 1 ? '1 guardia' : $preview['count'].' guardias').' · '.$hours.' h · '.MedicalGuardScheduler::scheduleLabel($start, $end);

        if ($preview['conflicts'] === []) {
            return new HtmlString('<strong>'.e($summary).'</strong>');
        }

        return new HtmlString('<strong>'.e($summary).'</strong><div style="color:#dc2626;margin-top:.35rem">'.implode('<br>', array_map(e(...), $preview['conflicts'])).'</div>');
    }

    /**
     * @param  array{state: string, percent: int|null}|null  $result
     * @return array<string, mixed>
     */
    private function guardCard(MedicalGuard $guard, ?array $result): array
    {
        $state = $result['state'] ?? MedicalGuardCompliance::SCHEDULED;
        $description = MedicalGuardCompliance::describe($state);

        return [
            'id' => $guard->id,
            'doctor' => trim((string) $guard->doctor?->full_name),
            'schedule' => $guard->scheduleLabel(),
            'notes' => (string) $guard->notes,
            'color' => $description['color'],
            'status' => $description['label'].(($result['percent'] ?? null) !== null ? ' ('.$result['percent'].' %)' : ''),
        ];
    }

    /**
     * Una fila por médico con lo que hizo en el mes.
     *
     * @param  Collection<int, MedicalGuard>  $guards
     * @param  array<int, array{state: string, percent: int|null}>  $compliance
     * @return list<array{doctor: string, guards: int, hours: float, active: int, partial: int, none: int}>
     */
    private function summary(Collection $guards, array $compliance): array
    {
        return $guards
            ->groupBy('telemedicine_doctor_id')
            ->map(static function (Collection $rows) use ($compliance): array {
                $count = static fn (string $state): int => $rows->filter(static fn (MedicalGuard $guard): bool => ($compliance[$guard->id]['state'] ?? null) === $state)->count();

                return [
                    'doctor' => trim((string) $rows->first()->doctor?->full_name),
                    'guards' => $rows->count(),
                    'hours' => round($rows->sum(static fn (MedicalGuard $guard): int => $guard->durationMinutes()) / 60, 1),
                    'active' => $count(MedicalGuardCompliance::ACTIVE),
                    'partial' => $count(MedicalGuardCompliance::PARTIAL),
                    'none' => $count(MedicalGuardCompliance::NONE),
                ];
            })
            ->sortBy('doctor')
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, MedicalGuardSite>
     */
    private function sites(): \Illuminate\Database\Eloquent\Collection
    {
        return MedicalGuardSite::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);
    }

    private function currentSiteName(): string
    {
        return (string) $this->sites()->firstWhere('id', $this->siteId)?->name;
    }

    private function selectedDatesLabel(): string
    {
        $count = count($this->selectedDates);

        return match ($count) {
            0 => 'Marca primero los días en el calendario.',
            1 => '1 día seleccionado.',
            default => $count.' días seleccionados.',
        };
    }

    private function monthStart(): CarbonImmutable
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month) === 1
            ? CarbonImmutable::createFromFormat('!Y-m', $this->month)
            : UserActivityClock::now()->startOfMonth()->startOfDay();
    }

    private function abortUnlessCanManage(): void
    {
        abort_unless($this->canManageGuards(), 403);
    }

    private function notifyProblem(string $message): void
    {
        Notification::make()->title('No se pudo guardar')->body($message)->danger()->persistent()->send();
    }

    private static function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
