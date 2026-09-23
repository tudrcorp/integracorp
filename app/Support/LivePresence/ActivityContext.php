<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Traduce una petición a "dónde está y qué hace" el usuario.
 *
 * Las acciones de Filament y Livewire llegan todas a `/livewire/update`: el
 * panel y la página salen del Referer y la acción de los componentes y métodos
 * llamados en el cuerpo de la petición.
 */
final class ActivityContext
{
    public const PWA = 'pwa';

    /**
     * @var array<string, string>
     */
    public const PANELS = [
        'admin' => 'Admin',
        'business' => 'Negocios',
        'operations' => 'Operaciones',
        'administration' => 'Administración',
        'marketing' => 'Marketing',
        'agents' => 'Agentes',
        'master' => 'Agencia Master',
        'general' => 'Agencia General',
        'telemedicina' => 'Telemedicina',
        'projects' => 'Proyectos',
        'metrics' => 'Métricas',
        self::PWA => 'PWA',
    ];

    /**
     * Llamadas automáticas de Livewire y Filament (refrescos, sondeos, gráficas):
     * ocurren solas cada pocos segundos y no son acciones del usuario.
     *
     * @var list<string>
     */
    private const AUTOMATIC_METHODS = [
        '$refresh', '$set', '$sync', '$commit', '$toggle', '__dispatch', '__lazyLoad', 'render',
        'updateChartData', 'refreshData', 'loadData', 'loadMore', 'markAsRead', 'markAllNotificationsAsRead',
        'getNotifications', 'updated', 'hydrate', 'dehydrate', 'mount', 'boot', 'callSchemaComponentMethod',
    ];

    /** Patrones de nombres de métodos que solo se usan en sondeos periódicos. */
    private const AUTOMATIC_PATTERNS = ['/^poll/i', '/heartbeat$/i', '/^refresh/i', '/^tick$/i', '/^check(For)?Updates?$/i', '/^sync/i'];

    /**
     * Métodos de Filament y Livewire traducidos a lo que el usuario hizo.
     * `%s` se reemplaza por el texto del botón pulsado o el nombre de la acción.
     *
     * @var array<string, string>
     */
    private const METHOD_LABELS = [
        'mountAction' => 'Abrió «%s»',
        'mountTableAction' => 'Abrió «%s» en la tabla',
        'mountTableBulkAction' => 'Abrió «%s» para varios registros',
        'mountFormComponentAction' => 'Abrió «%s»',
        'mountInfolistAction' => 'Abrió «%s»',
        'callMountedAction' => 'Confirmó «%s»',
        'callMountedTableAction' => 'Confirmó «%s»',
        'callMountedTableBulkAction' => 'Confirmó «%s» para varios registros',
        'callAction' => 'Ejecutó «%s»',
        'unmountAction' => 'Cerró la ventana',
        'unmountTableAction' => 'Cerró la ventana',
        'unmountTableBulkAction' => 'Cerró la ventana',
        'unmountFormComponentAction' => 'Cerró la ventana',
        'save' => 'Guardó los cambios',
        'create' => 'Creó el registro',
        'createAnother' => 'Creó el registro y abrió otro',
        'delete' => 'Eliminó el registro',
        'gotoPage' => 'Cambió de página en la tabla',
        'nextPage' => 'Pasó a la siguiente página de la tabla',
        'previousPage' => 'Volvió a la página anterior de la tabla',
        'sortTable' => 'Ordenó la tabla',
        'resetTableFiltersForm' => 'Quitó los filtros de la tabla',
        'applyTableFilters' => 'Aplicó filtros a la tabla',
        'removeTableFilter' => 'Quitó un filtro de la tabla',
        'removeTableFilters' => 'Quitó los filtros de la tabla',
        'toggleTableReordering' => 'Activó el reordenamiento de la tabla',
        'logout' => 'Cerró sesión',
        'authenticate' => 'Inició sesión',
        'login' => 'Inició sesión',
        'nextWizardStep' => 'Avanzó al siguiente paso',
        'previousWizardStep' => 'Volvió al paso anterior',
    ];

    public static function isLivewireUpdate(Request $request): bool
    {
        return $request->isMethod('POST') && Str::endsWith($request->path(), 'livewire/update');
    }

