<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use Throwable;

/**
 * Estado de seguridad para el monitor y la pantalla de TV: semáforo, series
 * por minuto, IPs sospechosas, cuentas atacadas, bloqueos y eventos.
 * Todo sale del almacenamiento de presencia (Redis): ninguna consulta a MySQL
 * salvo el conteo de usuarios bloqueados, que vive en caché.
 */
final class SecuritySnapshot
{
    public const LEVEL_GREEN = 'green';

    public const LEVEL_AMBER = 'amber';

    public const LEVEL_RED = 'red';

    /**
     * @return array<string, mixed>
     */
    public static function build(int $minutes = 30): array
    {
        try {
            $store = LivePresenceStore::repository();
        } catch (Throwable) {
            return self::empty();
        }

        try {
            $series = self::series($store, $minutes);
            $events = self::decorateEvents($store->readList(SecurityMonitor::prefix().'events', 40));
            /** Los críticos van en su propia lista: una ráfaga de logins fallidos no puede sacarlos del semáforo. */
            $critical = self::decorateEvents($store->readList(SecurityMonitor::prefix().'critical', 20));
            $offenders = self::offenders($store);
            $targets = $store->topMembers(SecurityMonitor::prefix().'targets', 8);
        } catch (Throwable) {
            return self::empty();
        }

        $lastMinute = array_map(static fn (array $values): int => (int) end($values), $series);
        $lastFive = array_map(static fn (array $values): int => array_sum(array_slice($values, -5)), $series);
        [$level, $reasons] = self::level($events, $critical, $lastMinute, $lastFive);

        return [
            'level' => $level,
            'level_label' => ['green' => 'Normal', 'amber' => 'Actividad sospechosa', 'red' => 'Ataque en curso'][$level],
            'reasons' => $reasons,
            'series' => $series,
            'last_minute' => $lastMinute,
            'last_five' => $lastFive,
            'events' => $events,
            'offenders' => $offenders,
            'targets' => $targets,
            'locks' => SecurityMonitor::activeLocks(),
            'blocked_users' => UserBlockList::activeCount(),
            'blocked_ips' => count(IpBlockList::activeIps()),
            'dismissed_ips' => count(SecurityMonitor::dismissalsFrom($store)),
        ];
    }

