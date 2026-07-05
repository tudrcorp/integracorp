<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

final class UserPermissionFormUi
{
    private const MODULE_SHELL_CLASS = 'user-perm-module-shell overflow-hidden rounded-lg border shadow-[0_12px_40px_-14px_rgba(15,23,42,0.14)] ring-1 ring-slate-200/55 dark:border-white/10 dark:ring-white/[0.04] dark:shadow-[0_12px_40px_-14px_rgba(0,0,0,0.55)]';

    private const GROUP_CARD_CLASS = 'user-perm-group-card overflow-hidden rounded-md border ring-1 ring-black/[0.03] dark:ring-white/[0.04]';

    /**
     * Paleta alineada con el stepper de módulos ({@see InternalPanelsQuickNavigation} + theme.css).
     *
     * @var array<string, array{panel_id: string, accent: string, accent_soft: string, accent_ring: string, icon: Heroicon, subtitle: string}>
     */
    private const MODULE_THEMES = [
        'NEGOCIOS' => [
            'panel_id' => 'business',
            'accent' => '#075985',
            'accent_soft' => '#e0f2fe',
            'accent_ring' => 'rgba(125,211,252,0.45)',
            'icon' => Heroicon::OutlinedBriefcase,
            'subtitle' => 'Módulo comercial',
        ],
        'ADMINISTRACION' => [
            'panel_id' => 'administration',
            'accent' => '#2563eb',
            'accent_soft' => '#dbeafe',
            'accent_ring' => 'rgba(147,197,253,0.35)',
            'icon' => Heroicon::OutlinedBuildingLibrary,
            'subtitle' => 'Finanzas y control',
        ],
        'OPERACIONES' => [
            'panel_id' => 'operations',
            'accent' => '#16a34a',
            'accent_soft' => '#dcfce7',
            'accent_ring' => 'rgba(34,197,94,0.35)',
            'icon' => Heroicon::OutlinedCog6Tooth,
            'subtitle' => 'Coordinación y logística',
        ],
        'MARKETING' => [
            'panel_id' => 'marketing',
            'accent' => '#d97706',
            'accent_soft' => '#fef3c7',
            'accent_ring' => 'rgba(251,191,36,0.45)',
            'icon' => Heroicon::OutlinedMegaphone,
            'subtitle' => 'Campañas y afiliaciones',
        ],
        'PROYECTOS' => [
            'panel_id' => 'projects',
            'accent' => '#3b82f6',
            'accent_soft' => '#dbeafe',
            'accent_ring' => 'rgba(147,197,253,0.35)',
            'icon' => Heroicon::OutlinedRectangleGroup,
            'subtitle' => 'Gestión de proyectos',
        ],
    ];

    /**
     * @var array<string, Heroicon>
     */
    private const GROUP_ICONS = [
        'RRHH' => Heroicon::OutlinedUsers,
        'NOMINA' => Heroicon::OutlinedBanknotes,
        'AFILIACIONES' => Heroicon::OutlinedIdentification,
        'ADMINISTRACIÓN' => Heroicon::OutlinedChartBar,
        'ADMINISTRACION' => Heroicon::OutlinedChartBar,
        'ESTRUCTURA COMERCIAL' => Heroicon::OutlinedBuildingStorefront,
        'COMPENSACION TDEV' => Heroicon::OutlinedCurrencyDollar,
        'ZONA DE DESCARGA' => Heroicon::OutlinedArrowDownTray,
        'COTIZADORES' => Heroicon::OutlinedCalculator,
        'CATÁLOGOS' => Heroicon::OutlinedSquares2x2,
        'CATALOGOS' => Heroicon::OutlinedSquares2x2,
        'CONFIGURACIÓN' => Heroicon::OutlinedAdjustmentsHorizontal,
        'CONFIGURACION' => Heroicon::OutlinedAdjustmentsHorizontal,
        'TELEMEDICINA' => Heroicon::OutlinedHeart,
        'INVENTARIO' => Heroicon::OutlinedCube,
        'SERVICIOS' => Heroicon::OutlinedWrenchScrewdriver,
        'PROYECTOS' => Heroicon::OutlinedFolder,
        'GENERAL' => Heroicon::OutlinedSquaresPlus,
        'Otros' => Heroicon::OutlinedEllipsisHorizontalCircle,
    ];

