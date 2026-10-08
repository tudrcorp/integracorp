<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\User;
use App\Support\UserActivity\UserActivityClock;
use App\Support\UserActivity\UserActivityLiveBoard;
use App\Support\UserActivity\UserActivityProfile;
use App\Support\UserActivity\UserActivityReport;
use App\Support\UserActivity\UserActivityState;
use App\Support\UserActivity\UserActivityTracker;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Actividad de usuarios: cuánto y cómo usa cada persona el sistema.
 *
 * - «En vivo»: quién está activo, inactivo (sistema abierto sin tocarlo) o en
 *   otra pestaña, desde cuándo, y su día minuto a minuto. Refresca cada 5 s solo
 *   con la pestaña visible; lee Redis/caché, no tablas de negocio.
 * - «Reporte»: por rango de fechas, desde los resúmenes diarios.
 * - Detalle de una persona: barra del día, recorrido paso a paso y hábitos.
 *
 * Acceso por el permiso de menú `actividad-usuarios` (Negocios); SUPERADMIN siempre.
 */
class UserActivityMonitor extends Page
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $navigationLabel = 'Actividad de usuarios';

    protected static ?string $title = 'Actividad de usuarios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

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

    public function getSubheading(): ?string
    {
        return 'Activo = tecleó, movió el mouse, tocó o desplazó la pantalla en los últimos '
            .intdiv(UserActivityTracker::idleAfterSeconds(), 60)
            .' min. Mide el uso del sistema, no toda la jornada: llamadas o trabajo fuera de IntegraCorp no se ven aquí.';
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'report' ? 'report' : 'live';
        $this->closeDetail();
    }

    public function filterState(string $state): void
    {
        $this->liveState = in_array($state, ['connected', 'active', 'idle', 'background', 'offline', 'all'], true) ? $state : 'connected';
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

        return [
            'user' => $user,
            'profile' => UserActivityProfile::classify($user),
            'day' => UserActivityReport::day($user->id, $date),
            'person' => $this->tab === 'report' ? UserActivityReport::person($user->id, $from, $to) : null,
            'is_latest_day' => $date->isSameDay(UserActivityClock::now()),
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
