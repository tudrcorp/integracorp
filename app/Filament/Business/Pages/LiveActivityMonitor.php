<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Models\SecurityUserBlock;
use App\Models\User;
use App\Support\LivePresence\ActivityContext;
use App\Support\LivePresence\LiveActivitySnapshot;
use App\Support\LivePresence\LivePresenceAccess;
use App\Support\LivePresence\LivePresenceStore;
use App\Support\LivePresence\SecurityMonitor;
use App\Support\LivePresence\SecuritySnapshot;
use App\Support\LivePresence\UserBlockList;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
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
        $blocks = UserBlockList::active();
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
            'selectedCanBeBlocked' => $selected !== null && ($target = User::query()->find((int) $selected['user_id'])) !== null
                && UserBlockList::restrictionFor($target) === null,
            'security' => SecuritySnapshot::build(),
            'blocks' => $blocks,
            'blockedIds' => array_map(static fn (SecurityUserBlock $block): int => (int) $block->user_id, $blocks),
        ];
    }

    /**
     * Bloquear a un usuario: sale de todos los paneles y de la PWA en su próxima petición.
     */
    public function blockUserAction(): Action
    {
        return Action::make('blockUser')
            ->label('Bloquear usuario')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedNoSymbol)
            ->modalHeading(fn (array $arguments): string => 'Bloquear a '.(User::query()->find((int) ($arguments['userId'] ?? 0))?->name ?? 'este usuario'))
            ->modalDescription('Pierde el acceso a todos los paneles y a la PWA en su próxima petición, aunque tenga la sesión abierta. No se modifica su cuenta ni sus datos. Queda registrado quién lo bloqueó y por qué.')
            ->modalSubmitActionLabel('Bloquear')
            ->form([
                Select::make('duration')
                    ->label('Duración')
                    ->options([
                        '60' => '1 hora',
                        '1440' => '24 horas',
                        '10080' => '7 días',
                        'permanent' => 'Hasta que lo levante',
                    ])
                    ->default('1440')
                    ->native(false)
                    ->required(),
                Textarea::make('reason')
                    ->label('Motivo')
                    ->placeholder('Qué comportamiento motivó el bloqueo.')
                    ->required()
                    ->minLength(UserBlockList::MIN_REASON_LENGTH)
                    ->maxLength(1000)
                    ->rows(3)
                    ->validationMessages([
                        'required' => 'El motivo es obligatorio.',
                        'min' => 'Explique el motivo con al menos '.UserBlockList::MIN_REASON_LENGTH.' caracteres.',
                    ]),
            ])
            ->action(function (array $arguments, array $data): void {
                $user = User::query()->find((int) ($arguments['userId'] ?? 0));

                if ($user === null) {
                    Notification::make()->warning()->title('El usuario ya no existe')->send();

                    return;
                }

                try {
                    UserBlockList::block($user, (string) ($data['reason'] ?? ''), $data['duration'] === 'permanent' ? null : (int) $data['duration']);
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->warning()->title('No se bloqueó')->body($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Usuario bloqueado')->body($user->name.' queda fuera del sistema en su próxima petición.')->send();
            });
    }

    public function liftBlockAction(): Action
    {
        return Action::make('liftBlock')
            ->label('Levantar')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('success')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading('Levantar el bloqueo')
            ->modalDescription('El usuario podrá volver a iniciar sesión de inmediato.')
            ->form([
                Textarea::make('reason')->label('Motivo (opcional)')->maxLength(1000)->rows(2),
            ])
            ->action(function (array $arguments, array $data): void {
                $block = SecurityUserBlock::query()->find((int) ($arguments['blockId'] ?? 0));

                if ($block !== null) {
                    UserBlockList::lift($block, (string) ($data['reason'] ?? ''));
                }

                Notification::make()->success()->title('Bloqueo levantado')->send();
            });
    }

    public function unlockAccountAction(): Action
    {
        return Action::make('unlockAccount')
            ->label('Desbloquear')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('gray')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading('Desbloquear la cuenta')
            ->modalDescription('Quita el bloqueo temporal por intentos fallidos. Si el ataque sigue, se volverá a bloquear sola.')
            ->action(function (array $arguments): void {
                SecurityMonitor::unlockAccount((string) ($arguments['account'] ?? ''), (string) (Auth::user()?->name ?? 'system'));
                Notification::make()->success()->title('Cuenta desbloqueada')->send();
            });
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
