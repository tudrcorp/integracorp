<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use App\Filament\Business\Pages\LiveQueueCenter;
use Throwable;

/**
 * Convierte todo lo que mide el monitor en dos cosas fáciles de leer:
 *
 * - Tres semáforos: Seguridad, Colas y Errores.
 * - «Qué hacer ahora»: pocas tareas, la más urgente primero, cada una con qué
 *   pasa, por qué importa y un botón que lleva a resolverlo.
 *
 * No consulta nada por su cuenta: recibe lo que el monitor ya leyó.
 */
final class OperationsAdvisor
{
    public const MAX_ACTIONS = 6;

    /**
     * @param  array<string, mixed>  $security  SecuritySnapshot::build()
     * @param  array<string, mixed>|null  $queueReport  QueueHealth::measure()
     * @param  list<array<string, mixed>>  $errors  ErrorTracker::groups()
     * @return array{lights: array<string, array{level: string, label: string, detail: string}>, actions: list<array{severity: string, area: string, title: string, detail: string, cta: array{type: string, label: string, value: string}|null}>}
     */
    public static function advise(array $security, ?array $queueReport, array $errors): array
    {
        $actions = [
            ...self::queueActions($queueReport),
            ...self::errorActions($errors),
            ...self::securityActions($security),
        ];

        $rank = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($actions, static fn (array $a, array $b): int => $rank[$a['severity']] <=> $rank[$b['severity']]);

        return [
            'lights' => [
                'security' => self::securityLight($security),
                'queues' => self::queueLight($queueReport),
                'errors' => self::errorLight($errors),
            ],
            'actions' => array_slice($actions, 0, self::MAX_ACTIONS),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $report
     * @return list<array<string, mixed>>
     */
    public static function queueActions(?array $report): array
    {
        if ($report === null) {
            return [];
        }

        $actions = [];
        $workers = $report['workers'] ?? ['known' => false, 'alive' => []];
        $copyCommand = ['type' => 'copy', 'label' => 'Copiar comando', 'value' => (string) $report['worker_command']];

        if ($workers['known'] && $workers['alive'] === [] && (int) $report['pending_total'] > 0) {
            $actions[] = self::action('critical', 'Colas', 'Ningún worker está procesando las colas', $report['pending_total'].' trabajos esperan y ningún worker dio señal de vida en el último minuto. Nada en cola se está ejecutando: WhatsApp, PDFs, renovaciones.', $copyCommand);
        } else {
            foreach ($report['queues'] as $queue) {
                if ($queue['status'] === 'unattended') {
                    $actions[] = self::action('critical', 'Colas', 'Nadie atiende la cola «'.$queue['name'].'»', $queue['advice'], $copyCommand);
                } elseif ($queue['status'] === 'stuck') {
                    $actions[] = self::action('critical', 'Colas', 'Cola «'.$queue['name'].'» atascada', $queue['advice'], $copyCommand);
                } elseif ($queue['status'] === 'zombie') {
                    $actions[] = self::action('warning', 'Colas', 'Trabajos colgados en «'.$queue['name'].'»', $queue['advice'], self::link('Ver colas', 'colas'));
                }
            }
        }

        if (! $workers['known'] && (int) $report['pending_total'] > 0) {
            $actions[] = self::action('info', 'Colas', 'Los workers aún no reportan su latido', 'Reinícielos una vez para que empiecen a reportarse; hasta entonces no se puede saber qué cola atiende cada uno.', ['type' => 'copy', 'label' => 'Copiar comando', 'value' => 'php artisan queue:restart']);
        }

        foreach (array_slice($report['failed']['groups'] ?? [], 0, 3) as $group) {
            $severity = match ($group['diagnosis']['action']) {
                FailureDiagnosis::ACTION_DELETE => 'info',
                default => $group['days_since_last'] < 1 ? 'warning' : 'info',
            };

            $actions[] = self::action(
                $severity,
                'Fallidos',
                $group['count'].' '.($group['count'] === 1 ? 'fallido' : 'fallidos').' de '.$group['job'].': '.$group['diagnosis']['title'],
                $group['diagnosis']['advice'],
                self::link($group['diagnosis']['action_label'], 'causas'),
            );
        }

        return $actions;
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     * @return list<array<string, mixed>>
     */
    public static function errorActions(array $errors): array
    {
        $actions = [];

        foreach ($errors as $error) {
            if (! in_array($error['status'], ['regression', 'new'], true)) {
                continue;
            }

            $actions[] = self::action(
                $error['status'] === 'regression' ? 'critical' : 'warning',
                'Errores',
                ($error['status'] === 'regression' ? 'Volvió un error resuelto: ' : 'Error nuevo: ').$error['short_class'].' en '.($error['origin'] ?: $error['location']),
                $error['count'].' '.($error['count'] === 1 ? 'vez' : 'veces').($error['users'] > 0 ? ' · '.$error['users'].' '.($error['users'] === 1 ? 'usuario afectado' : 'usuarios afectados') : '').' · último '.$error['last_ago'].'. '.$error['diagnosis']['title'].'.',
                self::link('Ver error', 'errores'),
            );

            if (count($actions) >= 3) {
                break;
            }
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $security
     * @return list<array<string, mixed>>
     */
    public static function securityActions(array $security): array
    {
        $confirmed = array_values(array_filter($security['offenders'] ?? [], static fn (array $offender): bool => ($offender['verdict'] ?? '') === IpThreatAssessment::CONFIRMED && ! ($offender['blocked'] ?? false)));

        if ($confirmed === []) {
            return [];
        }

        return [self::action(
            ($security['level'] ?? '') === SecuritySnapshot::LEVEL_RED ? 'critical' : 'warning',
            'Seguridad',
            count($confirmed).' '.(count($confirmed) === 1 ? 'IP es una amenaza confirmada' : 'IPs son amenazas confirmadas').' sin bloquear',
            implode(', ', array_slice(array_column($confirmed, 'ip'), 0, 4)).'. Revíselas en «IPs sospechosas» y muévalas a la lista negra.',
            ['type' => 'anchor', 'label' => 'Ver amenazas', 'value' => '#lsec-threats'],
        )];
    }

    /**
     * @param  array<string, mixed>  $security
     * @return array{level: string, label: string, detail: string}
     */
    private static function securityLight(array $security): array
    {
        return [
            'level' => (string) ($security['level'] ?? 'green'),
            'label' => (string) ($security['level_label'] ?? 'Normal'),
            'detail' => (string) (($security['reasons'] ?? [])[0] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $report
     * @return array{level: string, label: string, detail: string}
     */
    private static function queueLight(?array $report): array
    {
        if ($report === null) {
            return ['level' => 'amber', 'label' => 'Sin lectura', 'detail' => 'No se pudo medir las colas.'];
        }

        $workers = $report['workers'] ?? ['known' => false, 'alive' => []];
        $critical = ($workers['known'] && $workers['alive'] === [] && (int) $report['pending_total'] > 0)
            || ($report['unattended'] ?? []) !== []
            || ($report['stuck'] ?? []) !== [];
        $failedHour = (int) ($report['failed']['last_hour'] ?? 0);

        if ($critical) {
            $names = array_values(array_unique([...($report['unattended'] ?? []), ...($report['stuck'] ?? [])]));

            return ['level' => 'red', 'label' => 'Requiere acción', 'detail' => $names !== [] ? 'Sin atender: '.implode(', ', $names).'.' : 'Ningún worker vivo.'];
        }

        if ((int) ($report['zombies'] ?? 0) > 0 || $failedHour > 0) {
            return ['level' => 'amber', 'label' => 'Atención', 'detail' => $failedHour.' fallidos en la última hora'.((int) ($report['zombies'] ?? 0) > 0 ? ' · '.$report['zombies'].' colgados' : '').'.'];
        }

        $alive = count($workers['alive']);

        return ['level' => 'green', 'label' => 'Al día', 'detail' => $report['pending_total'].' pendientes'.($workers['known'] ? ' · '.$alive.' '.($alive === 1 ? 'worker' : 'workers') : '').'.'];
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     * @return array{level: string, label: string, detail: string}
     */
    private static function errorLight(array $errors): array
    {
        $regressions = count(array_filter($errors, static fn (array $error): bool => $error['status'] === 'regression'));
        $new = count(array_filter($errors, static fn (array $error): bool => $error['status'] === 'new'));
        $recent = count(array_filter($errors, static fn (array $error): bool => $error['status'] !== 'resolved' && (int) $error['last_at'] >= time() - 600));

        return match (true) {
            $regressions > 0 => ['level' => 'red', 'label' => 'Regresión', 'detail' => $regressions.' '.($regressions === 1 ? 'error resuelto volvió' : 'errores resueltos volvieron').'.'],
            $recent > 0 => ['level' => 'red', 'label' => 'Errores ahora', 'detail' => $recent.' '.($recent === 1 ? 'tipo de error' : 'tipos de error').' en los últimos 10 min.'],
            $new > 0 => ['level' => 'amber', 'label' => 'Errores nuevos', 'detail' => $new.' '.($new === 1 ? 'error nuevo' : 'errores nuevos').' en 24 h.'],
            default => ['level' => 'green', 'label' => 'Sin errores', 'detail' => 'Nada nuevo en 24 h.'],
        };
    }

    /**
     * @param  array{type: string, label: string, value: string}|null  $cta
     * @return array{severity: string, area: string, title: string, detail: string, cta: array{type: string, label: string, value: string}|null}
     */
    private static function action(string $severity, string $area, string $title, string $detail, ?array $cta): array
    {
        return ['severity' => $severity, 'area' => $area, 'title' => $title, 'detail' => $detail, 'cta' => $cta];
    }

    /**
     * @return array{type: string, label: string, value: string}
     */
    private static function link(string $label, string $tab): array
    {
        try {
            $url = LiveQueueCenter::getUrl(['tab' => $tab]);
        } catch (Throwable) {
            $url = '/business/colas-y-errores?tab='.$tab;
        }

        return ['type' => 'link', 'label' => $label, 'value' => $url];
    }
}
