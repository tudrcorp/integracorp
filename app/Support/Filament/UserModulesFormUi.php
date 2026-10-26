<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\Rol;

final class UserModulesFormUi
{
    public static function stylesView(): string
    {
        return 'filament.business.users.partials.modules-form-styles';
    }

    /**
     * Qué abre cada módulo, qué exige y si es un acceso especial. Es la misma
     * regla que aplica {@see \App\Models\User::canAccessPanel()}: si cambia
     * allí, cambia aquí.
     *
     * @var array<string, array{kind: string, path?: string, icon: string, accent: string, description: string, internal_email?: bool, requires?: string, confirm?: string}>
     */
    private const MODULES = [
        'NEGOCIOS' => ['kind' => 'panel', 'path' => '/business', 'icon' => 'heroicon-o-briefcase', 'accent' => '#075985', 'description' => 'Cotizaciones, afiliaciones, agentes, planes y configuración comercial.', 'internal_email' => true],
        'OPERACIONES' => ['kind' => 'panel', 'path' => '/operations', 'icon' => 'heroicon-o-cog-6-tooth', 'accent' => '#16a34a', 'description' => 'Servicios médicos, proveedores, inventario y órdenes de servicio.', 'internal_email' => true],
        'ADMINISTRACION' => ['kind' => 'panel', 'path' => '/administration', 'icon' => 'heroicon-o-building-library', 'accent' => '#2563eb', 'description' => 'Cobranza, ventas, comisiones, RRHH y nómina.', 'internal_email' => true],
        'MARKETING' => ['kind' => 'panel', 'path' => '/marketing', 'icon' => 'heroicon-o-megaphone', 'accent' => '#d97706', 'description' => 'Notificaciones masivas, cumpleaños, eventos y listas de contacto.'],
        'TELEMEDICINA' => ['kind' => 'panel', 'path' => '/telemedicina', 'icon' => 'heroicon-o-heart', 'accent' => '#005ca9', 'description' => 'Panel médico: casos, consultas, recetas e informes.'],
        'PROYECTOS' => ['kind' => 'panel', 'path' => '/projects', 'icon' => 'heroicon-o-rectangle-group', 'accent' => '#3b82f6', 'description' => 'Proyectos, sprints, actividades y tablero kanban.', 'internal_email' => true],
        'METRICAS' => ['kind' => 'panel', 'path' => '/metrics', 'icon' => 'heroicon-o-chart-bar-square', 'accent' => '#0f766e', 'description' => 'Panel corporativo de indicadores para la directiva.', 'internal_email' => true, 'requires' => 'SUPERADMIN'],
        'SUPERADMIN' => ['kind' => 'special', 'icon' => 'heroicon-o-shield-exclamation', 'accent' => '#dc2626', 'description' => 'Acceso total: ve todos los menús sin importar sus permisos, administra usuarios y entra a Métricas.', 'confirm' => 'SUPERADMIN da acceso total al sistema, incluida la administración de usuarios y permisos. ¿Seguro que esta persona lo necesita?'],
        'SISTEMAS' => ['kind' => 'special', 'icon' => 'heroicon-o-wrench-screwdriver', 'accent' => '#475569', 'description' => 'Soporte técnico: atiende los tickets de Helpdesk como equipo de sistemas. No abre ningún panel.'],
        'ATENMEDI' => ['kind' => 'special', 'icon' => 'heroicon-o-user-group', 'accent' => '#0891b2', 'description' => 'Personal de ATENMEDI: oculta en Operaciones las acciones que no le corresponden. No abre ningún panel.'],
        'MODERADOR-INTRANET' => ['kind' => 'special', 'icon' => 'heroicon-o-chat-bubble-left-right', 'accent' => '#64748b', 'description' => 'Sin uso actual: hoy ninguna pantalla lo revisa.'],
    ];

    /**
     * Módulos de la tabla `rols` con su ficha, en el orden en que se muestran:
     * primero los paneles de trabajo y después los accesos especiales.
     *
     * @return list<array{key: string, label: string, kind: string, path: ?string, icon: string, accent: string, description: string, internal_email: bool, requires: ?string, confirm: ?string}>
     */
    public static function moduleCatalog(): array
    {
        $catalog = [];

        foreach (Rol::query()->orderBy('name')->pluck('name') as $module) {
            if (! is_string($module) || trim($module) === '') {
                continue;
            }

            $meta = self::MODULES[$module] ?? ['kind' => 'special', 'icon' => 'heroicon-o-squares-2x2', 'accent' => '#64748b', 'description' => 'Módulo sin descripción registrada.'];

            $catalog[] = [
                'key' => $module,
                'label' => match ($module) {
                    'METRICAS' => 'Métricas',
                    'TELEMEDICINA' => 'Telemedicina',
                    'SUPERADMIN' => 'Superadministrador',
                    'SISTEMAS' => 'Sistemas',
                    'MODERADOR-INTRANET' => 'Moderador de intranet',
                    default => UserPermissionFormUi::moduleDisplayLabel($module),
                },
                'kind' => $meta['kind'],
                'path' => $meta['path'] ?? null,
                'icon' => $meta['icon'],
                'accent' => $meta['accent'],
                'description' => $meta['description'],
                'internal_email' => (bool) ($meta['internal_email'] ?? false),
                'requires' => $meta['requires'] ?? null,
                'confirm' => $meta['confirm'] ?? null,
            ];
        }

        $order = array_flip(array_keys(self::MODULES));
        usort($catalog, static fn (array $a, array $b): int => ($order[$a['key']] ?? PHP_INT_MAX) <=> ($order[$b['key']] ?? PHP_INT_MAX) ?: strcmp($a['key'], $b['key']));

        return $catalog;
    }
}
