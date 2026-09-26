<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use Illuminate\Support\Str;

/**
 * Datos ya masticados para la pantalla de TV del monitor (Flux).
 *
 * La vista solo dibuja: aquí se decide qué se muestra, en qué orden y con qué
 * nivel (green/amber/red), para leer de lejos sin tener que interpretar nada.
 * Las clases de color viven en la vista, porque Tailwind solo compila lo que
 * encuentra escrito en `resources/views`.
 */
final class LiveMonitorTvBoard
{
    /** Minutos de historia en las gráficas (lo que guarda SecuritySnapshot). */
    public const CHART_MINUTES = 30;

    /** Métricas de seguridad graficadas, con su etiqueta en la TV. */
    public const SECURITY_METRICS = [
        'failed_logins' => 'Logins fallidos',
        'server_errors' => 'Errores 5xx',
        'not_found' => 'Páginas 404',
        'throttled' => 'Frenadas 429',
        'csrf' => 'Formularios 419',
    ];

    private const MAX_ACTIONS = 5;

    private const MAX_OFFENDERS = 5;

    private const MAX_TARGETS = 5;

    private const MAX_EVENTS = 7;

    /**
     * @param  array<string, mixed>  $security  SecuritySnapshot::build()
     * @param  array<string, mixed>  $kpis  LiveActivitySnapshot::kpis()
     * @param  array<string, mixed>  $health  LiveActivitySnapshot::systemHealth()
     * @param  array<string, mixed>  $advice  OperationsAdvisor::advise()
     * @param  list<array<string, mixed>>  $sessions  LiveActivitySnapshot::sessions()
     * @return array<string, mixed>
     */
    public static function build(array $security, array $kpis, array $health, array $advice, array $sessions, int $maxSessions = 12): array
    {
        $queueReport = $health['queue_report'] ?? null;

        return [
            'lights' => self::lights($security, $advice),
            'blocks' => [
                'locks' => count($security['locks'] ?? []),
                'users' => (int) ($security['blocked_users'] ?? 0),
                'ips' => (int) ($security['blocked_ips'] ?? 0),
            ],
            'stuckQueues' => self::stuckQueues($queueReport),
            'kpis' => self::kpis($kpis),
            'requestsSparkline' => array_map('intval', array_values($security['series']['requests'] ?? [])),
            'chart' => self::chartRows($security['series'] ?? []),
            'lastMinute' => self::lastMinute($security),
            'actions' => array_slice($advice['actions'] ?? [], 0, self::MAX_ACTIONS),
            'offenders' => self::offenders($security['offenders'] ?? []),
            'offendersTotal' => count($security['offenders'] ?? []),
            'targets' => self::targets($security['targets'] ?? []),
            'events' => array_slice($security['events'] ?? [], 0, self::MAX_EVENTS),
            'queues' => self::queues($queueReport),
            'workers' => self::workers($queueReport),
            'sessions' => array_slice($sessions, 0, $maxSessions),
            'totalSessions' => count($sessions),
        ];
    }

