<?php

declare(strict_types=1);

use App\Support\CierreMesSlides;

it('registra la ruta fantasma cierre-mes sin nombre de ruta', function (): void {
    $webRoutes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

    expect($webRoutes)
        ->toContain("Route::get('/cierre-mes', CierreMesController::class)")
        ->toContain('use App\\Http\\Controllers\\CierreMesController;')
        ->not->toContain("->name('cierre-mes')");
});

it('expone la vista cierre-mes con navegación dinámica y vista en vivo del sistema', function (): void {
    $viewPath = dirname(__DIR__, 2).'/resources/views/cierre-mes.blade.php';
    $controllerPath = dirname(__DIR__, 2).'/app/Http/Controllers/CierreMesController.php';

    expect(file_exists($viewPath))->toBeTrue()
        ->and(file_exists($controllerPath))->toBeTrue();

    $viewContents = file_get_contents($viewPath);
    $controllerContents = file_get_contents($controllerPath);

    expect($controllerContents)
        ->toContain("return view('cierre-mes'")
        ->toContain('CierreMesSlides::all()')
        ->toContain("'url'");

    expect($viewContents)
        ->toContain('id="slides-container"')
        ->toContain('id="slides-viewport"')
        ->toContain('data-system-preview')
        ->toContain('system-preview__viewport')
        ->toContain('activateSystemPreview')
        ->toContain('id="btn-next"')
        ->toContain('id="btn-prev"')
        ->toContain('exit-left')
        ->toContain('exit-right')
        ->toContain('@json($slides)');
});

it('define slides estructurados con vista previa del sistema real', function (): void {
    $slides = CierreMesSlides::all();

    expect($slides)->not->toBeEmpty()
        ->and($slides[0]['type'])->toBe('title')
        ->and(collect($slides)->pluck('id')->unique()->count())->toBe(count($slides));

    foreach ($slides as $slide) {
        expect($slide)->toHaveKeys(['id', 'type', 'title', 'subtitle', 'module', 'icon', 'color', 'preview', 'highlights', 'tags'])
            ->and($slide['preview'])->toHaveKey('type')
            ->and($slide['highlights'])->toBeArray()
            ->and($slide['tags'])->toBeArray();

        if ($slide['preview']['type'] === 'system') {
            expect($slide['preview'])->toHaveKeys(['path', 'panel', 'tip'])
                ->and($slide['preview']['path'])->toStartWith('/');
        }
    }
});

it('vincula cada mejora funcional a una pantalla concreta del sistema', function (): void {
    $systemSlides = collect(CierreMesSlides::all())
        ->filter(fn (array $slide): bool => ($slide['preview']['type'] ?? '') === 'system');

    expect($systemSlides->count())->toBeGreaterThan(10);
    expect($systemSlides->pluck('preview.path'))->toContain('/business/agenda-corporativa')
        ->toContain('/business/helpdesks')
        ->toContain('/marketing/mass-notifications');
});

it('incluye las áreas principales trabajadas en el periodo', function (): void {
    $modules = collect(CierreMesSlides::all())->pluck('module')->unique()->values()->all();

    expect($modules)->toContain('Cotizaciones')
        ->toContain('Soporte')
        ->toContain('Agenda')
        ->toContain('Marketing');
});
