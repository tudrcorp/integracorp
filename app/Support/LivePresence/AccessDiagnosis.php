<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use App\Models\SecurityUserBlock;
use App\Models\User;
use App\Support\Filament\PanelAccessResolver;
use Throwable;

/**
 * «¿Por qué no puede entrar este usuario?»
 *
 * Reúne en una sola respuesta lo que hoy hay que revisar a mano: si el correo
 * existe, su estatus y paneles, los tres bloqueos posibles (cuenta por intentos,
 * usuario en lista negra, IP en lista negra) y sus últimos intentos con el panel
 * por el que entró. Solo lee: no cambia nada.
 */
final class AccessDiagnosis
{
    public const SEVERITY_OK = 'ok';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_DANGER = 'danger';

    private const RECENT_EVENTS = 8;

    /**
     * @return array{
     *     email: string,
     *     user: array{id: int, name: string, email: string, status: string}|null,
     *     suggestions: list<array{name: string, email: string}>,
     *     panels: list<array{id: string, label: string, url: string}>,
     *     account_lock: array<string, mixed>|null,
     *     user_block: array{reason: string, by: string, until: string|null}|null,
     *     ips: list<array{ip: string, blocked: bool}>,
     *     events: list<array{time: string, type: string, title: string, detail: string}>,
     *     findings: list<array{severity: string, title: string, detail: string}>
     * }|null
     */
    public static function for(string $email): ?array
    {
        $email = mb_strtolower(trim($email));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $user = self::findUser($email);
        $panels = $user !== null ? PanelAccessResolver::accessiblePanels($user) : [];
        $lock = SecurityMonitor::accountLock($email);
        $userBlock = $user !== null ? self::activeUserBlock((int) $user->getKey()) : null;
        $events = self::recentEvents($email);
        $ips = self::recentIps($events, $user);

        $diagnosis = [
            'email' => $email,
            'user' => $user !== null ? [
                'id' => (int) $user->getKey(),
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'status' => (string) $user->status,
            ] : null,
            'suggestions' => $user === null ? self::similarUsers($email) : [],
            'panels' => $panels,
            'account_lock' => $lock,
            'user_block' => $userBlock,
            'ips' => $ips,
            'events' => array_map(static fn (array $event): array => [
                'time' => date('d/m H:i', (int) ($event['at'] ?? 0)),
                'type' => (string) ($event['type'] ?? ''),
                'title' => (string) ($event['title'] ?? ''),
                'detail' => (string) ($event['detail'] ?? ''),
            ], array_slice($events, 0, self::RECENT_EVENTS)),
            'findings' => [],
        ];

        $diagnosis['findings'] = self::findings($diagnosis, $events);

        return $diagnosis;
    }

    /**
     * @param  array<string, mixed>  $diagnosis
     * @param  list<array<string, mixed>>  $events
     * @return list<array{severity: string, title: string, detail: string}>
     */
    private static function findings(array $diagnosis, array $events): array
    {
        $findings = [];

        if ($diagnosis['user'] === null) {
            return [[
                'severity' => self::SEVERITY_DANGER,
                'title' => 'No existe un usuario con ese correo',
                'detail' => $diagnosis['suggestions'] !== []
                    ? 'Revise cómo lo escribe: hay usuarios con correos parecidos (abajo).'
                    : 'Revise cómo lo escribe o si se registró con otro correo. Mientras tanto, cada intento cuenta como fallido.',
            ]];
        }

        if ($diagnosis['user']['status'] !== 'ACTIVO') {
            $findings[] = [
                'severity' => self::SEVERITY_DANGER,
                'title' => 'El usuario no está ACTIVO',
                'detail' => 'Su estatus es «'.($diagnosis['user']['status'] !== '' ? $diagnosis['user']['status'] : 'sin estatus').'». Actívelo desde la ficha del usuario.',
            ];
        }

        if ($diagnosis['user_block'] !== null) {
            $findings[] = [
                'severity' => self::SEVERITY_DANGER,
                'title' => 'Bloqueado por un administrador',
                'detail' => 'Motivo: '.$diagnosis['user_block']['reason'].' (por '.$diagnosis['user_block']['by']
                    .($diagnosis['user_block']['until'] !== null ? ', hasta '.$diagnosis['user_block']['until'] : ', sin vencimiento')
                    .'). Levántelo en «Lista negra» de usuarios.',
            ];
        }

        if ($diagnosis['account_lock'] !== null) {
            $findings[] = [
                'severity' => self::SEVERITY_DANGER,
                'title' => 'Cuenta bloqueada por intentos fallidos',
                'detail' => 'Hasta las '.date('H:i', (int) ($diagnosis['account_lock']['until'] ?? time())).'. Puede desbloquearla aquí mismo; corrija antes la causa de los fallos.',
            ];
        }

        foreach ($diagnosis['ips'] as $ip) {
            if ($ip['blocked']) {
                $findings[] = [
                    'severity' => self::SEVERITY_DANGER,
                    'title' => 'Se conecta desde una IP en la lista negra',
                    'detail' => $ip['ip'].' está bloqueada: todo el que salga por ella ve «Acceso denegado.». Levántela en «IPs en lista negra».',
                ];
            }
        }

        if ($diagnosis['panels'] === []) {
            $findings[] = [
                'severity' => self::SEVERITY_DANGER,
                'title' => 'No tiene acceso a ningún panel',
                'detail' => 'Revise su departamento y sus banderas (agente, agencia, proveedor) en la ficha del usuario.',
            ];
        }

        $panelLabels = array_column($diagnosis['panels'], 'label');
        $wrongPanels = [];
        $failedInOwnPanel = 0;

        foreach ($events as $event) {
            $channel = (string) ($event['channel'] ?? '');

            if (($event['type'] ?? '') === 'wrong_panel' && $channel !== '') {
                $wrongPanels[$channel] = true;
            }

            if (($event['type'] ?? '') === 'failed_login' && $channel !== '') {
                if (in_array($channel, $panelLabels, true)) {
                    $failedInOwnPanel++;
                } else {
                    $wrongPanels[$channel] = true;
                }
            }
        }

        if ($wrongPanels !== [] && $panelLabels !== []) {
            $findings[] = [
                'severity' => self::SEVERITY_WARNING,
                'title' => 'Intenta entrar por el panel equivocado',
                'detail' => 'Probó en '.implode(', ', array_keys($wrongPanels)).', pero su acceso es por '.implode(', ', $panelLabels).'. Envíele el enlace correcto.',
            ];
        }

        if ($failedInOwnPanel > 0) {
            $findings[] = [
                'severity' => self::SEVERITY_WARNING,
                'title' => $failedInOwnPanel.' '.($failedInOwnPanel === 1 ? 'intento fallido' : 'intentos fallidos').' en su propio panel',
                'detail' => 'El panel es el correcto, así que la clave no coincide. Restablézcale la contraseña.',
            ];
        }

        if ($findings === []) {
            $findings[] = [
                'severity' => self::SEVERITY_OK,
                'title' => 'No hay nada que le impida entrar',
                'detail' => 'Sin bloqueos. Debe entrar por '.implode(', ', $panelLabels).' con su clave. Si aun así falla, pídale una captura del mensaje que ve.',
            ];
        }

        return $findings;
    }

