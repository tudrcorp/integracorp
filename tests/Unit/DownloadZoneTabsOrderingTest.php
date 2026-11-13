<?php

declare(strict_types=1);

use App\Support\Filament\DownloadZoneTabIcons;
use Filament\Support\Icons\Heroicon;

uses(Tests\TestCase::class);

it('ordena por posición en módulos agentes, general y master sin reordenar desde el listado', function (): void {
    $files = [
        base_path('app/Filament/Agents/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/General/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Master/Resources/DownloadZones/Pages/ListDownloadZones.php'),
    ];

    foreach ($files as $file) {
        $contents = file_get_contents($file);
        expect($contents)->not->toBeFalse();

        expect($contents)
            ->toContain("->orderBy('position')")
            ->toContain("->orderBy('id')");

        expect($contents)->not->toContain("Action::make('editOrder')");
    }
});

it('ordena por posición en módulos marketing, operaciones y administración', function (): void {
    $files = [
        base_path('app/Filament/Marketing/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Operations/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Administration/Resources/DownloadZones/Pages/ListDownloadZones.php'),
    ];

    foreach ($files as $file) {
        $contents = file_get_contents($file);
        expect($contents)->not->toBeFalse();

        expect($contents)
            ->toContain("->orderBy('position')")
            ->toContain("->orderBy('id')");
    }
});

it('expone acción de reordenar documentos en módulos marketing, operaciones y administración', function (): void {
    $files = [
        base_path('app/Filament/Marketing/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Operations/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Administration/Resources/DownloadZones/Pages/ListDownloadZones.php'),
    ];

    foreach ($files as $file) {
        $contents = file_get_contents($file);
        expect($contents)->not->toBeFalse();

        expect($contents)
            ->toContain("Action::make('editOrder')")
            ->toContain("'Editar orden'")
            ->toContain("'Reordenar documentos'")
            ->toContain('public ?string $activeTab = null;')
            ->toContain('private function getActiveZoneId(): ?int');
    }
});

it('centraliza iconos de pestañas en DownloadZoneTabIcons y los referencia en todas las páginas ListDownloadZones', function (): void {
    $support = file_get_contents(base_path('app/Support/Filament/DownloadZoneTabIcons.php'));
    expect($support)->not->toBeFalse();

    expect($support)
        ->toContain('OutlinedMegaphone')
        ->toContain('OutlinedPaperAirplane')
        ->toContain('OutlinedHome')
        ->toContain('forTodosTab');

    $listPages = [
        base_path('app/Filament/Business/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Administration/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Marketing/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Operations/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Master/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/General/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Agents/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Resources/DownloadZones/Pages/ListDownloadZones.php'),
    ];

    foreach ($listPages as $file) {
        expect(file_exists($file))->toBeTrue($file.' debe existir');

        $contents = file_get_contents($file);
        expect($contents)->not->toBeFalse();

        expect($contents)
            ->toContain('DownloadZoneTabIcons::')
            ->toContain('->icon(');
    }
});

it('resuelve iconos conocidos por etiqueta de pestaña', function (): void {
    expect(DownloadZoneTabIcons::forLabel('COMUNICADOS', 1))->toBe(Heroicon::OutlinedMegaphone);
    expect(DownloadZoneTabIcons::forLabel('TU DR. EN VIAJES', 2))->toBe(Heroicon::OutlinedPaperAirplane);
    expect(DownloadZoneTabIcons::forLabel('TU DR. EN CASA', 3))->toBe(Heroicon::OutlinedHome);
    expect(DownloadZoneTabIcons::forTodosTab())->toBe(Heroicon::OutlinedSquaresPlus);
});

it('importa estilos responsive de zona de descarga en el tema de cada panel', function (): void {
    $panelThemes = [
        'operaciones' => 'resources/css/filament/admin/theme.css',
        'administración' => 'resources/css/filament/admin/theme.css',
        'marketing' => 'resources/css/filament/admin/theme.css',
        'business' => 'resources/css/filament/admin/theme.css',
        'master' => 'resources/css/filament/admin/theme.css',
        'agents' => 'resources/css/filament/agents/theme.css',
        'general' => 'resources/css/filament/general/theme.css',
    ];

    foreach ($panelThemes as $panel => $themePath) {
        $theme = file_get_contents(base_path($themePath));
        expect($theme)->not->toBeFalse("El tema de {$panel} debe existir");

        expect($theme)
            ->toContain('download-zone-tabs.css')
            ->toContain('download-zone-sidebar.css');
    }
});

