<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\User;
use App\Models\UserWorkAbsence;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\SharedNavigationGroups;
use App\Support\SecurityAudit;
use App\Support\UserActivity\UserActivityClock;
use App\Support\UserActivity\UserActivityLiveBoard;
use App\Support\UserActivity\UserActivityProfile;
use App\Support\UserActivity\UserActivityReport;
use App\Support\UserActivity\UserActivityState;
use App\Support\UserActivity\UserActivityTracker;
use App\Support\UserActivity\Workday\WorkAbsenceType;
use App\Support\UserActivity\Workday\WorkdayConfig;
use App\Support\UserActivity\Workday\WorkDayPlanner;
use App\Support\UserActivity\Workday\WorkdaySubjects;
use App\Support\UserActivity\Workday\WorkMinuteKind;
use App\Support\UserActivity\Workday\WorkScheduleType;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;
use Throwable;
use UnitEnum;

/**
 * Actividad de usuarios: cuánto y cómo usa cada persona el sistema.
 *
 * - «En vivo»: quién está activo, inactivo (sistema abierto sin tocarlo) o en
 *   otra pestaña, desde cuándo, y su día minuto a minuto. Refresca cada 5 s solo
 *   con la pestaña visible; lee Redis/caché, no tablas de negocio.
 * - «Reporte»: por rango de fechas, desde los resúmenes diarios.
 * - Detalle de una persona: barra del día, recorrido paso a paso y hábitos.
 *
 * - Jornada: uso sobre la jornada laboral, almuerzo declarado, horas extra y
 *   guardias. Cambiar el tipo de jornada o registrar ausencias pide el permiso
 *   `gestionar-jornadas-laborales`.
 *
 * Acceso por el permiso de menú `actividad-usuarios` (Negocios); SUPERADMIN siempre.
 */