    public static function stylesView(): string
    {
        return 'filament.business.users.partials.permission-form-styles';
    }

    public static function moduleIcon(string $module): Heroicon
    {
        return self::MODULE_THEMES[$module]['icon'] ?? Heroicon::OutlinedSquares2x2;
    }

    public static function moduleDisplayLabel(string $module): string
    {
        return self::moduleShortLabel($module);
    }

    public static function moduleMenuSubtitle(string $module): string
    {
        return self::moduleTheme($module)['subtitle'];
    }

    public static function moduleSectionClass(string $module): string
    {
        $theme = self::moduleTheme($module);
        $panelClass = isset($theme['panel_id'])
            ? ' user-perm-panel-'.$theme['panel_id']
            : '';

        return self::MODULE_SHELL_CLASS.' user-perm-module--'.self::moduleSlug($module).$panelClass;
    }

    public static function groupCardClass(string $module): string
    {
        return self::GROUP_CARD_CLASS.' user-perm-group--'.self::moduleSlug($module);
    }

    public static function permissionsIntroHtml(): HtmlString
    {
        return new HtmlString(
            '<div class="user-perm-intro rounded-[1.25rem] border p-4 ring-1 ring-sky-100/80 dark:ring-sky-500/10 sm:p-5">'
            .'<div class="flex flex-col gap-3 sm:flex-row sm:items-start">'
            .'<div class="user-perm-intro-icon flex size-11 shrink-0 items-center justify-center rounded-2xl ring-1">'
            .'<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>'
            .'</div>'
            .'<div class="min-w-0 space-y-1.5">'
            .'<p class="user-perm-intro-title text-sm font-semibold">Control granular de acceso al menú</p>'
            .'<p class="user-perm-intro-body text-sm leading-relaxed">Los permisos están organizados por <strong>módulo</strong> y por <strong>grupo de navegación</strong>, igual que en el panel lateral. Si no marcas ninguno en un módulo, el usuario <strong>no verá ítems de menú</strong> en ese módulo hasta que le asignes permisos explícitos.</p>'
            .'</div>'
            .'</div>'
            .'</div>'
        );
    }

    public static function permissionsEmptyStateHtml(): HtmlString
    {
        return new HtmlString(
            '<div class="user-perm-empty rounded-[1.25rem] border border-dashed border-slate-300/90 bg-slate-50/80 px-5 py-10 text-center dark:border-white/15 dark:bg-white/[0.03]">'
            .'<div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-2xl bg-slate-200/70 text-slate-500 dark:bg-white/10 dark:text-slate-300">'
            .'<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 0 0 2.25-2.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v2.25A2.25 2.25 0 0 0 6 10.5Zm0 9.75h2.25A2.25 2.25 0 0 0 10.5 18v-2.25a2.25 2.25 0 0 0-2.25-2.25H6a2.25 2.25 0 0 0-2.25 2.25V18A2.25 2.25 0 0 0 6 20.25Zm9.75-9.75H18a2.25 2.25 0 0 0 2.25-2.25V6A2.25 2.25 0 0 0 18 3.75h-2.25A2.25 2.25 0 0 0 13.5 6v2.25a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>'
            .'</div>'
            .'<p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Sin módulos seleccionados</p>'
            .'<p class="mx-auto mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">Ve a la pestaña <strong>Información del usuario</strong> y asigna al menos un módulo para configurar permisos.</p>'
            .'</div>'
        );
    }

    public static function moduleHeaderHtml(string $module, int $permissionCount, int $groupCount): HtmlString
    {
        $theme = self::moduleTheme($module);
        $subtitle = e($theme['subtitle']);
        $countLabel = $permissionCount === 0
            ? 'Sin permisos configurados'
            : $permissionCount.' permiso'.($permissionCount === 1 ? '' : 's').' · '.$groupCount.' grupo'.($groupCount === 1 ? '' : 's').' de menú';

        return new HtmlString(
            '<div class="user-perm-module-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">'
            .'<div class="min-w-0">'
            .'<div class="flex flex-wrap items-center gap-2">'
            .'<span class="user-perm-module-badge inline-flex items-center rounded-full px-3 py-1 text-[0.68rem] font-bold uppercase tracking-[0.14em]">'
            .e(self::moduleShortLabel($module))
            .'</span>'
            .'<span class="text-xs font-medium text-slate-500 dark:text-slate-400">'.e($countLabel).'</span>'
            .'</div>'
            .'<p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">'.$subtitle.'</p>'
            .'</div>'
            .'<div class="user-perm-stat-pill shrink-0 rounded-2xl px-4 py-2 text-center">'
            .'<div class="text-2xl font-bold leading-none">'.$permissionCount.'</div>'
            .'<div class="mt-1 text-[0.65rem] font-semibold uppercase tracking-wide opacity-80">Disponibles</div>'
            .'</div>'
            .'</div>'
        );
    }