    /**
     * Filas de la gráfica: una por minuto, de la más vieja a la actual.
     *
     * @param  array<string, list<int>>  $series
     * @return list<array<string, int|string>>
     */
    public static function chartRows(array $series, ?int $now = null): array
    {
        $now ??= time();
        $currentMinute = $now - ($now % 60);
        $rows = [];

        for ($index = 0; $index < self::CHART_MINUTES; $index++) {
            $minute = $currentMinute - ((self::CHART_MINUTES - 1 - $index) * 60);
            /** Sin zona horaria: el gráfico de Flux solo acepta «YYYY-MM-DDTHH:MM:SS» y lo muestra tal cual. */
            $row = ['at' => date('Y-m-d\\TH:i:s', $minute)];

            foreach (['requests', 'anonymous', ...array_keys(self::SECURITY_METRICS)] as $metric) {
                $values = array_values($series[$metric] ?? []);
                $offset = count($values) - self::CHART_MINUTES;
                $row[$metric] = (int) ($values[$index + $offset] ?? 0);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $security
     * @param  array<string, mixed>  $advice
     * @return array<string, array{title: string, level: string, label: string, detail: string}>
     */
    private static function lights(array $security, array $advice): array
    {
        $lights = [
            'security' => [
                'title' => 'Seguridad',
                'level' => (string) ($security['level'] ?? 'green'),
                'label' => (string) ($security['level_label'] ?? 'Normal'),
                'detail' => (string) (($security['reasons'] ?? [])[0] ?? 'Sin señales de ataque.'),
            ],
        ];

        foreach (['queues' => 'Colas', 'errors' => 'Errores'] as $key => $title) {
            $light = $advice['lights'][$key] ?? ['level' => 'amber', 'label' => 'Sin lectura', 'detail' => ''];
            $lights[$key] = [
                'title' => $title,
                'level' => (string) $light['level'],
                'label' => (string) $light['label'],
                'detail' => (string) $light['detail'],
            ];
        }

        return $lights;
    }

    /**
     * @param  array<string, mixed>  $kpis
     * @return list<array{label: string, value: string, unit: string, hint: string, level: string|null}>
     */
    private static function kpis(array $kpis): array
    {
        $rtt = $kpis['avg_rtt'] ?? null;
        $p95 = $kpis['p95_ms'] ?? null;
        $sessions = (int) ($kpis['sessions'] ?? 0);
        $idle = (int) ($kpis['idle_sessions'] ?? 0);

        return [
            ['label' => 'Usuarios conectados', 'value' => (string) ($kpis['users'] ?? 0), 'unit' => '', 'hint' => $sessions.' '.($sessions === 1 ? 'sesión' : 'sesiones').($idle > 0 ? ' · '.$idle.' '.($idle === 1 ? 'inactiva' : 'inactivas') : ''), 'level' => null],
            ['label' => 'Pestañas activas', 'value' => (string) ($kpis['active_tabs'] ?? 0), 'unit' => '', 'hint' => max(0, $sessions - (int) ($kpis['active_tabs'] ?? 0)).' en segundo plano', 'level' => null],
            ['label' => 'En la PWA', 'value' => (string) ($kpis['pwa'] ?? 0), 'unit' => '', 'hint' => 'clientes en /app', 'level' => null],
            ['label' => 'Latencia media', 'value' => $rtt === null ? '—' : (string) $rtt, 'unit' => $rtt === null ? '' : 'ms', 'hint' => 'ida y vuelta del navegador', 'level' => $rtt === null ? null : ($rtt < 200 ? 'green' : ($rtt < 600 ? 'amber' : 'red'))],
            ['label' => 'Respuesta p95', 'value' => $p95 === null ? '—' : (string) $p95, 'unit' => $p95 === null ? '' : 'ms', 'hint' => 'últimos 5 min', 'level' => $p95 === null ? null : ($p95 < 800 ? 'green' : ($p95 < 2000 ? 'amber' : 'red'))],
        ];
    }

    /**
     * @param  array<string, mixed>  $security
     * @return list<array{metric: string, label: string, value: int, five: int, total: int, level: string}>
     */
    private static function lastMinute(array $security): array
    {
        $rows = [];

        foreach (self::SECURITY_METRICS as $metric => $label) {
            $value = (int) ($security['last_minute'][$metric] ?? 0);
            $critical = in_array($metric, ['failed_logins', 'server_errors'], true);

            $rows[] = [
                'metric' => $metric,
                'label' => $label,
                'value' => $value,
                'five' => (int) ($security['last_five'][$metric] ?? 0),
                'total' => array_sum(array_map('intval', $security['series'][$metric] ?? [])),
                'level' => $value === 0 ? 'green' : ($critical ? 'red' : 'amber'),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $offenders
     * @return list<array{ip: string, flag: string, location: string, verdict: string, verdict_label: string, detail: string, blocked: bool}>
     */
    private static function offenders(array $offenders): array
    {
        return array_map(static fn (array $offender): array => [
            'ip' => (string) ($offender['ip'] ?? ''),
            'flag' => (string) ($offender['flag'] ?? ''),
            'location' => (string) ($offender['location'] ?? ''),
            'verdict' => (string) ($offender['verdict'] ?? 'possible'),
            'verdict_label' => (string) ($offender['verdict_label'] ?? 'Sospechosa'),
            'detail' => Str::limit(implode(' · ', array_slice((array) ($offender['reasons'] ?? []), 0, 2)), 90),
            'blocked' => (bool) ($offender['blocked'] ?? false),
        ], array_slice($offenders, 0, self::MAX_OFFENDERS));
    }

    /**
     * @param  array<string, int|float>  $targets
     * @return list<array{account: string, failures: int}>
     */
    private static function targets(array $targets): array
    {
        $rows = [];

        foreach (array_slice($targets, 0, self::MAX_TARGETS, true) as $account => $failures) {
            $rows[] = ['account' => (string) $account, 'failures' => (int) $failures];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>|null  $report
     * @return list<array{name: string, pending: int|null, reserved: int|null, listeners: int|null, status: string, status_label: string}>
     */
    private static function queues(?array $report): array
    {
        $known = (bool) ($report['workers']['known'] ?? false);

        return array_map(static fn (array $queue): array => [
            'name' => (string) ($queue['name'] ?? ''),
            'pending' => isset($queue['pending']) ? (int) $queue['pending'] : null,
            'reserved' => isset($queue['reserved']) ? (int) $queue['reserved'] : null,
            'listeners' => $known ? (int) ($queue['listeners'] ?? 0) : null,
            'status' => (string) ($queue['status'] ?? 'unknown'),
            'status_label' => (string) ($queue['status_label'] ?? '—'),
        ], array_values($report['queues'] ?? []));
    }

    /**
     * @param  array<string, mixed>|null  $report
     * @return array{known: bool, alive: int, processed_30m: int, failed_24h: int|null}
     */
    private static function workers(?array $report): array
    {
        return [
            'known' => (bool) ($report['workers']['known'] ?? false),
            'alive' => count($report['workers']['alive'] ?? []),
            'processed_30m' => (int) ($report['throughput']['processed_30m'] ?? 0),
            'failed_24h' => isset($report['failed']['last_24h']) ? (int) $report['failed']['last_24h'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $report
     * @return list<array{name: string, advice: string}>
     */
    private static function stuckQueues(?array $report): array
    {
        $rows = [];

        foreach ($report['queues'] ?? [] as $queue) {
            if (($queue['stuck'] ?? false) || ($queue['unattended'] ?? false)) {
                $rows[] = [
                    'name' => (string) ($queue['name'] ?? ''),
                    'advice' => (string) ($queue['advice'] ?? ((int) ($queue['pending'] ?? 0)).' trabajos esperan.'),
                ];
            }
        }

        return $rows;
    }
}
