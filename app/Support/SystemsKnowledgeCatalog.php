<?php

declare(strict_types=1);

namespace App\Support;

final class SystemsKnowledgeCatalog
{
    public const STATUS_READY = 'ready';

    public const STATUS_COMING_SOON = 'coming_soon';

    /**
     * Catálogo escalable del panel de Sistemas.
     * Para agregar un recurso, solo suma un item en la sección correspondiente.
     *
     * @return list<array{
     *     id: string,
     *     title: string,
     *     subtitle: string,
     *     eyebrow: string,
     *     icon: string,
     *     items: list<array{
     *         id: string,
     *         title: string,
     *         subtitle: string,
     *         url: string|null,
     *         status: string,
     *         requires_auth: bool
     *     }>
     * }>
     */
    public static function sections(): array
    {
        return [
            [
                'id' => 'presentaciones',
                'title' => 'Presentaciones',
                'subtitle' => 'Sesiones técnicas internas del equipo de sistemas.',
                'eyebrow' => 'Capacitación',
                'icon' => 'presentations',
                'items' => self::presentationItems(),
            ],
            [
                'id' => 'manuales',
                'title' => 'Manuales de Tecnología',
                'subtitle' => 'Guías operativas y documentación técnica para el día a día.',
                'eyebrow' => 'Documentación',
                'icon' => 'manuals',
                'items' => self::manualItems(),
            ],
        ];
    }

    /**
     * @return list<array{id: string, title: string, subtitle: string, url: string|null, status: string, requires_auth: bool}>
     */
    public static function presentationItems(): array
    {
        return [
            [
                'id' => 'scrum',
                'title' => 'Scrum (desarrollo de apps)',
                'subtitle' => 'Metodología ágil aplicada al desarrollo',
                'url' => '/scrum-desarrollo-apps',
                'status' => self::STATUS_READY,
                'requires_auth' => true,
            ],
            [
                'id' => 'avances',
                'title' => 'Última presentación (avances tecnológicos)',
                'subtitle' => 'Paneles, API, infraestructura y futuro',
                'url' => '/avances-tecnologicos',
                'status' => self::STATUS_READY,
                'requires_auth' => true,
            ],
        ];
    }

    /**
     * Placeholder listo para escalar: agrega URLs cuando existan los manuales.
     *
     * @return list<array{id: string, title: string, subtitle: string, url: string|null, status: string, requires_auth: bool}>
     */
    public static function manualItems(): array
    {
        return [
            // Ejemplo de cómo sumar un manual listo:
            // [
            //     'id' => 'manual-operaciones',
            //     'title' => 'Manual del Panel de Operaciones',
            //     'subtitle' => 'Flujos, roles y buenas prácticas',
            //     'url' => '/manuales/operaciones',
            //     'status' => self::STATUS_READY,
            //     'requires_auth' => true,
            // ],
        ];
    }

    /**
     * @return list<array{id: string, title: string, subtitle: string, url: string|null, status: string, requires_auth: bool, section_id: string}>
     */
    public static function allItems(): array
    {
        $items = [];

        foreach (self::sections() as $section) {
            foreach ($section['items'] as $item) {
                $items[] = [
                    ...$item,
                    'section_id' => $section['id'],
                ];
            }
        }

        return $items;
    }

    /**
     * @return array{id: string, title: string, subtitle: string, url: string|null, status: string, requires_auth: bool, section_id: string}|null
     */
    public static function findByUrl(string $path): ?array
    {
        $normalized = self::normalizePath($path);

        foreach (self::allItems() as $item) {
            if (! is_string($item['url']) || $item['url'] === '') {
                continue;
            }

            if (self::normalizePath($item['url']) === $normalized) {
                return $item;
            }
        }

        return null;
    }

    public static function isProtectedReadyPath(string $path): bool
    {
        $item = self::findByUrl($path);

        return $item !== null
            && $item['status'] === self::STATUS_READY
            && ($item['requires_auth'] ?? true) === true
            && filled($item['url']);
    }

    public static function normalizePath(string $path): string
    {
        $parsed = parse_url($path, PHP_URL_PATH);

        return '/'.ltrim(is_string($parsed) && $parsed !== '' ? $parsed : $path, '/');
    }

    /**
     * Compatibilidad con el gate/listados previos de presentaciones.
     *
     * @return list<array{id: string, title: string, subtitle: string, path: string}>
     */
    public static function presentationPaths(): array
    {
        return collect(self::presentationItems())
            ->filter(fn (array $item): bool => $item['status'] === self::STATUS_READY && filled($item['url']))
            ->map(fn (array $item): array => [
                'id' => $item['id'],
                'title' => $item['title'],
                'subtitle' => $item['subtitle'],
                'path' => (string) $item['url'],
            ])
            ->values()
            ->all();
    }
}