    public static function groupHeaderHtml(string $navigationGroup, int $optionCount, string $module): HtmlString
    {
        $icon = self::groupIconSvg($navigationGroup);
        $title = e($navigationGroup);
        $count = $optionCount.' función'.($optionCount === 1 ? '' : 'es');

        return new HtmlString(
            '<div class="user-perm-group-header flex items-center justify-between gap-3">'
            .'<div class="flex min-w-0 items-center gap-3">'
            .'<span class="user-perm-group-icon flex size-9 shrink-0 items-center justify-center rounded-xl">'
            .$icon
            .'</span>'
            .'<div class="min-w-0">'
            .'<p class="truncate text-sm font-semibold text-slate-900 dark:text-white">'.$title.'</p>'
            .'<p class="text-xs text-slate-500 dark:text-slate-400">Grupo de navegación · '.$count.'</p>'
            .'</div>'
            .'</div>'
            .'<span class="user-perm-group-count hidden shrink-0 rounded-full px-2.5 py-1 text-[0.68rem] font-semibold uppercase tracking-wide sm:inline-flex">'.$count.'</span>'
            .'</div>'
        );
    }

    /**
     * @return array{panel_id?: string, accent: string, accent_soft: string, accent_ring: string, icon: Heroicon, subtitle: string}
     */
    private static function moduleTheme(string $module): array
    {
        return self::MODULE_THEMES[$module] ?? [
            'accent' => '#475569',
            'accent_soft' => '#f8fafc',
            'accent_ring' => 'rgba(71,85,105,0.22)',
            'icon' => Heroicon::OutlinedSquares2x2,
            'subtitle' => 'Permisos del módulo '.$module.'.',
        ];
    }

    private static function moduleShortLabel(string $module): string
    {
        return match ($module) {
            'ADMINISTRACION' => 'Administración',
            'NEGOCIOS' => 'Negocios',
            'MARKETING' => 'Marketing',
            'OPERACIONES' => 'Operaciones',
            'PROYECTOS' => 'Proyectos',
            default => $module,
        };
    }

    private static function moduleSlug(string $module): string
    {
        return strtolower(str_replace([' ', '-'], '_', $module));
    }

    private static function groupIconSvg(string $navigationGroup): string
    {
        $icon = self::GROUP_ICONS[$navigationGroup] ?? Heroicon::OutlinedFolderOpen;

        return match ($icon) {
            Heroicon::OutlinedUsers => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>',
            Heroicon::OutlinedBanknotes => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.375M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg>',
            Heroicon::OutlinedIdentification => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" /></svg>',
            Heroicon::OutlinedChartBar => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>',
            Heroicon::OutlinedBuildingStorefront => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72L4.318 3.44A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" /></svg>',
            Heroicon::OutlinedCurrencyDollar => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>',
            Heroicon::OutlinedArrowDownTray => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>',
            Heroicon::OutlinedCalculator => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V12Zm0 2.25h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V18Zm2.498-6.75h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V12Zm0 2.25h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V18Zm2.504-6.75h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V12Zm0 2.25h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V18Zm2.498-6.75h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V12Zm0 2.25h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V18Z" /></svg>',
            Heroicon::OutlinedHeart => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" /></svg>',
            Heroicon::OutlinedCube => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" /></svg>',
            Heroicon::OutlinedWrenchScrewdriver => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437Zm6.615 8.206 2.746-2.746" /></svg>',
            Heroicon::OutlinedFolder => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" /></svg>',
            Heroicon::OutlinedEllipsisHorizontalCircle => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>',
            default => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-4.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" /></svg>',
        };
    }
}