    /**
     * IPs que se pueden limpiar sin riesgo: calificadas «Probable falso
     * positivo», o con sesión abierta o login correcto desde ellas. Nunca
     * incluye amenazas confirmadas ni IPs en la lista negra.
     *
     * @return list<array<string, mixed>>
     */
    public static function falsePositives(): array
    {
        try {
            $store = LivePresenceStore::repository();
            $sessions = self::sessionNamesByIp($store);
            $dismissed = SecurityMonitor::dismissalsFrom($store);
            $blocked = array_flip(IpBlockList::activeIps());
            $rows = [];

            foreach ($store->topMembers(SecurityMonitor::prefix().'offenders', 200) as $ip => $score) {
                if ($score < 1) {
                    continue;
                }

                $row = self::offenderRow($store, (string) $ip, $score, $sessions, $dismissed, $blocked);

                if ($row['blocked'] || $row['verdict'] === IpThreatAssessment::CONFIRMED) {
                    continue;
                }

                if ($row['verdict'] === IpThreatAssessment::BENIGN || $row['mitigations'] !== []) {
                    $rows[] = $row;
                }
            }

            return $rows;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Una IP con su evidencia y veredicto, para las acciones del monitor.
     * Devuelve la fila aunque la IP ya no figure entre las sospechosas.
     *
     * @return array<string, mixed>
     */
    public static function offender(string $ip): array
    {
        try {
            $store = LivePresenceStore::repository();
            $score = (float) ($store->topMembers(SecurityMonitor::prefix().'offenders', 200)[$ip] ?? 0);

            return self::offenderRow($store, $ip, $score, self::sessionNamesByIp($store), SecurityMonitor::dismissalsFrom($store), array_flip(IpBlockList::activeIps()));
        } catch (Throwable) {
            return self::offenderRow(null, $ip, 0, [], [], []);
        }
    }

    /**
     * Puntos de una mini gráfica SVG (polilínea) para una serie.
     *
     * @param  list<int>  $values
     */
    public static function sparkline(array $values, int $width = 160, int $height = 36): string
    {
        $count = count($values);

        if ($count < 2) {
            return '0,'.$height.' '.$width.','.$height;
        }

        $max = max(1, max($values));
        $points = [];

        foreach (array_values($values) as $index => $value) {
            $x = round($index * $width / ($count - 1), 1);
            $y = round($height - ($value / $max) * ($height - 3) - 1.5, 1);
            $points[] = $x.','.$y;
        }

        return implode(' ', $points);
    }

    /**
     * @return array<string, list<int>> métrica → valores por minuto, del más viejo al actual
     */
    private static function series(LivePresenceRepository $store, int $minutes): array
    {
        $now = time();
        $minuteKeys = [];

        for ($offset = $minutes - 1; $offset >= 0; $offset--) {
            $minuteKeys[] = SecurityMonitor::minute($now - ($offset * 60));
        }

        $keys = [];

        foreach (SecurityMonitor::METRICS as $metric) {
            foreach ($minuteKeys as $minute) {
                $keys[] = SecurityMonitor::metricKey($metric, $minute);
            }
        }

        $counters = $store->counters($keys);
        $series = [];

        foreach (SecurityMonitor::METRICS as $metric) {
            $series[$metric] = array_map(
                static fn (string $minute): int => (int) ($counters[SecurityMonitor::metricKey($metric, $minute)] ?? 0),
                $minuteKeys,
            );
        }

        return $series;
    }

    /**
     * IPs sospechosas con su veredicto. Las marcadas como legítimas no se
     * listan; primero las amenazas confirmadas, después por puntaje.
     *
     * @return list<array<string, mixed>>
     */
    private static function offenders(LivePresenceRepository $store): array
    {
        $sessions = self::sessionNamesByIp($store);
        $dismissed = SecurityMonitor::dismissalsFrom($store);
        $blocked = array_flip(IpBlockList::activeIps());
        $rows = [];

        foreach ($store->topMembers(SecurityMonitor::prefix().'offenders', 30) as $ip => $score) {
            if ($score < 1) {
                continue;
            }

            $row = self::offenderRow($store, (string) $ip, $score, $sessions, $dismissed, $blocked);

            if ($row['dismissed']) {
                continue;
            }

            $rows[] = $row;
        }

        usort($rows, static fn (array $a, array $b): int => [IpThreatAssessment::RANK[$a['verdict']], -$a['score']] <=> [IpThreatAssessment::RANK[$b['verdict']], -$b['score']]);

        return array_slice($rows, 0, 12);
    }

    /**
     * @param  array<string, list<string>>  $sessions  IP → nombres con sesión abierta
     * @param  array<string, array<string, mixed>>  $dismissed
     * @param  array<string, int>  $blocked  IPs en la lista negra (como claves)
     * @return array<string, mixed>
     */
    private static function offenderRow(?LivePresenceRepository $store, string $ip, float $score, array $sessions, array $dismissed, array $blocked): array
    {
        $meta = $store?->getValue(SecurityMonitor::prefix().'ipmeta:'.$ip) ?? [];
        $counterKeys = array_map(static fn (string $metric): string => SecurityMonitor::ipCounterKey($ip, $metric), [...SecurityMonitor::IP_COUNTERS, 'blocked']);
        $rawCounters = $store?->counters($counterKeys) ?? [];
        $counters = [];

        foreach ([...SecurityMonitor::IP_COUNTERS, 'blocked'] as $metric) {
            $counters[$metric] = (int) ($rawCounters[SecurityMonitor::ipCounterKey($ip, $metric)] ?? 0);
        }

        $evidence = [
            'tags' => array_values(array_map('strval', (array) ($meta['tags'] ?? []))),
            'failed_logins' => (int) ($meta['failed_logins'] ?? 0),
            'accounts_tried' => (int) ($meta['accounts_tried'] ?? 0),
            'scanner_path' => (string) ($meta['scanner_path'] ?? ''),
            'user_agent' => (string) ($meta['user_agent'] ?? ''),
            'not_found_peak' => (int) ($meta['not_found_peak'] ?? 0),
            'flood_peak' => (int) ($meta['flood_peak'] ?? 0),
            'hard_at' => (int) ($meta['hard_at'] ?? 0),
            'counters' => $counters,
            'legitimate' => $store === null ? null : SecurityMonitor::legitimateUse($store, $ip),
            'sessions' => $sessions[$ip] ?? [],
            'dismissed' => $dismissed[$ip] ?? null,
        ];

        $assessment = IpThreatAssessment::assess($evidence);

        return [
            'ip' => $ip,
            'score' => (int) round($score),
            'tags' => $evidence['tags'],
            'location' => (string) ($meta['location'] ?? ''),
            'flag' => self::flag((string) ($meta['country_code'] ?? '')),
            'failed_logins' => $evidence['failed_logins'],
            'accounts_tried' => $evidence['accounts_tried'],
            'last_account' => (string) ($meta['last_account'] ?? ''),
            'last_path' => (string) ($meta['last_path'] ?? ''),
            'user_agent' => $evidence['user_agent'],
            'last_seen_ago' => isset($meta['last_seen']) ? LiveActivitySnapshot::ago(time() - (int) $meta['last_seen']) : '',
            'counters' => $counters,
            'sessions' => $evidence['sessions'],
            'verdict' => $assessment['verdict'],
            'verdict_label' => $assessment['label'],
            'reasons' => $assessment['reasons'],
            'mitigations' => $assessment['mitigations'],
            'hard' => $assessment['hard'],
            'dismissed' => $assessment['dismissed'],
            'blocked' => isset($blocked[$ip]),
        ];
    }

    /**
     * @return array<string, list<string>> IP → nombres de usuarios con sesión abierta
     */
    private static function sessionNamesByIp(LivePresenceRepository $store): array
    {
        $byIp = [];

        foreach ($store->online(max(60, (int) config('live-presence.session_ttl', 300))) as $row) {
            $ip = (string) ($row['ip'] ?? '');

            if ($ip !== '' && (int) ($row['user_id'] ?? 0) > 0) {
                $byIp[$ip][] = (string) ($row['user_name'] ?? 'Usuario');
            }
        }

        return array_map(static fn (array $names): array => array_values(array_unique($names)), $byIp);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private static function decorateEvents(array $events): array
    {
        $now = time();

        return array_map(static fn (array $event): array => [
            ...$event,
            'time' => date('H:i:s', (int) ($event['at'] ?? $now)),
            'ago' => LiveActivitySnapshot::ago($now - (int) ($event['at'] ?? $now)),
        ], $events);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @param  list<array<string, mixed>>  $criticalEvents
     * @param  array<string, int>  $lastMinute
     * @param  array<string, int>  $lastFive
     * @return array{0: string, 1: list<string>}
     */
    private static function level(array $events, array $criticalEvents, array $lastMinute, array $lastFive): array
    {
        $thresholds = (array) config('live-presence.security.thresholds', []);
        $recent = array_filter($events, static fn (array $event): bool => (int) ($event['at'] ?? 0) >= time() - 600);
        $critical = array_values(array_filter($criticalEvents, static fn (array $event): bool => (int) ($event['at'] ?? 0) >= time() - 600));
        $warning = array_values(array_filter($recent, static fn (array $event): bool => ($event['severity'] ?? '') === SecurityMonitor::SEVERITY_WARNING));

        if ($critical !== [] || $lastMinute['failed_logins'] >= (int) ($thresholds['failed_logins_red_per_minute'] ?? 20)) {
            $reasons = array_map(static fn (array $event): string => (string) ($event['title'] ?? '').': '.(string) ($event['detail'] ?? ''), array_slice($critical, 0, 3));

            if ($reasons === []) {
                $reasons[] = $lastMinute['failed_logins'].' logins fallidos en el último minuto.';
            }

            return [self::LEVEL_RED, $reasons];
        }

        $rejections = $lastMinute['csrf'] + $lastMinute['throttled'];

        if ($warning !== []
            || $lastMinute['failed_logins'] >= (int) ($thresholds['failed_logins_amber_per_minute'] ?? 5)
            || $rejections >= (int) ($thresholds['rejections_per_minute'] ?? 20)
            || $lastFive['scanner'] > 0) {
            $reasons = array_map(static fn (array $event): string => (string) ($event['title'] ?? '').': '.(string) ($event['detail'] ?? ''), array_slice($warning, 0, 3));

            if ($reasons === []) {
                $reasons[] = $lastMinute['failed_logins'].' logins fallidos y '.$rejections.' peticiones rechazadas en el último minuto.';
            }

            return [self::LEVEL_AMBER, $reasons];
        }

        return [self::LEVEL_GREEN, ['Sin señales de ataque en los últimos 10 minutos.']];
    }

    private static function flag(string $countryCode): string
    {
        $countryCode = strtoupper($countryCode);

        if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            return '';
        }

        return mb_chr(0x1F1E6 + ord($countryCode[0]) - 65).mb_chr(0x1F1E6 + ord($countryCode[1]) - 65);
    }

    /**
     * @return array<string, mixed>
     */
    private static function empty(): array
    {
        $zeros = array_fill_keys(SecurityMonitor::METRICS, 0);

        return [
            'level' => self::LEVEL_GREEN,
            'level_label' => 'Sin datos',
            'reasons' => ['El almacenamiento de presencia no respondió.'],
            'series' => array_fill_keys(SecurityMonitor::METRICS, array_fill(0, 30, 0)),
            'last_minute' => $zeros,
            'last_five' => $zeros,
            'events' => [],
            'offenders' => [],
            'targets' => [],
            'locks' => [],
            'blocked_users' => 0,
            'blocked_ips' => 0,
            'dismissed_ips' => 0,
        ];
    }
}
