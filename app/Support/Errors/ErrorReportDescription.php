<?php

declare(strict_types=1);

namespace App\Support\Errors;

use Carbon\CarbonImmutable;

/**
 * Descripción del ticket que se abre desde la página de error: primero qué le
 * pasó a quién y dónde, luego un diagnóstico con la causa probable y al final
 * los datos técnicos. Pensada para que Sistemas actúe sin volver a preguntar.
 *
 * Todo valor que viene del contexto o del usuario se escapa.
 */
final class ErrorReportDescription
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array{name: string, email: string, id: int, departments: list<string>}  $reporter
     * @param  array{screen: string|null, record: string|null, permission: array{names: list<string>, slugs: list<string>, module: string|null, has: bool, super_admin_only: bool}|null}  $insight
     */
    public static function html(array $context, array $reporter, ?string $note, array $insight): string
    {
        $status = (int) $context['status'];
        $label = ErrorPage::shortLabel($status);
        $title = ErrorPage::content($status)['title'];
        $panel = filled($context['panel'] ?? null) ? ErrorPage::panelLabel((string) $context['panel']) : null;
        $where = $insight['screen'] ?? (string) ($context['url'] ?? '');
        $occurredAt = CarbonImmutable::createFromTimestamp((int) $context['at'], (string) config('app.timezone', 'America/Caracas'));

        $summary = e($reporter['name']).' recibió el <strong>error '.$status.' ('.e($label).')</strong> en '
            .'<strong>'.e($where).'</strong>'
            .($insight['record'] !== null ? ' (registro '.e($insight['record']).')' : '')
            .($panel !== null ? ', panel '.e($panel) : '')
            .'.';

        $note = trim((string) $note);

        $html = '<p><strong>Reporte automático desde la página de error.</strong></p>'
            .'<p>'.$summary.'</p>'
            .'<p><strong>Lo que dice el usuario</strong></p>'
            .($note !== '' ? '<p>'.nl2br(e($note)).'</p>' : '<p><em>No agregó comentarios.</em></p>')
            .'<p><strong>Diagnóstico</strong></p>'
            .self::diagnosis($context, $insight)
            .'<p><strong>Qué pasó</strong></p>'
            .self::list([
                'Error' => $status.' — '.$label,
                'Lo que vio el usuario' => '«'.$title.'»',
                'Pantalla' => $insight['screen'],
                'Registro' => $insight['record'],
                'Panel' => $panel,
                'Fecha y hora' => $occurredAt->format('d/m/Y h:i:s a'),
                'Mensaje mostrado' => $context['detail'] ?? null,
            ])
            .'<p><strong>Quién</strong></p>'
            .self::list([
                'Usuario' => $reporter['name'].' · '.$reporter['email'].' · ID '.$reporter['id'],
                'Departamentos' => $reporter['departments'] !== [] ? implode(', ', $reporter['departments']) : 'Sin departamento',
            ])
            .'<p><strong>Datos técnicos</strong></p>'
            .self::list([
                'Dirección' => ($context['method'] ?? 'GET').' '.($context['url'] ?? ''),
                'Servidor' => $context['host'] ?? null,
                'Ruta interna' => $context['route'] ?? null,
                'Componente' => $context['action'] ?? null,
                'Parámetros' => self::params($context['params'] ?? []),
                'Venía de' => $context['referer'] ?? null,
                'Navegador' => self::browser((string) ($context['ua'] ?? '')),
                'IP' => $context['ip'] ?? null,
            ]);

        $exception = $context['exception'] ?? null;

        if (is_array($exception) && ($exception['frames'] ?? []) !== []) {
            $html .= '<p><strong>Traza (solo nuestro código)</strong></p><pre>'.e(implode("\n", $exception['frames'])).'</pre>';
        }

        return $html;
    }

    /**
     * Causa probable según el código, con el siguiente paso para resolverlo.
     *
     * @param  array<string, mixed>  $context
     * @param  array{screen: string|null, record: string|null, permission: array{names: list<string>, slugs: list<string>, module: string|null, has: bool, super_admin_only: bool}|null}  $insight
     */
    private static function diagnosis(array $context, array $insight): string
    {
        $status = (int) $context['status'];
        $permission = $insight['permission'];

        if ($status === 403) {
            if ($permission === null) {
                return '<p>La pantalla no está en el registro de permisos de menú (<code>DepartmentNavigationPermissionRegistry</code>). '
                    .'El bloqueo viene de su propia regla de acceso: revisar <code>canAccess</code> / <code>canView</code> de '
                    .e((string) ($context['action'] ?? 'la página')).'.</p>';
            }

            if ($permission['super_admin_only']) {
                return '<p>Esta pantalla es <strong>solo para SUPERADMIN</strong> y el usuario no lo es. Si debe verla, hay que darle el departamento SUPERADMIN o mover la pantalla fuera de esa lista.</p>';
            }

            $names = $permission['names'] !== [] ? '«'.implode('», «', $permission['names']).'»' : 'sin nombre registrado';
            $technical = 'slug: '.implode(', ', $permission['slugs']).($permission['module'] ? ' · módulo: '.$permission['module'] : '');

            if (! $permission['has']) {
                return '<p>La pantalla exige el permiso <strong>'.e($names).'</strong> ('.e($technical).') y '
                    .'<strong>el usuario no lo tiene asignado</strong>. Si le corresponde, asignárselo desde la gestión de permisos del usuario.</p>';
            }

            return '<p>El usuario <strong>sí tiene</strong> el permiso de la pantalla ('.e($names).', '.e($technical).'). '
                .'El bloqueo viene de otra regla: departamento del panel, visibilidad del registro (p. ej. por proveedor) o el estado del registro. '
                .'Revisar <code>canView</code> / <code>canAccess</code> del recurso.</p>';
        }

        if ($status === 404) {
            $from = filled($context['referer'] ?? null)
                ? ' Venía de <code>'.e((string) $context['referer']).'</code>: si ese enlace es del sistema, apunta a algo eliminado o a una ruta vieja.'
                : ' Llegó sin página anterior (enlace directo, marcador o mensaje).';

            return '<p>La dirección o el registro no existe.'.$from.'</p>';
        }

        if ($status === 405) {
            return '<p>Se llamó la dirección con un método no admitido ('.e((string) ($context['method'] ?? '')).'). Suele ser un formulario o un enlace mal armado.</p>';
        }

        $exception = $context['exception'] ?? null;

        if ($status >= 500 && is_array($exception)) {
            return '<p>Falla de código: <strong>'.e((string) $exception['class']).'</strong>: '.e((string) $exception['message']).'</p>'
                .self::list([
                    'Lanzado en' => $exception['location'] ?? null,
                    'Origen en nuestro código' => $exception['origin'] ?? null,
                    'Huella' => ($exception['fingerprint'] ?? '').' — buscarla en Negocios → Colas y errores para ver ocurrencias y usuarios afectados',
                ]);
        }

        if ($status >= 500) {
            return '<p>Error del servidor sin detalle de la falla. Revisar el log de Laravel a la fecha y hora indicadas.</p>';
        }

        return '<p>La solicitud fue rechazada por datos inválidos o incompletos.</p>';
    }

    /**
     * @param  array<string, string|null>  $items
     */
    private static function list(array $items): string
    {
        $rows = '';

        foreach ($items as $label => $value) {
            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            $rows .= '<li><strong>'.e($label).':</strong> '.e((string) $value).'</li>';
        }

        return $rows === '' ? '' : '<ul>'.$rows.'</ul>';
    }

    /**
     * @param  array<string, string>  $params
     */
    private static function params(array $params): ?string
    {
        if ($params === []) {
            return null;
        }

        return implode(', ', array_map(static fn (string $key, string $value): string => $key.'='.$value, array_keys($params), $params));
    }

    /**
     * «Chrome 141 en macOS» a partir del User-Agent; si no se reconoce, el texto tal cual.
     */
    public static function browser(string $userAgent): ?string
    {
        if (trim($userAgent) === '') {
            return null;
        }

        $browser = match (true) {
            preg_match('/Edg\/([\d]+)/', $userAgent, $m) === 1 => 'Edge '.$m[1],
            preg_match('/OPR\/([\d]+)/', $userAgent, $m) === 1 => 'Opera '.$m[1],
            preg_match('/Firefox\/([\d]+)/', $userAgent, $m) === 1 => 'Firefox '.$m[1],
            preg_match('/Chrome\/([\d]+)/', $userAgent, $m) === 1 => 'Chrome '.$m[1],
            preg_match('/Version\/([\d.]+).*Safari/', $userAgent, $m) === 1 => 'Safari '.$m[1],
            default => null,
        };

        $system = match (true) {
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Mac OS X') => 'macOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        if ($browser === null) {
            return mb_strimwidth($userAgent, 0, 160, '…');
        }

        return $browser.($system !== null ? ' en '.$system : '');
    }
}
