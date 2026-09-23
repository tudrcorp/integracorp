<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

/**
 * Veredicto de una IP sospechosa para el analista: qué tan seguro es que sea
 * una amenaza y por qué. No decide nada por sí solo: recomienda, y el analista
 * bloquea o descarta desde el monitor.
 *
 * El puntaje acumulado mezcla señales débiles (un 404, un formulario vencido)
 * con señales que ningún usuario legítimo produce (pedir /.env, usar sqlmap).
 * Aquí se separan:
 *
 * - Señal dura: ruta de escáner, herramienta de ataque, fuerza bruta, relleno
 *   de credenciales o inundación.
 * - Señal débil: logins fallidos repetidos, ráfagas de 404, cliente automatizado.
 * - Atenuante: la IP tuvo un login correcto hace poco o tiene sesiones abiertas.
 *   En Venezuela muchas conexiones comparten IP (CGNAT): una IP con usuarios
 *   legítimos se trata con cuidado aunque desde ella llegue un ataque.
 *
 * Es una clase pura: recibe la evidencia ya leída, así se prueba sin Redis.
 */
final class IpThreatAssessment
{
    public const CONFIRMED = 'confirmed';

    public const POSSIBLE = 'possible';

    public const BENIGN = 'benign';

    public const LABELS = [
        self::CONFIRMED => 'Amenaza confirmada',
        self::POSSIBLE => 'Posible amenaza',
        self::BENIGN => 'Probable falso positivo',
    ];

    /** Orden en la tabla: primero lo que hay que atender. */
    public const RANK = [self::CONFIRMED => 0, self::POSSIBLE => 1, self::BENIGN => 2];

    /** Logins fallidos desde una IP a partir de los cuales es una señal débil. */
    private const FAILED_LOGINS_SIGNAL = 3;

    /** Páginas inexistentes en 24 h a partir de las cuales es una señal débil. */
    private const NOT_FOUND_SIGNAL = 15;

