<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Models\SecurityIpBlock;
use App\Models\SecurityUserBlock;
use App\Models\User;
use App\Support\LivePresence\ActivityContext;
use App\Support\LivePresence\ErrorTracker;
use App\Support\LivePresence\IpBlockList;
use App\Support\LivePresence\IpThreatAssessment;
use App\Support\LivePresence\LiveActivitySnapshot;
use App\Support\LivePresence\LivePresenceAccess;
use App\Support\LivePresence\LivePresenceStore;
use App\Support\LivePresence\OperationsAdvisor;
use App\Support\LivePresence\SecurityMonitor;
use App\Support\LivePresence\SecuritySnapshot;
use App\Support\LivePresence\UserBlockList;
use App\Support\SecurityAudit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
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

    /** Filtro de la vista: solo amenazas confirmadas. No borra nada. */
    #[Url(as: 'confirmadas')]
    public bool $onlyConfirmedThreats = false;

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

    public function toggleOnlyConfirmedThreats(): void
    {
        $this->onlyConfirmedThreats = ! $this->onlyConfirmedThreats;
    }

    /**
     * Reiniciar la ficha de una IP cuya causa ya se entendió o corrigió.
     */
    public function resetIpAction(): Action
    {
        return Action::make('resetIp')
            ->label('Reiniciar')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->size('sm')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedArrowPath)
            ->modalHeading(fn (array $arguments): string => 'Reiniciar la ficha de '.self::argumentIp($arguments))
            ->modalDescription('Sale de «IPs sospechosas» con puntaje, etiquetas y contadores en cero. Se conservan los eventos, la auditoría y la lista negra. Si la causa sigue activa, la IP vuelve a aparecer con datos limpios.')
            ->modalSubmitActionLabel('Reiniciar ficha')
            ->action(function (array $arguments): void {
                $ip = self::argumentIp($arguments);

                if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                    Notification::make()->warning()->title('La IP no es válida')->send();

                    return;
                }

                SecurityMonitor::resetIp($ip, (string) (Auth::user()?->name ?? 'system'));
                SecurityAudit::log('AUDIT_LIVE_SECURITY_IP_RESET', 'live-presence.ip-reset', ['ip' => $ip]);

                Notification::make()->success()->title('Ficha reiniciada')->body($ip.' sale de la lista de sospechosas.')->send();
            });
    }

    /**
     * Limpiar de una vez los falsos positivos. Nunca toca amenazas confirmadas.
     */
    public function clearFalsePositivesAction(): Action
    {
        return Action::make('clearFalsePositives')
            ->label('Limpiar falsos positivos')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->size('xs')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedSparkles)
            ->modalHeading('Limpiar falsos positivos')
            ->modalDescription(function (): HtmlString {
                $candidates = SecuritySnapshot::falsePositives();

                if ($candidates === []) {
                    return new HtmlString('No hay falsos positivos que limpiar: las IPs que quedan son amenazas confirmadas o posibles sin ningún atenuante.');
                }

                $lines = ['Se reinicia la ficha de <strong>'.count($candidates).'</strong> '.(count($candidates) === 1 ? 'IP' : 'IPs').' calificadas «Probable falso positivo» o con sesión o login legítimo:'];

                foreach (array_slice($candidates, 0, 10) as $candidate) {
                    $lines[] = '• '.e($candidate['ip']).' — '.e($candidate['verdict_label']).($candidate['sessions'] !== [] ? ' ('.e(implode(', ', array_slice($candidate['sessions'], 0, 2))).')' : '');
                }

                if (count($candidates) > 10) {
                    $lines[] = '… y '.(count($candidates) - 10).' más.';
                }

                $lines[] = 'Las amenazas confirmadas y las IPs en lista negra no se tocan. Los eventos y la auditoría se conservan.';

                return new HtmlString(implode('<br>', $lines));
            })
            ->modalSubmitActionLabel('Limpiar')
            ->action(function (): void {
                $candidates = SecuritySnapshot::falsePositives();
                $actor = (string) (Auth::user()?->name ?? 'system');

                foreach ($candidates as $candidate) {
                    SecurityMonitor::resetIp((string) $candidate['ip'], $actor, 'limpieza de falsos positivos');
                }

                SecurityAudit::log('AUDIT_LIVE_SECURITY_FALSE_POSITIVES_CLEARED', 'live-presence.ip-reset', [
                    'ips' => array_column($candidates, 'ip'),
                    'count' => count($candidates),
                ]);

                Notification::make()->success()
                    ->title($candidates === [] ? 'No había falsos positivos' : count($candidates).' '.(count($candidates) === 1 ? 'IP limpiada' : 'IPs limpiadas'))
                    ->body($candidates === [] ? null : 'Las amenazas confirmadas siguen en la lista.')
                    ->send();
            });
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

        $health = LiveActivitySnapshot::systemHealth();
        $security = SecuritySnapshot::build();

        return [
            'sessions' => $sessions,
            'kpis' => $kpis,
            'health' => $health,
            'advice' => OperationsAdvisor::advise($security, $health['queue_report'] ?? null, ErrorTracker::groups()),
            'panelLabels' => ActivityContext::PANELS + ['web' => 'Sitio web'],
            'selected' => $selected,
            'selectedTimeline' => $selected === null ? [] : $this->timelineFor((int) $selected['user_id']),
            'selectedSiblings' => $selected === null
                ? []
                : array_values(array_filter($all, fn (array $session): bool => $session['user_id'] === $selected['user_id'] && $session['session_key'] !== $selected['session_key'])),
            'refreshedAt' => now()->format('H:i:s'),
            'selectedCanBeBlocked' => $selected !== null && ($target = User::query()->find((int) $selected['user_id'])) !== null
                && UserBlockList::restrictionFor($target) === null,
            'security' => $security,
            'blocks' => $blocks,
            'blockedIds' => array_map(static fn (SecurityUserBlock $block): int => (int) $block->user_id, $blocks),
            'ipBlocks' => IpBlockList::active(),
            'dismissedIps' => SecurityMonitor::dismissals(),
        ];
    }

    /**
     * Mover una IP a la lista negra: el sistema recomienda, el analista decide.
     */
    public function blacklistIpAction(): Action
    {
        return Action::make('blacklistIp')
            ->label('Lista negra')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->size('sm')
            ->modalIcon(Heroicon::OutlinedNoSymbol)
            ->modalIconColor('danger')
            ->modalHeading(fn (array $arguments): string => 'Mover '.self::argumentIp($arguments).' a la lista negra')
            ->modalDescription(fn (array $arguments): HtmlString => self::offenderSummary(SecuritySnapshot::offender(self::argumentIp($arguments))))
            ->modalSubmitActionLabel('Mover a lista negra')
            ->form(function (array $arguments): array {
                $offender = SecuritySnapshot::offender(self::argumentIp($arguments));
                $affected = count(IpBlockList::sessionsFrom($offender['ip']));

                return [
                    Select::make('duration')
                        ->label('Duración')
                        ->options([
                            '1440' => '24 horas',
                            '10080' => '7 días',
                            '43200' => '30 días',
                            'permanent' => 'Hasta que la levante',
                        ])
                        ->default((string) IpThreatAssessment::suggestedMinutes($offender['verdict']))
                        ->helperText('Las IPs de hogares y celulares cambian de dueño: prefiera un bloqueo con vencimiento.')
                        ->native(false)
                        ->required(),
                    Textarea::make('reason')
                        ->label('Motivo')
                        ->default($offender['verdict_label'].': '.implode(' ', $offender['reasons']))
                        ->required()
                        ->minLength(IpBlockList::MIN_REASON_LENGTH)
                        ->maxLength(1000)
                        ->rows(3)
                        ->validationMessages([
                            'required' => 'El motivo es obligatorio.',
                            'min' => 'Explique el motivo con al menos '.IpBlockList::MIN_REASON_LENGTH.' caracteres.',
                        ]),
                    Checkbox::make('acknowledge_affected')
                        ->label('Entiendo que '.$affected.' '.($affected === 1 ? 'usuario conectado desde esta IP perderá' : 'usuarios conectados desde esta IP perderán').' el acceso de inmediato.')
                        ->visible($affected > 0)
                        ->accepted()
                        ->validationMessages(['accepted' => 'Confirme que entiende a quién deja sin acceso.']),
                ];
            })
            ->action(function (array $arguments, array $data): void {
                $offender = SecuritySnapshot::offender(self::argumentIp($arguments));

                if (IpBlockList::sessionsFrom($offender['ip']) !== [] && ! ($data['acknowledge_affected'] ?? false)) {
                    Notification::make()->warning()->title('No se bloqueó')->body('Hay usuarios conectados desde esta IP: confirme que entiende que perderán el acceso.')->send();

                    return;
                }

                try {
                    IpBlockList::block(
                        $offender['ip'],
                        (string) ($data['reason'] ?? ''),
                        $data['duration'] === 'permanent' ? null : (int) $data['duration'],
                        $offender['verdict'],
                        [
                            'verdict' => $offender['verdict'],
                            'reasons' => $offender['reasons'],
                            'mitigations' => $offender['mitigations'],
                            'score' => $offender['score'],
                            'tags' => $offender['tags'],
                            'counters' => $offender['counters'],
                            'location' => $offender['location'],
                            'user_agent' => $offender['user_agent'],
                            'sessions' => $offender['sessions'],
                        ],
                    );
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->warning()->title('No se bloqueó')->body($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('IP en lista negra')->body($offender['ip'].' ya no puede entrar a ninguna parte del sistema.')->send();
            });
    }

    /**
     * Marcar una IP como legítima: sale de la lista de sospechosas unos días,
     * salvo que vuelva a dar una señal dura.
     */
    public function dismissIpAction(): Action
    {
        return Action::make('dismissIp')
            ->label('Es legítima')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('gray')
            ->size('sm')
            ->modalIcon(Heroicon::OutlinedCheckCircle)
            ->modalHeading(fn (array $arguments): string => 'Marcar '.self::argumentIp($arguments).' como legítima')
            ->modalDescription('Deja de listarse como sospechosa por '.max(1, (int) config('live-presence.security.dismiss_days', 7)).' días. Si vuelve a dar una señal de ataque clara (escáner, herramienta de ataque, fuerza bruta), reaparece sola.')
            ->modalSubmitActionLabel('Marcar como legítima')
            ->form([
                Textarea::make('note')->label('Nota (opcional)')->placeholder('Por ejemplo: es la oficina de Carora.')->maxLength(300)->rows(2),
            ])
            ->action(function (array $arguments, array $data): void {
                $ip = self::argumentIp($arguments);

                if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                    Notification::make()->warning()->title('La IP no es válida')->send();

                    return;
                }

                $actor = (string) (Auth::user()?->name ?? 'system');
                SecurityMonitor::dismissIp($ip, $actor, (string) ($data['note'] ?? ''));
                SecurityAudit::log('AUDIT_LIVE_SECURITY_IP_DISMISSED', 'live-presence.ip-dismiss', ['ip' => $ip, 'note' => (string) ($data['note'] ?? '')]);

                Notification::make()->success()->title('IP marcada como legítima')->body($ip.' sale de la lista de sospechosas.')->send();
            });
    }

    public function undismissIpAction(): Action
    {
        return Action::make('undismissIp')
            ->label('Deshacer')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading('Volver a vigilar esta IP')
            ->modalDescription('Si sigue dando señales, vuelve a la lista de IPs sospechosas.')
            ->action(function (array $arguments): void {
                SecurityMonitor::undismissIp(self::argumentIp($arguments));
                Notification::make()->success()->title('La IP vuelve a vigilarse')->send();
            });
    }

    public function liftIpBlockAction(): Action
    {
        return Action::make('liftIpBlock')
            ->label('Levantar')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('success')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading('Sacar la IP de la lista negra')
            ->modalDescription('La IP podrá volver a entrar al sistema de inmediato.')
            ->form([
                Textarea::make('reason')->label('Motivo (opcional)')->maxLength(1000)->rows(2),
            ])
            ->action(function (array $arguments, array $data): void {
                $block = SecurityIpBlock::query()->find((int) ($arguments['blockId'] ?? 0));

                if ($block !== null) {
                    IpBlockList::lift($block, (string) ($data['reason'] ?? ''));
                }

                Notification::make()->success()->title('IP fuera de la lista negra')->send();
            });
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
     * @param  array<string, mixed>  $arguments
     */
    private static function argumentIp(array $arguments): string
    {
        return trim((string) ($arguments['ip'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $offender
     */
    private static function offenderSummary(array $offender): HtmlString
    {
        $lines = ['<strong>'.e($offender['verdict_label']).'</strong>'];

        foreach ($offender['reasons'] as $reason) {
            $lines[] = '• '.e($reason);
        }

        foreach ($offender['mitigations'] as $mitigation) {
            $lines[] = '⚠ '.e($mitigation);
        }

        $lines[] = 'Queda sin acceso a todo el sistema (paneles, PWA y páginas públicas) hasta que venza o se levante. Queda registrado quién la bloqueó y por qué.';

        return new HtmlString(implode('<br>', $lines));
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
