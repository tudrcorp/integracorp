<?php

declare(strict_types=1);

namespace App\Support\Integracorp;

/**
 * Catálogo de módulos (paneles Filament) expuestos en el hub de acceso principal.
 *
 * @phpstan-type HubModuleDefinition array{
 *     id: string,
 *     name: string,
 *     objective: string,
 *     image: string,
 *     route: string,
 *     sort: int,
 *     badge: string,
 *     tags: list<string>,
 * }
 */
final class IntegracorpHubModuleRegistry
{
    /**
     * @return list<HubModuleDefinition>
     */
    public static function definitions(): array
    {
        return [
            [
                'id' => 'admin',
                'name' => 'Administración general',
                'objective' => 'Configuración central, catálogos y supervisión transversal del ecosistema.',
                'image' => 'image/hub-modules/admin.png',
                'route' => 'filament.admin.pages.dashboard',
                'sort' => 5,
                'badge' => 'Central',
                'tags' => ['Catálogos', 'Supervisión', 'Sistema'],
            ],
            [
                'id' => 'business',
                'name' => 'Negocios',
                'objective' => 'Cotizaciones, planes, red comercial y desarrollo de nuevos negocios.',
                'image' => 'image/hub-modules/business.png',
                'route' => 'filament.business.pages.dashboard',
                'sort' => 10,
                'badge' => 'TDEC',
                'tags' => ['Cotizaciones', 'Planes', 'Red comercial'],
            ],
            [
                'id' => 'administration',
                'name' => 'Administración',
                'objective' => 'Cobranza, comisiones, nómina y control financiero operativo.',
                'image' => 'image/hub-modules/administration.jpg',
                'route' => 'filament.administration.pages.dashboard',
                'sort' => 20,
                'badge' => 'Finanzas',
                'tags' => ['Cobranza', 'Comisiones', 'Nómina'],
            ],
            [
                'id' => 'operations',
                'name' => 'Operaciones',
                'objective' => 'Coordinación médica, órdenes de servicio, red de proveedores e inventario.',
                'image' => 'image/hub-modules/operations.png',
                'route' => 'filament.operations.pages.dashboard',
                'sort' => 30,
                'badge' => 'Red AMD',
                'tags' => ['Coordinación', 'Órdenes', 'Inventario'],
            ],
            [
                'id' => 'telemedicina',
                'name' => 'Telemedicina',
                'objective' => 'Consultas remotas, casos clínicos, historias y documentación médica.',
                'image' => 'image/hub-modules/telemedicina.png',
                'route' => 'filament.telemedicina.pages.dashboard',
                'sort' => 40,
                'badge' => 'Clínico',
                'tags' => ['Consultas', 'Casos', 'Historias'],
            ],
            [
                'id' => 'marketing',
                'name' => 'Marketing',
                'objective' => 'Campañas, comunicaciones masivas, eventos y relación con afiliados.',
                'image' => 'image/hub-modules/marketing.png',
                'route' => 'filament.marketing.pages.dashboard',
                'sort' => 50,
                'badge' => 'Masivos',
                'tags' => ['Campañas', 'WhatsApp', 'Eventos'],
            ],
            [
                'id' => 'projects',
                'name' => 'Proyectos',
                'objective' => 'Gestión ágil interna: épicas, sprints, actividades y entregables.',
                'image' => 'image/hub-modules/projects.jpg',
                'route' => 'filament.projects.pages.dashboard',
                'sort' => 60,
                'badge' => 'Scrum',
                'tags' => ['Sprints', 'Kanban', 'Entregables'],
            ],
            [
                'id' => 'metrics',
                'name' => 'Métricas / KPI',
                'objective' => 'Indicadores de desempeño, analítica y mapas de actividad comercial.',
                'image' => 'image/hub-modules/metrics.png',
                'route' => 'filament.metrics.pages.dashboard',
                'sort' => 70,
                'badge' => 'KPI',
                'tags' => ['Analítica', 'Mapas', 'Indicadores'],
            ],
            [
                'id' => 'agents',
                'name' => 'Agentes',
                'objective' => 'Portal de la red comercial: agentes, agencias master y generales.',
                'image' => 'image/hub-modules/agents.png',
                'route' => 'filament.agents.pages.dashboard',
                'sort' => 80,
                'badge' => 'Red',
                'tags' => ['Agentes', 'Agencias', 'Comisiones'],
            ],
        ];
    }

    /**
     * @return HubModuleDefinition|null
     */
    public static function find(string $panelId): ?array
    {
        foreach (self::definitions() as $definition) {
            if ($definition['id'] === $panelId) {
                return $definition;
            }
        }

        return null;
    }
}