    private static function findUser(string $email): ?User
    {
        try {
            return User::query()->where('email', $email)->first();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{name: string, email: string}>
     */
    private static function similarUsers(string $email): array
    {
        $local = explode('@', $email)[0];

        if (mb_strlen($local) < 4) {
            return [];
        }

        $needle = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($local, 0, 6));

        try {
            return User::query()
                ->where('email', 'like', $needle.'%')
                ->orderBy('email')
                ->limit(5)
                ->get(['name', 'email'])
                ->map(static fn (User $user): array => ['name' => (string) $user->name, 'email' => (string) $user->email])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array{reason: string, by: string, until: string|null}|null
     */
    private static function activeUserBlock(int $userId): ?array
    {
        if (! UserBlockList::isBlocked($userId)) {
            return null;
        }

        try {
            $block = SecurityUserBlock::query()->where('user_id', $userId)->active()->latest('id')->first();
        } catch (Throwable) {
            $block = null;
        }

        return [
            'reason' => (string) ($block->reason ?? 'sin detalle'),
            'by' => (string) ($block->blocked_by_name ?? 'un administrador'),
            'until' => $block?->expires_at?->format('d/m/Y H:i'),
        ];
    }

    /**
     * Eventos de seguridad de hoy de esa cuenta, del más reciente al más viejo.
     *
     * @return list<array<string, mixed>>
     */
    private static function recentEvents(string $email): array
    {
        $account = SecurityMonitor::normalizeAccount($email);

        try {
            $events = LivePresenceStore::repository()->readList(SecurityMonitor::prefix().'events', 150);
        } catch (Throwable) {
            return [];
        }

        $mine = array_values(array_filter(
            $events,
            static fn (mixed $event): bool => is_array($event) && (string) ($event['account'] ?? '') === $account,
        ));

        usort($mine, static fn (array $a, array $b): int => (int) ($b['at'] ?? 0) <=> (int) ($a['at'] ?? 0));

        return $mine;
    }

    /**
     * IPs de sus intentos de hoy y de sus sesiones abiertas.
     *
     * @param  list<array<string, mixed>>  $events
     * @return list<array{ip: string, blocked: bool}>
     */
    private static function recentIps(array $events, ?User $user): array
    {
        $ips = [];

        foreach ($events as $event) {
            $ip = (string) ($event['ip'] ?? '');

            if ($ip !== '') {
                $ips[$ip] = true;
            }
        }

        if ($user !== null) {
            try {
                foreach (LivePresenceStore::repository()->online(max(60, (int) config('live-presence.session_ttl', 300))) as $row) {
                    if ((int) ($row['user_id'] ?? 0) === (int) $user->getKey() && (string) ($row['ip'] ?? '') !== '') {
                        $ips[(string) $row['ip']] = true;
                    }
                }
            } catch (Throwable) {
            }
        }

        return array_map(
            static fn (string $ip): array => ['ip' => $ip, 'blocked' => IpBlockList::isBlocked($ip)],
            array_keys($ips),
        );
    }
}