    /**
     * Ruta de la página que el usuario tiene abierta, sin parámetros de consulta.
     */
    public static function pagePath(Request $request): string
    {
        if (self::isLivewireUpdate($request)) {
            $referer = (string) $request->headers->get('referer', '');
            $path = (string) (parse_url($referer, PHP_URL_PATH) ?? '');

            return '/'.ltrim($path, '/');
        }

        return '/'.ltrim($request->path(), '/');
    }

    public static function isMonitorPage(string $pagePath): bool
    {
        return str_ends_with(rtrim($pagePath, '/'), '/monitor-en-vivo');
    }

    public static function panelFor(string $pagePath): string
    {
        $segment = strtolower((string) Str::of($pagePath)->ltrim('/')->before('/'));

        if ($segment === 'app') {
            return self::PWA;
        }

        return array_key_exists($segment, self::PANELS) ? $segment : 'web';
    }

    public static function panelLabel(string $panel): string
    {
        return self::PANELS[$panel] ?? 'Sitio web';
    }

    /**
     * Nombre legible de la página a partir de su ruta: "/business/affiliation-corporates/15" → "Affiliation corporates · 15".
     */
    public static function pageLabel(string $pagePath): string
    {
        $resolved = PageLabelResolver::label($pagePath);

        if ($resolved !== null && $resolved !== '') {
            return Str::limit($resolved, 90);
        }

        $segments = array_values(array_filter(explode('/', trim($pagePath, '/'))));

        if ($segments === []) {
            return 'Inicio';
        }

        if (array_key_exists(strtolower($segments[0]), self::PANELS) || strtolower($segments[0]) === 'app') {
            array_shift($segments);
        }

        if ($segments === []) {
            return 'Escritorio';
        }

        $words = array_map(
            static fn (string $segment): string => ctype_digit($segment) ? '#'.$segment : Str::of($segment)->replace(['-', '_'], ' ')->ucfirst()->toString(),
            array_slice($segments, 0, 3),
        );

        return Str::limit(implode(' · ', $words), 90);
    }

    /**
     * Qué hizo el usuario en una petición de Livewire, en español.
     * null si todo fue automático (refrescos, sondeos, gráficas) o solo tecleo.
     */
    public static function livewireAction(Request $request): ?string
    {
        if (! self::isLivewireUpdate($request)) {
            return null;
        }

        $components = $request->input('components');

        if (! is_array($components)) {
            return null;
        }

        $clicked = self::clickedLabel($request);
        $actions = [];

        foreach (array_slice($components, 0, 5) as $component) {
            if (! is_array($component)) {
                continue;
            }

            foreach (is_array($component['calls'] ?? null) ? $component['calls'] : [] as $call) {
                $described = self::describeCall(
                    (string) ($call['method'] ?? ''),
                    is_array($call['params'] ?? null) ? $call['params'] : [],
                    $clicked,
                );

                if ($described !== null) {
                    $actions[] = $described;
                }
            }

            foreach (self::describeUpdates(is_array($component['updates'] ?? null) ? $component['updates'] : []) as $described) {
                $actions[] = $described;
            }
        }

        $actions = array_values(array_unique($actions));

        return $actions === [] ? null : Str::limit(implode(' · ', $actions), 160);
    }

