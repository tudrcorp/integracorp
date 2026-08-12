<?php

declare(strict_types=1);

use App\Support\AgenciasTdevPresentationSlides;
use App\Support\PresentationHubGate;
use App\Support\SystemsKnowledgeCatalog;

uses(Tests\TestCase::class);

it('registra la ruta agencias-tdev sin nombre de ruta', function (): void {
    $webRoutes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

    expect($webRoutes)
        ->toContain("Route::get('/agencias-tdev', AgenciasTdevPresentationController::class)")
        ->toContain('use App\\Http\\Controllers\\AgenciasTdevPresentationController;')
        ->not->toContain("->name('agencias-tdev')");
});

it('expone la vista agencias-tdev-presentation con navegacion tema e interactividad', function (): void {
    $viewPath = dirname(__DIR__, 2).'/resources/views/agencias-tdev-presentation.blade.php';
    $headerPath = dirname(__DIR__, 2).'/resources/views/partials/presentation-app-header.blade.php';
    $controllerPath = dirname(__DIR__, 2).'/app/Http/Controllers/AgenciasTdevPresentationController.php';

    expect(file_exists($viewPath))->toBeTrue()
        ->and(file_exists($headerPath))->toBeTrue()
        ->and(file_exists($controllerPath))->toBeTrue();

    $viewContents = file_get_contents($viewPath);
    $headerContents = file_get_contents($headerPath);
    $controllerContents = file_get_contents($controllerPath);

    expect($controllerContents)
        ->toContain("return view('agencias-tdev-presentation'")
        ->toContain('AgenciasTdevPresentationSlides::all()');

    expect($viewContents)
        ->toContain('id="slides-container"')
        ->toContain('id="slides-viewport"')
        ->toContain('liquid-glass')
        ->toContain('#FCA311')
        ->toContain('tuDrGroup')
        ->toContain('id="btn-next"')
        ->toContain('id="btn-prev"')
        ->toContain('partials.presentation-app-header')
        ->toContain('presentation-nav-desktop')
        ->toContain('presentation-swipe-hint')
        ->toContain('partials.presentation-theme-styles')
        ->toContain('partials.presentation-theme-script')
        ->toContain('data-theme')
        ->toContain('hierarchy-node')
        ->toContain('readable-card')
        ->toContain('@json($slides)');

    expect($headerContents)
        ->toContain('data-presentation-theme-toggle')
        ->toContain('id="btn-overview"')
        ->toContain('id="btn-fullscreen"');
});

it('define catorce diapositivas estructuradas de Agencias TDEV', function (): void {
    $slides = AgenciasTdevPresentationSlides::all();

    expect($slides)->toHaveCount(14)
        ->and($slides[0]['type'])->toBe('cover')
        ->and($slides[13]['type'])->toBe('qa')
        ->and(collect($slides)->pluck('id')->unique()->count())->toBe(14);

    foreach ($slides as $slide) {
        expect($slide)->toHaveKeys([
            'id',
            'type',
            'title',
            'subtitle',
            'module',
            'icon',
            'color',
            'speaker_note',
            'highlights',
            'tags',
            'data',
        ])
            ->and($slide['title'])->not->toBeEmpty()
            ->and($slide['subtitle'])->not->toBeEmpty()
            ->and($slide['highlights'])->toBeArray()
            ->and($slide['tags'])->toBeArray()
            ->and($slide['data'])->toBeArray();
    }
});

it('incluye el contenido clave de niveles urls flujo y panel', function (): void {
    $byId = collect(AgenciasTdevPresentationSlides::all())->keyBy('id');

    expect($byId->keys()->all())->toContain(
        'portada',
        'proposito',
        'mapa',
        'nivel-2',
        'nivel-3',
        'agentes',
        'urls',
        'flujo',
        'panel',
        'notificaciones',
        'lectura',
        'beneficios',
        'cierre',
        'preguntas',
    );

    expect($byId['mapa']['data']['nodes'])->toHaveCount(4)
        ->and($byId['urls']['data']['routes'])->toHaveCount(3)
        ->and($byId['flujo']['data']['steps'])->toHaveCount(4)
        ->and($byId['lectura']['data']['cards'])->toHaveCount(3)
        ->and($byId['beneficios']['data']['benefits'])->toHaveCount(6)
        ->and($byId['preguntas']['data']['contact'])->toHaveKeys(['name', 'email', 'linkedin', 'org'])
        ->and($byId['preguntas']['data']['contact']['email'])->toBe('');

    $paths = collect($byId['urls']['data']['routes'])->pluck('path')->all();
    expect($paths)->toContain('/tdev/web/{token}', '/tdev/agencia/{token}', '/tdev/{token}');
});

it('registra Agencias TDEV en el catalogo de presentaciones', function (): void {
    $items = SystemsKnowledgeCatalog::presentationItems();
    $tdev = collect($items)->firstWhere('id', 'agencias-tdev');

    expect($tdev)->not->toBeNull()
        ->and($tdev['url'])->toBe('/agencias-tdev')
        ->and($tdev['status'])->toBe(SystemsKnowledgeCatalog::STATUS_READY)
        ->and(PresentationHubGate::isAllowedPath('/agencias-tdev'))->toBeTrue()
        ->and(collect($items))->toHaveCount(3);
});

it('responde la ruta de presentacion Agencias TDEV con la vista liquid glass', function (): void {
    $this->withSession([
        PresentationHubGate::SESSION_KEY => [
            'colaborador_id' => 1,
            'full_name' => 'Tester TDEV',
            'authenticated_at' => now()->toIso8601String(),
        ],
    ])->get('/agencias-tdev')
        ->assertOk()
        ->assertSee('Desarrollo de Agencias TDEV', false)
        ->assertSee('INTEGRACORP', false)
        ->assertSee('tuDrGroup', false)
        ->assertSee('liquid-glass', false)
        ->assertSee('data-presentation-theme-toggle', false)
        ->assertSee('Desliza', false)
        ->assertSee('/tdev/web/{token}', false);
});