    /**
     * @param  array{
     *     tags?: list<string>,
     *     failed_logins?: int,
     *     accounts_tried?: int,
     *     scanner_path?: string,
     *     user_agent?: string,
     *     not_found_peak?: int,
     *     flood_peak?: int,
     *     hard_at?: int,
     *     counters?: array<string, int>,
     *     legitimate?: array{at: int, account: string, logins: int}|null,
     *     sessions?: list<string>,
     *     dismissed?: array{at: int, until: int, by: string, note: string}|null,
     * }  $evidence
     * @return array{verdict: string, label: string, reasons: list<string>, mitigations: list<string>, hard: bool, dismissed: bool}
     */
    public static function assess(array $evidence): array
    {
        $tags = (array) ($evidence['tags'] ?? []);
        $counters = (array) ($evidence['counters'] ?? []);
        $failedLogins = (int) ($evidence['failed_logins'] ?? 0);
        $accountsTried = (int) ($evidence['accounts_tried'] ?? 0);
        $scannerPath = (string) ($evidence['scanner_path'] ?? '');
        $sessions = (array) ($evidence['sessions'] ?? []);
        $legitimate = $evidence['legitimate'] ?? null;

        /** Señales que ningún usuario legítimo produce, aunque comparta IP con otros. */
        $unmistakable = [];

        if ($scannerPath !== '') {
            $unmistakable[] = 'Pidió «/'.ltrim($scannerPath, '/').'», una ruta que solo buscan los escáneres.';
        }

        if (in_array('herramienta de ataque', $tags, true)) {
            $unmistakable[] = 'Usa una herramienta de ataque o escaneo como navegador.';
        }

        /** Señales duras que, desde una IP compartida, pueden ser de un solo usuario entre muchos. */
        $attack = [];

        if (in_array('fuerza bruta', $tags, true)) {
            $attack[] = 'Fuerza bruta: '.$failedLogins.' logins fallidos en pocos minutos.';
        }

        if (in_array('relleno de credenciales', $tags, true)) {
            $attack[] = 'Probó '.$accountsTried.' cuentas distintas (relleno de credenciales).';
        }

        if (in_array('inundación', $tags, true)) {
            $attack[] = 'Inundación: '.((int) ($evidence['flood_peak'] ?? 0) ?: 'cientos de').' peticiones en un minuto.';
        }

        $weak = [];

        if ($attack === [] && $failedLogins >= self::FAILED_LOGINS_SIGNAL) {
            $weak[] = $failedLogins.' logins fallidos'.($accountsTried > 1 ? ' en '.$accountsTried.' cuentas' : '').'.';
        }

        if ($scannerPath === '' && in_array('escáner', $tags, true)) {
            $weak[] = 'Ráfaga de '.((int) ($evidence['not_found_peak'] ?? 0) ?: 'muchas').' páginas inexistentes en un minuto.';
        } elseif (($counters['not_found'] ?? 0) >= self::NOT_FOUND_SIGNAL) {
            $weak[] = $counters['not_found'].' páginas inexistentes en 24 h.';
        }

        if (in_array('bot', $tags, true)) {
            $weak[] = 'Cliente automatizado (sin navegador).';
        }

        $mitigations = [];

        if ($sessions !== []) {
            $mitigations[] = 'Tiene '.count($sessions).' '.(count($sessions) === 1 ? 'sesión abierta' : 'sesiones abiertas').' ahora: '.implode(', ', array_slice(array_unique($sessions), 0, 3)).'.';
        }

        if ($legitimate !== null) {
            $mitigations[] = 'Login correcto desde esta IP '.LiveActivitySnapshot::ago(max(0, time() - (int) $legitimate['at']))
                .(($legitimate['account'] ?? '') !== '' ? ' ('.$legitimate['account'].')' : '').'.';
        }

        $isLegitimate = $mitigations !== [];
        $hard = $unmistakable !== [] || $attack !== [];

        $dismissal = $evidence['dismissed'] ?? null;
        $dismissed = $dismissal !== null && (int) ($dismissal['at'] ?? 0) >= (int) ($evidence['hard_at'] ?? 0);

        $verdict = match (true) {
            $unmistakable !== [] => self::CONFIRMED,
            $attack !== [] && ! $isLegitimate => self::CONFIRMED,
            $attack !== [] => self::POSSIBLE,
            $weak !== [] && ! $isLegitimate => self::POSSIBLE,
            default => self::BENIGN,
        };

        $reasons = [...$unmistakable, ...$attack, ...$weak];

        if ($attack !== [] && $isLegitimate && $unmistakable === []) {
            $reasons[] = 'Desde esta IP también entran usuarios legítimos: puede ser una IP compartida.';
        }

        if ($reasons === []) {
            $reasons[] = self::noiseSummary($counters, $failedLogins);
        }

        return [
            'verdict' => $verdict,
            'label' => self::LABELS[$verdict],
            'reasons' => $reasons,
            'mitigations' => $mitigations,
            'hard' => $hard,
            'dismissed' => $dismissed,
        ];
    }

    /**
     * Duración recomendada (minutos) según el veredicto: las IPs residenciales
     * cambian de dueño, así que nada es permanente por defecto.
     */
    public static function suggestedMinutes(string $verdict): int
    {
        return $verdict === self::CONFIRMED ? 10080 : 1440;
    }

    /**
     * @param  array<string, int>  $counters
     */
    private static function noiseSummary(array $counters, int $failedLogins): string
    {
        $parts = array_filter([
            $failedLogins > 0 ? $failedLogins.' '.($failedLogins === 1 ? 'login fallido' : 'logins fallidos') : null,
            ($counters['not_found'] ?? 0) > 0 ? $counters['not_found'].' páginas inexistentes' : null,
            ($counters['csrf'] ?? 0) > 0 ? $counters['csrf'].' formularios vencidos (419)' : null,
            ($counters['throttled'] ?? 0) > 0 ? $counters['throttled'].' frenadas por límite (429)' : null,
            ($counters['forbidden'] ?? 0) > 0 ? $counters['forbidden'].' accesos denegados (403)' : null,
        ]);

        return $parts === []
            ? 'Solo errores sueltos, sin señales de ataque.'
            : 'Solo ruido: '.implode(', ', $parts).'. Es lo normal en un usuario que se equivoca o deja la página abierta.';
    }
}
