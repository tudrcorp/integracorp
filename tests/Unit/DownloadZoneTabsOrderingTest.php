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

it('usa el layout de pestañas en cuadrícula en administración, operaciones, marketing, agentes, agencias y master', function (): void {
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
            ->toContain('use HasDownloadZoneTabsGridLayout;')
            ->toContain('HasDownloadZoneTabsGridLayout');
    }

    $trait = file_get_contents(base_path('app/Support/Filament/Concerns/HasDownloadZoneTabsGridLayout.php'));
    expect($trait)->not->toBeFalse();

    expect($trait)
        ->toContain('fi-download-zone-tabs-grid')
        ->toContain('->contained(false)');

    $styles = file_get_contents(base_path('resources/css/filament/download-zone-tabs.css'));
    expect($styles)->not->toBeFalse();

    expect($styles)
        ->toContain('grid-template-columns: repeat(5, max-content)')
        ->toContain('column-gap: 0.125rem')
        ->toContain('margin-inline: auto')
        ->toContain('justify-content: center')
        ->toContain('font-size: 0.9375rem');
});

it('excluye la zona test en los tabs de marketing, operaciones y administración', function (): void {
    $files = [
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