class UserActivityMonitor extends Page
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $navigationLabel = 'Actividad de usuarios';

    protected static ?string $title = 'Actividad de usuarios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = SharedNavigationGroups::MONITORING;

    protected static ?int $navigationSort = 98;

    protected static ?string $slug = 'actividad-de-usuarios';

    protected string $view = 'filament.business.pages.user-activity-monitor';

    protected Width|string|null $maxContentWidth = Width::Full;

    #[Url(as: 'vista')]
    public string $tab = 'live';

    #[Url(as: 'estado')]
    public string $liveState = 'connected';

    #[Url(as: 'tipo')]
    public string $userType = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'desde')]
    public string $from = '';

    #[Url(as: 'hasta')]
    public string $to = '';

    #[Url(as: 'orden')]
    public string $sort = 'active';

    #[Url(as: 'dir')]
    public string $direction = 'desc';

    public bool $paused = false;

    public ?int $selectedUserId = null;

    public ?string $selectedDate = null;

    public function mount(): void
    {
        [$from, $to] = UserActivityReport::range($this->from ?: null, $this->to ?: null);
        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
        $this->tab = in_array($this->tab, ['live', 'report'], true) ? $this->tab : 'live';
    }

    /**
     * Encabezado con propósito y leyenda de estados. Estilos en línea y solo las
     * utilidades de color de texto que ya compila el tema (claro/oscuro).
     */
    public function getHeading(): string|Htmlable
    {
        $idleMinutes = intdiv(UserActivityTracker::idleAfterSeconds(), 60);
        $legend = [
            [UserActivityState::Active->color(), UserActivityState::Active->label(), 'Tecleó, movió el mouse, tocó o desplazó en los últimos '.$idleMinutes.' min', UserActivityState::Active->description()],
            [UserActivityState::Idle->color(), UserActivityState::Idle->label(), 'Sistema abierto sin tocarlo por más de '.$idleMinutes.' min', UserActivityState::Idle->description()],
            [UserActivityState::Background->color(), UserActivityState::Background->label(), 'Minimizado o en otra pestaña', UserActivityState::Background->description()],
            [UserActivityState::Offline->color(), UserActivityState::Offline->label(), 'Sin el sistema abierto', UserActivityState::Offline->description()],
        ];

        if (WorkdayConfig::enabled()) {
            $lunch = WorkdayConfig::lunchMinutes();
            $legend = [
                [WorkMinuteKind::WorkActive->color(), 'Jornada', UserActivityClock::formatMinute(WorkdayConfig::workStart()).' a '.UserActivityClock::formatMinute(WorkdayConfig::workEnd()).', lunes a viernes = 100 %', 'Internos y médicos. Uso de jornada = (activo + reuniones, en jornada) ÷ jornada transcurrida, sin el almuerzo declarado.'],
                [WorkMinuteKind::Lunch->color(), 'Almuerzo', $lunch.' min declarados con el botón «Salir a almorzar»', 'Se declara a cualquier hora de la jornada, una vez por día. Sin declararlo, esa hora cuenta como jornada.'],
                [WorkMinuteKind::Meeting->color(), 'Reunión', 'Declarada con su motivo: cuenta como trabajo', 'Con el botón «Reunión». Puede cerrar sesión mientras dura. Fuera de horario cuenta como horas extra.'],
                [WorkMinuteKind::LunchExcess->color(), 'Exceso', 'Más de '.$lunch.' min de almuerzo', WorkMinuteKind::LunchExcess->description()],
                [WorkMinuteKind::WorkAbsent->color(), 'Ausente', 'En su horario sin el sistema abierto', WorkMinuteKind::WorkAbsent->description()],
                [WorkMinuteKind::Overtime->color(), 'Horas extra', 'Fuera de horario, fin de semana o feriado', WorkMinuteKind::Overtime->description()],
                [WorkMinuteKind::GuardActive->color(), 'Guardia', 'Calendario TDG u Operaciones', 'La guardia mide presencia; la inactividad no se penaliza. El día después de una guardia nocturna es de descanso.'],
            ];
        }

        $chips = '';

        foreach ($legend as [$color, $label, $help, $description]) {
            $chips .= '<span title="'.e($description).'" style="display:inline-flex;align-items:center;gap:8px;padding:6px 12px;border-radius:999px;border:1px solid rgba(148,163,184,.35);background:rgba(148,163,184,.08);">'
                .'<span style="width:9px;height:9px;border-radius:999px;flex-shrink:0;background:'.$color.';box-shadow:0 0 0 3px '.$color.'26;"></span>'
                .'<span class="text-gray-900 dark:text-white" style="font-size:.8rem;font-weight:700;">'.e($label).'</span>'
                .'<span class="text-gray-600 dark:text-gray-300" style="font-size:.78rem;font-weight:500;">'.e($help).'</span>'
                .'</span>';
        }

        $icon = svg('heroicon-o-presentation-chart-line', '', ['style' => 'width:26px;height:26px;color:#fff;'])->toHtml();

        return new HtmlString(
            '<div style="display:flex;align-items:flex-start;gap:16px;padding:6px 0;">'
            .'<div style="width:52px;height:52px;flex-shrink:0;border-radius:16px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0ea5e9,#2563eb);box-shadow:0 10px 24px -8px rgba(37,99,235,.55);">'.$icon.'</div>'
            .'<div style="display:flex;flex-direction:column;gap:8px;min-width:0;flex:1;">'
            .'<span class="text-gray-600 dark:text-gray-300" style="font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">Negocios · Uso del sistema</span>'
            .'<span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white" style="line-height:1.15;">Actividad de usuarios</span>'
            .'<span class="text-gray-600 dark:text-gray-300" style="font-size:.95rem;font-weight:500;max-width:760px;">Cuánto usa cada persona IntegraCorp: quién está trabajando ahora y cómo repartió su día.</span>'
            .'<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:4px;">'.$chips.'</div>'
            .'<span class="text-gray-600 dark:text-gray-300" style="display:inline-flex;align-items:center;gap:6px;font-size:.78rem;font-weight:500;opacity:.85;">'
            .svg('heroicon-o-information-circle', '', ['style' => 'width:16px;height:16px;flex-shrink:0;', 'aria-hidden' => 'true'])->toHtml()
            .'Mide el uso del sistema, no toda la jornada: llamadas o trabajo fuera de IntegraCorp no se ven aquí.</span>'
            .'</div>'
            .'</div>'
        );
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'report' ? 'report' : 'live';
        $this->closeDetail();
    }

    public function filterState(string $state): void
    {
        $this->liveState = in_array($state, ['connected', 'active', 'idle', 'background', 'offline', 'lunch', 'meeting', 'absent', 'all'], true) ? $state : 'connected';
    }

    public static function canManageWorkdays(): bool
    {
        return WorkdayConfig::enabled() && BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::MANAGE_WORKDAYS);
    }

    public function editWorkScheduleAction(): Action
    {
        return Action::make('editWorkSchedule')
            ->label('Cambiar jornada')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            ->size('sm')
            ->visible(fn (): bool => static::canManageWorkdays())
            ->modalIcon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->modalHeading(fn (array $arguments): string => 'Tipo de jornada de '.(self::workdayUser($arguments)?->name ?? 'esta persona'))
            ->modalDescription('Define contra qué se mide su uso del sistema. El cambio aplica desde hoy; los días ya cerrados no se recalculan.')
            ->modalSubmitActionLabel('Guardar jornada')
            ->fillForm(fn (array $arguments): array => ['work_schedule' => WorkScheduleType::fromStored(self::workdayUser($arguments)?->getAttributes()['work_schedule'] ?? null)->value])
            ->form([
                Radio::make('work_schedule')
                    ->label('Jornada')
                    ->options(WorkScheduleType::options())
                    ->descriptions(collect(WorkScheduleType::cases())->mapWithKeys(fn (WorkScheduleType $type): array => [$type->value => $type->description()])->all())
                    ->in(array_keys(WorkScheduleType::options()))
                    ->required(),
            ])
            ->action(function (array $arguments, array $data): void {
                $user = self::workdayUser($arguments);

                if (! static::canManageWorkdays() || $user === null) {
                    Notification::make()->danger()->title('No se pudo cambiar la jornada')->body('La persona no tiene jornada o usted no tiene el permiso «Gestionar jornadas laborales».')->send();

                    return;
                }

                $type = WorkScheduleType::from((string) $data['work_schedule']);
                $before = WorkScheduleType::fromStored($user->getAttributes()['work_schedule'] ?? null);

                User::query()->whereKey($user->id)->update(['work_schedule' => $type === WorkScheduleType::Standard ? null : $type->value]);
                WorkDayPlanner::forget((int) $user->id);
                SecurityAudit::log('AUDIT_WORK_SCHEDULE_CHANGED', 'business.user-activity.work-schedule', ['user_id' => $user->id, 'from' => $before->value, 'to' => $type->value]);

                Notification::make()->success()->title('Jornada actualizada')->body($user->name.' ahora tiene jornada «'.$type->label().'».')->send();
            });
    }

    public function registerWorkAbsenceAction(): Action
    {
        return Action::make('registerWorkAbsence')
            ->label('Registrar ausencia')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('primary')
            ->size('sm')
            ->visible(fn (): bool => static::canManageWorkdays())
            ->modalIcon(Heroicon::OutlinedCalendarDays)
            ->modalHeading(fn (array $arguments): string => 'Registrar ausencia de '.(self::workdayUser($arguments)?->name ?? 'esta persona'))
            ->modalDescription('Esos días no tendrá jornada que cumplir: no contarán como ausencia ni bajarán su uso. Si trabaja en esos días, su uso se registra como horas extra.')
            ->modalSubmitActionLabel('Registrar ausencia')
            ->form([
                Select::make('type')
                    ->label('Motivo')
                    ->options(WorkAbsenceType::options())
                    ->in(array_keys(WorkAbsenceType::options()))
                    ->native(false)
                    ->required(),
                DatePicker::make('starts_on')
                    ->label('Desde')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(now()->toDateString())
                    ->required(),
                DatePicker::make('ends_on')
                    ->label('Hasta (incluido)')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(now()->toDateString())
                    ->afterOrEqual('starts_on')
                    ->rule(fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                        try {
                            $days = CarbonImmutable::parse((string) $get('starts_on'))->diffInDays(CarbonImmutable::parse((string) $value));
                        } catch (Throwable) {
                            return;
                        }

                        if ($days > 365) {
                            $fail('Una ausencia no puede pasar de un año. Regístrela por partes.');
                        }
                    })
                    ->required(),
                Textarea::make('reason')
                    ->label('Nota (opcional)')
                    ->placeholder('Por ejemplo: vacaciones del período 2025-2026.')
                    ->maxLength(255)
                    ->rows(2),
            ])
            ->action(function (array $arguments, array $data): void {
                $user = self::workdayUser($arguments);

                if (! static::canManageWorkdays() || $user === null) {
                    Notification::make()->danger()->title('No se pudo registrar la ausencia')->body('La persona no tiene jornada o usted no tiene el permiso «Gestionar jornadas laborales».')->send();

                    return;
                }

                $start = CarbonImmutable::parse((string) $data['starts_on'])->toDateString();
                $end = CarbonImmutable::parse((string) $data['ends_on'])->toDateString();

                $absence = DB::transaction(function () use ($user, $data, $start, $end): ?UserWorkAbsence {
                    $overlaps = UserWorkAbsence::query()
                        ->where('user_id', $user->id)
                        ->whereDate('starts_on', '<=', $end)
                        ->whereDate('ends_on', '>=', $start)
                        ->lockForUpdate()
                        ->exists();

                    if ($overlaps) {
                        return null;
                    }

                    return UserWorkAbsence::query()->create([
                        'user_id' => $user->id,
                        'type' => (string) $data['type'],
                        'starts_on' => $start,
                        'ends_on' => $end,
                        'reason' => filled($data['reason'] ?? null) ? trim((string) $data['reason']) : null,
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);
                });

                if ($absence === null) {
                    Notification::make()->warning()->title('Ya hay una ausencia en esas fechas')->body('Elimine o ajuste la ausencia existente antes de registrar otra que se cruce con ella.')->persistent()->send();

                    return;
                }

                WorkDayPlanner::forget((int) $user->id);
                SecurityAudit::log('AUDIT_WORK_ABSENCE_CREATED', 'business.user-activity.work-absence', ['user_id' => $user->id, 'absence_id' => $absence->id, 'type' => $absence->type->value, 'from' => $start, 'to' => $end]);

                Notification::make()->success()->title('Ausencia registrada')->body($absence->type->label().' de '.$user->name.' del '.$absence->starts_on->format('d/m/Y').' al '.$absence->ends_on->format('d/m/Y').'.')->send();
            });
    }

    public function deleteWorkAbsenceAction(): Action
    {
        return Action::make('deleteWorkAbsence')
            ->label('Quitar')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->size('xs')
            ->link()
            ->visible(fn (): bool => static::canManageWorkdays())
            ->requiresConfirmation()
            ->modalHeading('¿Quitar esta ausencia?')
            ->modalDescription('Esos días vuelven a contar como jornada laboral: si no se conectó, se verán como ausencia. Los días ya cerrados conservan sus cifras.')
            ->modalSubmitActionLabel('Sí, quitar ausencia')
            ->action(function (array $arguments): void {
                $absence = UserWorkAbsence::query()->find((int) ($arguments['absenceId'] ?? 0));

                if (! static::canManageWorkdays() || $absence === null) {
                    Notification::make()->warning()->title('La ausencia ya no existe')->send();

                    return;
                }

                $absence->delete();
                WorkDayPlanner::forget((int) $absence->user_id);
                SecurityAudit::log('AUDIT_WORK_ABSENCE_DELETED', 'business.user-activity.work-absence', ['user_id' => $absence->user_id, 'absence_id' => $absence->id, 'type' => $absence->type->value, 'from' => $absence->starts_on->toDateString(), 'to' => $absence->ends_on->toDateString()]);

                Notification::make()->success()->title('Ausencia eliminada')->send();
            });
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function workdayUser(array $arguments): ?User
    {
        $user = User::query()->find((int) ($arguments['userId'] ?? 0), WorkdaySubjects::columns());

        return WorkdaySubjects::isSubject($user) ? $user : null;
    }

    public function filterType(string $type): void
    {
        $this->userType = $type === 'all' || array_key_exists($type, UserActivityProfile::TYPES) ? $type : 'all';
    }

    public function togglePause(): void
    {
        $this->paused = ! $this->paused;
    }

    public function preset(string $preset): void
    {
        $today = UserActivityClock::now()->startOfDay();

        [$from, $to] = match ($preset) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            'week' => [$today->startOfWeek(), $today],
            'last_week' => [$today->subWeek()->startOfWeek(), $today->subWeek()->endOfWeek()->startOfDay()],
            'month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'last30' => [$today->subDays(29), $today],
            default => [$today->startOfWeek(), $today],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
    }

    public function updatedFrom(): void
    {
        $this->normalizeRange();
    }

    public function updatedTo(): void
    {
        $this->normalizeRange();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, UserActivityReport::SORTABLE, true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sort = $column;
        $this->direction = $column === 'name' || $column === 'avg_first' ? 'asc' : 'desc';
    }

    public function selectUser(int $userId, ?string $date = null): void
    {
        $this->selectedUserId = $userId > 0 ? $userId : null;
        $this->selectedDate = $this->validDate($date) ?? UserActivityClock::now()->toDateString();
    }

    public function shiftDay(int $days): void
    {
        if ($this->selectedUserId === null) {
            return;
        }

        $current = CarbonImmutable::parse($this->selectedDate ?? 'today', UserActivityClock::timezone());
        $next = $current->addDays(max(-1, min(1, $days)));

        if ($next->greaterThan(UserActivityClock::now()->endOfDay())) {
            return;
        }

        $this->selectedDate = $next->toDateString();
    }

    public function closeDetail(): void
    {
        $this->selectedUserId = null;
        $this->selectedDate = null;
    }

    public function exportUrl(): string
    {
        return route('business.user-activity.export', [
            'desde' => $this->from,
            'hasta' => $this->to,
            'tipo' => $this->userType,
            'q' => $this->search,
            'orden' => $this->sort,
            'dir' => $this->direction,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        [$from, $to] = UserActivityReport::range($this->from, $this->to);

        $data = [
            'states' => UserActivityState::cases(),
            'workdayEnabled' => WorkdayConfig::enabled(),
            'types' => UserActivityProfile::TYPES,
            'idleMinutes' => intdiv(UserActivityTracker::idleAfterSeconds(), 60),
            'refreshedAt' => UserActivityClock::now()->format('h:i:s a'),
            'rangeLabel' => $from->isSameDay($to) ? $from->translatedFormat('d M Y') : $from->translatedFormat('d M').' – '.$to->translatedFormat('d M Y'),
            'live' => null,
            'report' => null,
            'detail' => null,
        ];

        if ($this->tab === 'live') {
            $data['live'] = UserActivityLiveBoard::build([
                'state' => $this->liveState,
                'type' => $this->userType,
                'search' => $this->search,
            ]);
        } else {
            $data['report'] = UserActivityReport::summary($from, $to, [
                'type' => $this->userType,
                'search' => $this->search,
                'sort' => $this->sort,
                'direction' => $this->direction,
            ]);
        }

        if ($this->selectedUserId !== null) {
            $data['detail'] = $this->detail($from, $to);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function detail(CarbonImmutable $from, CarbonImmutable $to): ?array
    {
        $user = User::query()->find($this->selectedUserId, UserActivityProfile::COLUMNS);

        if ($user === null) {
            return null;
        }

        $date = CarbonImmutable::parse($this->validDate($this->selectedDate) ?? 'today', UserActivityClock::timezone());

        $subject = WorkdayConfig::enabled() && WorkdaySubjects::isSubject(User::query()->find($user->id, WorkdaySubjects::columns()));

        return [
            'user' => $user,
            'profile' => UserActivityProfile::classify($user),
            'day' => UserActivityReport::day($user->id, $date),
            'person' => $this->tab === 'report' ? UserActivityReport::person($user->id, $from, $to) : null,
            'is_latest_day' => $date->isSameDay(UserActivityClock::now()),
            'subject' => $subject,
            'schedule' => $subject ? WorkScheduleType::fromStored(User::query()->whereKey($user->id)->value('work_schedule')) : null,
            'absences' => $subject
                ? UserWorkAbsence::query()
                    ->where('user_id', $user->id)
                    ->whereDate('ends_on', '>=', UserActivityClock::now()->subDays(30)->toDateString())
                    ->orderBy('starts_on')
                    ->limit(12)
                    ->get()
                : collect(),
            'can_manage' => $subject && static::canManageWorkdays(),
        ];
    }

    private function normalizeRange(): void
    {
        [$from, $to] = UserActivityReport::range($this->from, $this->to);
        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
    }

    private function validDate(?string $date): ?string
    {
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('Y-m-d', $date, UserActivityClock::timezone())->startOfDay();
        } catch (Throwable) {
            return null;
        }

        return $parsed->greaterThan(UserActivityClock::now()) ? null : $parsed->toDateString();
    }
}