it('usa sidebar de carpetas en business, administración, operaciones, marketing, agentes, general y master', function (): void {
    $listPages = [
        base_path('app/Filament/Business/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Administration/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Operations/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Marketing/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Agents/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/General/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Master/Resources/DownloadZones/Pages/ListDownloadZones.php'),
    ];

    foreach ($listPages as $file) {
        $contents = file_get_contents($file);
        expect($contents)->not->toBeFalse();

        expect($contents)
            ->toContain('use HasDownloadZoneSidebarLayout;')
            ->toContain('HasDownloadZoneSidebarLayout')
            ->toContain('public ?string $activeTab = null;')
            ->not->toContain('HasDownloadZoneTabsGridLayout');
    }

    $trait = file_get_contents(base_path('app/Support/Filament/Concerns/HasDownloadZoneSidebarLayout.php'));
    expect($trait)->not->toBeFalse();

    expect($trait)
        ->toContain('fi-download-zone-sidebar')
        ->toContain('fi-download-zone-sidebar-layout')
        ->toContain('fi-download-zone-sidebar-page')
        ->toContain("public string \$documentsView = 'grid';")
        ->toContain('function setDocumentsView(string $view): void')
        ->toContain('->vertical()');

    $styles = file_get_contents(base_path('resources/css/filament/download-zone-sidebar.css'));
    expect($styles)->not->toBeFalse();

    expect($styles)
        ->toContain('.fi-download-zone-sidebar')
        ->toContain('flex-direction: column')
        ->toContain('@media (min-width: 768px)')
        ->toContain('width: 18rem')
        ->toContain('.fi-download-zone-view-toggle');
});

it('usa tabla browsable Nombre + Descargar con toggle de vista en todos los paneles', function (): void {
    $shared = file_get_contents(base_path('app/Support/Filament/DownloadZonesBrowsableTable.php'));
    expect($shared)->not->toBeFalse();

    expect($shared)
        ->toContain('contentGrid')
        ->toContain('ImageColumn')
        ->toContain("TextColumn::make('list_description')")
        ->toContain("->label('Nombre')")
        ->toContain("Action::make('download')")
        ->toContain("'Descargar'")
        ->toContain('function isGridView')
        ->toContain("=== 'grid'");

    $tables = [
        base_path('app/Filament/Business/Resources/DownloadZones/Tables/DownloadZonesTable.php'),
        base_path('app/Filament/Marketing/Resources/DownloadZones/Tables/DownloadZonesTable.php'),
        base_path('app/Filament/Operations/Resources/DownloadZones/Tables/DownloadZonesTable.php'),
        base_path('app/Filament/Administration/Resources/DownloadZones/Tables/DownloadZonesTable.php'),
        base_path('app/Filament/Agents/Resources/DownloadZones/Tables/DownloadZonesTable.php'),
        base_path('app/Filament/Master/Resources/DownloadZones/Tables/DownloadZonesTable.php'),
        base_path('app/Filament/General/Resources/DownloadZones/Tables/DownloadZonesTable.php'),
    ];

    foreach ($tables as $file) {
        $contents = file_get_contents($file);
        expect($contents)->not->toBeFalse();
        expect($contents)->toContain('DownloadZonesBrowsableTable::configure');
    }

    $provider = file_get_contents(base_path('app/Providers/AppServiceProvider.php'));
    expect($provider)->not->toBeFalse();
    expect($provider)
        ->toContain('TablesRenderHook::TOOLBAR_SEARCH_BEFORE')
        ->toContain('download-zone-documents-view-toggle')
        ->toContain('ListDownloadZones::class');

    $toggleView = file_get_contents(base_path('resources/views/filament/hooks/download-zone-documents-view-toggle.blade.php'));
    expect($toggleView)->not->toBeFalse();
    expect($toggleView)
        ->toContain('setDocumentsView')
        ->toContain('Vista previa')
        ->toContain('fi-download-zone-view-toggle');
});

it('excluye la zona test en los tabs de business, marketing, operaciones y administración', function (): void {
    $files = [
        base_path('app/Filament/Business/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Marketing/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Operations/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Administration/Resources/DownloadZones/Pages/ListDownloadZones.php'),
    ];

    foreach ($files as $file) {
        $contents = file_get_contents($file);
        expect($contents)->not->toBeFalse();

        expect($contents)
            ->toContain("->orWhere('zone', '!=', 'Zona test')")
            ->toContain("->orWhere('code', '!=', 'Zona test')");
    }
});
