<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Support\LivePresence\ActivityContext;
use App\Support\LivePresence\LiveActivitySnapshot;
use App\Support\LivePresence\LivePresenceAccess;
use App\Support\LivePresence\LivePresenceStore;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Monitor en vivo de la actividad de los usuarios: solo para el desarrollador.
 *
 * Cada refresco (3 s, y solo con la pestaña visible) es una lectura a Redis;
 * la salud del sistema se cachea 10 s. No consulta tablas de negocio.
 */
class LiveActivityMonitor extends Page
{
    protected static ?string $navigationLabel = 'Monitor en vivo';

    protected static ?string $title = 'Monitor en vivo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?int $navigationSort = 99;

    protected static ?string $slug = 'monitor-en-vivo';

    protected string $view = 'filament.business.pages.live-activity-monitor';

    #[Url(as: 'panel')]
    public string $panelFilter = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    public ?string $selectedSession = null;

    public bool $paused = false;

    public static function canAccess(): bool
    {
        return LivePresenceAccess::allows(Auth::user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * Se revalida en cada petición de Livewire, no solo al entrar.
     */
    public function boot(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function filterPanel(string $panel): void
    {
        $this->panelFilter = $panel === 'all' || array_key_exists($panel, ActivityContext::PANELS) || $panel === 'web' ? $panel : 'all';
    }

    public function selectSession(string $sessionKey): void
    {
        $this->selectedSession = preg_match('/^[a-f0-9]{24}$/', $sessionKey) === 1 ? $sessionKey : null;
    }

    public function closeDetail(): void
    {
        $this->selectedSession = null;
    }

    public function togglePause(): void
    {
        $this->paused = ! $this->paused;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $all = LiveActivitySnapshot::sessions();
        $kpis = LiveActivitySnapshot::kpis($all);
        $sessions = $this->applyFilters($all);
        $selected = $this->selectedSession === null
            ? null
            : collect($all)->firstWhere('session_key', $this->selectedSession);

        return [
            'sessions' => $sessions,
            'kpis' => $kpis,
            'health' => LiveActivitySnapshot::systemHealth(),
            'panelLabels' => ActivityContext::PANELS + ['web' => 'Sitio web'],
            'selected' => $selected,
            'selectedTimeline' => $selected === null ? [] : $this->timelineFor((int) $selected['user_id']),
            'selectedSiblings' => $selected === null
                ? []
                : array_values(array_filter($all, fn (array $session): bool => $session['user_id'] === $selected['user_id'] && $session['session_key'] !== $selected['session_key'])),
            'refreshedAt' => now()->format('H:i:s'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $sessions
     * @return list<array<string, mixed>>
     */
    private function applyFilters(array $sessions): array
    {
        $search = mb_strtolower(trim($this->search));

        return array_values(array_filter($sessions, function (array $session) use ($search): bool {
            if ($this->panelFilter !== 'all' && $session['panel'] !== $this->panelFilter) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = mb_strtolower(implode(' ', [
                $session['user_name'], $session['user_email'], $session['ip'], $session['location'],
                $session['page_label'], $session['last_action'], $session['browser'], $session['os'],
            ]));

            return str_contains($haystack, $search);
        }));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function timelineFor(int $userId): array
    {
        try {
            $events = LivePresenceStore::repository()->timeline($userId, 50);
        } catch (Throwable) {
            return [];
        }

        $now = time();

        return array_map(static fn (array $event): array => [
            ...$event,
            'ago' => LiveActivitySnapshot::ago($now - (int) ($event['at'] ?? $now)),
            'time' => date('H:i:s', (int) ($event['at'] ?? $now)),
        ], $events);
    }
}