    public static function isAutomaticMethod(string $method): bool
    {
        if ($method === '' || str_starts_with($method, '$') || str_starts_with($method, '__')
            || in_array($method, self::AUTOMATIC_METHODS, true)) {
            return true;
        }

        foreach (self::AUTOMATIC_PATTERNS as $pattern) {
            if (preg_match($pattern, $method) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Texto del botón que el usuario pulsó (lo envía el latido del navegador en
     * el encabezado X-Presence-Click, solo si el clic fue hace menos de 2 s).
     */
    public static function clickedLabel(Request $request): ?string
    {
        $raw = (string) $request->headers->get('X-Presence-Click', '');

        if ($raw === '') {
            return null;
        }

        $label = trim(preg_replace('/\s+/u', ' ', strip_tags(rawurldecode($raw))) ?? '');

        return $label === '' ? null : Str::limit($label, 60);
    }

    /**
     * Descripción de un archivo que el usuario abrió o descargó.
     */
    public static function downloadLabel(string $path, ?string $contentType, ?string $fromPage): string
    {
        $contentType = strtolower((string) $contentType);

        $kind = match (true) {
            str_contains($contentType, 'pdf') => 'Abrió un PDF',
            str_contains($contentType, 'spreadsheet'), str_contains($contentType, 'excel'), str_contains($contentType, 'csv') => 'Descargó una hoja de cálculo',
            str_contains($contentType, 'zip') => 'Descargó un ZIP',
            str_starts_with($contentType, 'image/') => 'Abrió una imagen',
            default => 'Abrió o descargó un archivo',
        };

        $detail = self::humanizeRoute(PageLabelResolver::routeName($path) ?? $path);
        $from = $fromPage !== null && $fromPage !== '' ? ' desde '.$fromPage : '';

        return Str::limit($kind.($detail !== '' ? ' ('.$detail.')' : '').$from, 160);
    }

    /**
     * @param  array<int, mixed>  $params
     */
    private static function describeCall(string $method, array $params, ?string $clicked): ?string
    {
        if (self::isAutomaticMethod($method)) {
            return null;
        }

        if (isset(self::METHOD_LABELS[$method])) {
            $template = self::METHOD_LABELS[$method];

            if (! str_contains($template, '%s')) {
                return $template;
            }

            $first = $params[0] ?? null;
            $name = $clicked ?? (is_string($first) && $first !== '' ? self::humanize($first) : 'la acción');

            return sprintf($template, $name);
        }

        return $clicked !== null ? 'Pulsó «'.$clicked.'»' : 'Ejecutó «'.self::humanize($method).'»';
    }

    /**
     * Cambios de propiedades que son acciones visibles del usuario. El tecleo en
     * formularios no se registra: solo búsquedas, filtros, pestañas y paginación.
     *
     * @param  array<string, mixed>  $updates
     * @return list<string>
     */
    private static function describeUpdates(array $updates): array
    {
        $described = [];

        foreach ($updates as $property => $value) {
            $property = (string) $property;
            $text = is_scalar($value) ? trim((string) $value) : '';

            $label = match (true) {
                in_array($property, ['tableSearch', 'search'], true) => $text !== '' ? 'Buscó «'.Str::limit($text, 40).'»' : 'Limpió la búsqueda',
                str_starts_with($property, 'tableFilters') => 'Cambió los filtros de la tabla',
                $property === 'activeTab' => $text !== '' ? 'Cambió a la pestaña «'.self::humanize($text).'»' : 'Cambió de pestaña',
                $property === 'tableRecordsPerPage' => 'Mostró '.$text.' registros por página',
                str_starts_with($property, 'toggledTableColumns') => 'Cambió las columnas visibles',
                str_starts_with($property, 'tableGrouping') => 'Agrupó la tabla',
                default => null,
            };

            if ($label !== null) {
                $described[] = $label;
            }
        }

        return $described;
    }

    /**
     * "report_suppliers" → "Report suppliers"; "changePaymentFrequency" → "Change payment frequency".
     */
    private static function humanize(string $name): string
    {
        return (string) Str::of($name)->snake()->replace(['_', '-', '.'], ' ')->squish()->ucfirst();
    }

    private static function humanizeRoute(string $routeOrPath): string
    {
        $words = [
            'report' => 'reporte', 'preview' => 'vista previa', 'download' => 'descarga', 'export' => 'exportación',
            'pdf' => 'PDF', 'csv' => 'CSV', 'ficha' => 'ficha', 'print' => 'impresión', 'card' => 'tarjeta',
            'certificate' => 'certificado', 'invoice' => 'factura', 'receipt' => 'recibo', 'quote' => 'cotización',
        ];

        $segments = array_values(array_filter(preg_split('/[.\/]/', strtolower($routeOrPath)) ?: [], static fn (string $segment): bool => $segment !== '' && ! ctype_digit($segment)));
        $segments = array_slice($segments, -2);
        $translated = array_map(static fn (string $segment): string => $words[$segment] ?? str_replace(['-', '_'], ' ', $segment), $segments);

        return implode(' · ', $translated);
    }
}
