<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Filament\Administration\Resources\Helpdesks\HelpdeskResource as AdministrationHelpdeskResource;
use App\Filament\Business\Resources\Helpdesks\HelpdeskResource as BusinessHelpdeskResource;
use App\Filament\Marketing\Resources\Helpdesks\HelpdeskResource as MarketingHelpdeskResource;
use App\Filament\Operations\Resources\Helpdesks\HelpdeskResource as OperationsHelpdeskResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Accesos directos tipo stepper (Negocios, Administración, Operaciones, Marketing, Proyectos):
 * crear ticket en el panel actual + accesos a otros módulos según departament y canAccessPanel; SUPERADMIN ve todos.
 */
final class InternalPanelsQuickNavigation
{
    /** @var list<string> */
    private const INTERNAL_HOST_PANEL_IDS = ['business', 'administration', 'operations', 'marketing', 'projects'];

    /**
     * @return list<array{kind: string, url: string, label: string, subtitle: string, tone: int, panel_id: ?string, accessible: bool, denied_message: ?string}>
     */
    public static function navigationItems(?string $hostPanelId = null): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return [];
        }

        $resolvedHost = $hostPanelId ?? Filament::getCurrentPanel()?->getId();
        if (! is_string($resolvedHost) || ! in_array($resolvedHost, self::INTERNAL_HOST_PANEL_IDS, true)) {
            return [];
        }

        $departments = self::normalizedDepartments($user);
        $isSuperAdmin = in_array('SUPERADMIN', $departments, true);

        $items = [];

        $ticketUrl = self::ticketUrlForHost($resolvedHost);
        if ($ticketUrl !== null) {
            $items[] = [
                'kind' => 'ticket',
                'url' => $ticketUrl,
                'label' => 'Crear ticket',
                'subtitle' => 'Soporte Helpdesk',
                'tone' => 0,
                'panel_id' => null,
                'accessible' => true,
                'denied_message' => null,
            ];
        }

        if ($resolvedHost === 'operations') {
            $items[] = [
                'kind' => 'operations-chat',
                'url' => '#',
                'label' => 'Chat casos',
                'subtitle' => 'Seguimiento activo',
                'tone' => 1,
                'panel_id' => null,
                'accessible' => true,
                'denied_message' => null,
            ];
        }

        $panelVisualIndex = 0;
        foreach (self::panelDefinitions() as $definition) {
            $panelId = $definition['id'];
            if (! Route::has($definition['route'])) {
                continue;
            }

            $panel = self::resolvePanel($panelId);
            if ($panel === null) {
                continue;
            }

            if ($isSuperAdmin) {
                $visible = true;
                $accessible = $user->canAccessPanel($panel);
            } else {
                $requiredDepartment = $definition['department'];
                $hasDepartment = in_array($requiredDepartment, $departments, true);
                $accessible = $user->canAccessPanel($panel);
                $visible = $hasDepartment && $accessible;
            }

            if (! $visible) {
                continue;
            }

            $items[] = [
                'kind' => 'panel',
                'url' => route($definition['route']),
                'label' => $definition['label'],
                'subtitle' => $definition['subtitle'],
                'tone' => ($panelVisualIndex % 3) + 1,
                'panel_id' => $panelId,
                'accessible' => $accessible,
                'denied_message' => $accessible ? null : self::panelAccessDeniedMessage($definition['label']),
            ];
            $panelVisualIndex++;
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private static function normalizedDepartments(User $user): array
    {
        $raw = $user->departament;
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = strtoupper(trim($item));
            }
        }

        return $out;
    }

    public static function panelAccessDeniedMessage(string $moduleLabel): string
    {
        return "No tiene acceso al módulo {$moduleLabel}. Solicite la asignación correspondiente al administrador del sistema.";
    }

    /**
     * @return list<array{id: string, department: string, route: string, label: string, subtitle: string}>
     */
    private static function panelDefinitions(): array
    {
        return [
            ['id' => 'business', 'department' => 'NEGOCIOS', 'route' => 'filament.business.pages.dashboard', 'label' => 'Negocios', 'subtitle' => 'Módulo comercial'],
            ['id' => 'administration', 'department' => 'ADMINISTRACION', 'route' => 'filament.administration.pages.dashboard', 'label' => 'Administración', 'subtitle' => 'Finanzas y control'],
            ['id' => 'operations', 'department' => 'OPERACIONES', 'route' => 'filament.operations.pages.dashboard', 'label' => 'Operaciones', 'subtitle' => 'Coordinación y logística'],
            ['id' => 'marketing', 'department' => 'MARKETING', 'route' => 'filament.marketing.pages.dashboard', 'label' => 'Marketing', 'subtitle' => 'Campañas y afiliaciones'],
            ['id' => 'projects', 'department' => 'PROYECTOS', 'route' => 'filament.projects.pages.dashboard', 'label' => 'Proyectos', 'subtitle' => 'Gestión de proyectos'],
        ];
    }

    /**
     * @return class-string<\Filament\Resources\Resource>|null
     */
    private static function helpdeskResourceClassForHost(string $hostPanelId): ?string
    {
        return match ($hostPanelId) {
            'business' => BusinessHelpdeskResource::class,
            'administration' => AdministrationHelpdeskResource::class,
            'operations' => OperationsHelpdeskResource::class,
            'marketing' => MarketingHelpdeskResource::class,
            default => null,
        };
    }

    private static function ticketUrlForHost(string $hostPanelId): ?string
    {
        $helpdeskResourceClass = self::helpdeskResourceClassForHost($hostPanelId);
        if ($helpdeskResourceClass !== null) {
            return $helpdeskResourceClass::getUrl('create', [], false, $hostPanelId);
        }

        // El panel projects no tiene HelpdeskResource propio, por lo que
        // delega la creación al módulo de Negocios.
        if ($hostPanelId === 'projects') {
            return BusinessHelpdeskResource::getUrl('create', [], false, 'business');
        }

        return null;
    }

    private static function resolvePanel(string $id): ?Panel
    {
        try {
            return Filament::getPanel($id);
        } catch (\Throwable) {
            return null;
        }
    }
}
