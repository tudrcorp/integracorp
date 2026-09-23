<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Arma y guarda la presencia de una sesión: lo que ve el servidor en cada
 * petición (panel, página, acción, IP, ubicación, navegador, tiempo de
 * respuesta) y lo que reporta el navegador en cada latido (latencia, red,
 * pestaña activa, PWA instalada).
 */
final class LivePresenceRecorder
{
    /** Consultas SQL ejecutadas en el proceso; se usa la diferencia por petición. */
    public static int $queries = 0;

    public static function sessionKey(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $id = (string) $request->session()->getId();

        return $id === '' ? null : substr(hash('sha256', $id), 0, 24);
    }

    /**
     * Petición normal del sistema (llamado desde el middleware, después de responder).
     */
    public static function recordServerRequest(Request $request, ?Response $response, Authenticatable $user, float $durationMs, int $queries): void
    {
        $sessionKey = self::sessionKey($request);

        if ($sessionKey === null) {
            return;
        }

        $isLivewire = ActivityContext::isLivewireUpdate($request);
        $isPage = self::isPageLoad($request, $response);
        $pagePath = ActivityContext::pagePath($request);
        /** Los clics dentro del propio monitor son ruido: no son actividad a vigilar. */
        $action = ActivityContext::isMonitorPage($pagePath) ? null : ActivityContext::livewireAction($request);

        $fields = [
            ...self::identity($request, $user),
            'server_ms' => round($durationMs, 1),
            'memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            'queries' => $queries,
            'status_code' => $response?->getStatusCode(),
            'last_server_at' => time(),
        ];

        if ($isPage || $isLivewire) {
            $panel = ActivityContext::panelFor($pagePath);
            $fields = [
                ...$fields,
                'panel' => $panel,
                'panel_label' => ActivityContext::panelLabel($panel),
                'page_path' => $pagePath,
                'page_label' => ActivityContext::pageLabel($pagePath),
            ];
        }

        if ($action !== null) {
            $fields['last_action'] = $action;
            $fields['last_action_at'] = time();
        }

        $event = match (true) {
            $action !== null => ['type' => 'action', 'label' => $action],
            $isPage => ['type' => 'page', 'label' => ActivityContext::pageLabel($pagePath)],
            $request->isMethod('GET') && ! $isLivewire && ! $request->expectsJson() => [
                'type' => 'download',
                'label' => ActivityContext::downloadLabel(
                    '/'.ltrim($request->path(), '/'),
                    $response?->headers->get('Content-Type'),
                    self::refererPageLabel($request),
                ),
            ],
            default => null,
        };

        SecurityMonitor::recordAuthenticatedCountry(
            (int) $user->getAuthIdentifier(),
            (string) ($fields['user_name'] ?? ''),
            (string) ($fields['country_code'] ?? ''),
            (string) ($fields['ip'] ?? ''),
        );

        LivePresenceStore::safely(function (LivePresenceRepository $store) use ($sessionKey, $fields, $event, $user, $pagePath, $durationMs, $response): void {
            $store->touch($sessionKey, $fields, countRequest: true);
            $store->recordRequestDuration($durationMs);

            if ($event !== null) {
                $panel = ActivityContext::panelFor($pagePath);
                $store->pushTimeline((int) $user->getAuthIdentifier(), [
                    ...$event,
                    'at' => time(),
                    'panel' => ActivityContext::panelLabel($panel),
                    'page' => $event['type'] === 'page' ? null : ActivityContext::pageLabel($event['type'] === 'download' ? (self::refererPath($request) ?? $pagePath) : $pagePath),
                    'path' => $pagePath,
                    'ms' => (int) round($durationMs),
                    'status' => $response?->getStatusCode(),
                ]);
            }
        });
    }

    /**
     * Latido del navegador.
     *
     * @param  array<string, mixed>  $client  Datos ya validados por el controlador.
     */
    public static function recordPing(Request $request, Authenticatable $user, array $client): void
    {
        $sessionKey = self::sessionKey($request);

        if ($sessionKey === null) {
            return;
        }

        $pagePath = '/'.ltrim((string) ($client['path'] ?? ''), '/');
        $panel = ActivityContext::panelFor($pagePath);

        $fields = [
            ...self::identity($request, $user),
            'panel' => $panel,
            'panel_label' => ActivityContext::panelLabel($panel),
            'page_path' => $pagePath,
            'page_label' => ActivityContext::pageLabel($pagePath),
            'page_title' => $client['title'] ?? null,
            'rtt_ms' => $client['rtt'] ?? null,
            'conn_type' => $client['effective_type'] ?? null,
            'downlink' => $client['downlink'] ?? null,
            'conn_rtt' => $client['conn_rtt'] ?? null,
            'visible' => (bool) ($client['visible'] ?? true),
            'load_ms' => $client['load'] ?? null,
            'ttfb_ms' => $client['ttfb'] ?? null,
            'screen' => $client['screen'] ?? null,
            'device_memory' => $client['memory'] ?? null,
            'cores' => $client['cores'] ?? null,
            'lang' => $client['lang'] ?? null,
            'timezone' => $client['tz'] ?? null,
            'pwa_installed' => (bool) ($client['standalone'] ?? false),
            'last_ping_at' => time(),
        ];

        $reason = (string) ($client['reason'] ?? 'heartbeat');

        LivePresenceStore::safely(function (LivePresenceRepository $store) use ($sessionKey, $fields, $reason, $user, $panel, $pagePath): void {
            $store->touch($sessionKey, $fields, countRequest: false);

            if ($reason === 'visibility') {
                $store->pushTimeline((int) $user->getAuthIdentifier(), [
                    'type' => 'visibility',
                    'label' => $fields['visible'] ? 'Volvió a la pestaña' : 'Dejó la pestaña en segundo plano',
                    'at' => time(),
                    'panel' => ActivityContext::panelLabel($panel),
                    'path' => $pagePath,
                ]);
            }
        });
    }

    /**
     * @return array<string, scalar|null>
     */
    private static function identity(Request $request, Authenticatable $user): array
    {
        $ip = ClientLocation::ip($request);
        $location = ClientLocation::locate($request, $ip);
        $userAgent = Str::limit((string) $request->userAgent(), 400, '');
        $agent = UserAgentSummary::parse($userAgent);

        return [
            'user_id' => (int) $user->getAuthIdentifier(),
            'user_name' => Str::limit((string) ($user->name ?? ''), 120, ''),
            'user_email' => Str::limit((string) ($user->email ?? ''), 150, ''),
            'ip' => $ip,
            'country' => $location['country'],
            'country_code' => $location['country_code'],
            'region' => $location['region'],
            'city' => $location['city'],
            'geo_source' => $location['source'],
            'browser' => $agent['browser'],
            'browser_version' => $agent['version'],
            'os' => $agent['os'],
            'os_version' => $agent['os_version'],
            'device' => $agent['device'],
            'user_agent' => $userAgent,
        ];
    }

    private static function refererPath(Request $request): ?string
    {
        $path = parse_url((string) $request->headers->get('referer', ''), PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : null;
    }

    private static function refererPageLabel(Request $request): ?string
    {
        $path = self::refererPath($request);

        return $path === null ? null : ActivityContext::pageLabel($path);
    }

    private static function isPageLoad(Request $request, ?Response $response): bool
    {
        if (! $request->isMethod('GET') || $request->ajax() && ! $request->headers->has('X-Livewire-Navigate')) {
            return false;
        }

        $contentType = (string) $response?->headers->get('Content-Type', '');

        return str_contains($contentType, 'text/html');
    }
}
