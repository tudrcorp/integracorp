<?php

declare(strict_types=1);

use App\Support\TechnologyAdvancesPresentationSlides;

uses(Tests\TestCase::class);

it('registra la ruta de avances tecnologicos sin nombre de ruta', function (): void {
    $webRoutes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

    expect($webRoutes)
        ->toContain("Route::get('/avances-tecnologicos', TechnologyAdvancesPresentationController::class)")
        ->toContain('use App\\Http\\Controllers\\TechnologyAdvancesPresentationController;')
        ->not->toContain("->name('avances-tecnologicos')");
});

it('expone la vista technology-advances-presentation con navegación e interactividad liquid glass', function (): void {
    $viewPath = dirname(__DIR__, 2).'/resources/views/technology-advances-presentation.blade.php';
    $headerPath = dirname(__DIR__, 2).'/resources/views/partials/presentation-app-header.blade.php';
    $controllerPath = dirname(__DIR__, 2).'/app/Http/Controllers/TechnologyAdvancesPresentationController.php';

    expect(file_exists($viewPath))->toBeTrue()
        ->and(file_exists($headerPath))->toBeTrue()
        ->and(file_exists($controllerPath))->toBeTrue();

    $viewContents = file_get_contents($viewPath);
    $headerContents = file_get_contents($headerPath);
    $controllerContents = file_get_contents($controllerPath);

    expect($controllerContents)
        ->toContain("return view('technology-advances-presentation'")
        ->toContain('TechnologyAdvancesPresentationSlides::all()');

    expect($viewContents)
        ->toContain('id="slides-container"')
        ->toContain('id="slides-viewport"')
        ->toContain('liquid-glass')
        ->toContain('#007AFF')
        ->toContain('#14213D')
        ->toContain('tuDrGroup')
        ->toContain('id="btn-next"')
        ->toContain('id="btn-prev"')
        ->toContain('partials.presentation-app-header')
        ->toContain('presentation-nav-desktop')
        ->toContain('presentation-swipe-hint')
        ->toContain('infra-node')
        ->toContain('infra-hierarchy')
        ->toContain('infra-icon--server')
        ->toContain('infra-icon--api')
        ->toContain('infra-icon--database')
        ->toContain('infra-layer--apps')
        ->toContain('infra-layer--api')
        ->toContain('infra-layer--database')
        ->toContain('Apps (fila 1) → API (fila 2) → BD (fila 3)')
        ->toContain('@json($slides)')
        ->toContain('partials.presentation-theme-styles')
        ->toContain('partials.presentation-theme-script')
        ->toContain('data-theme')
        ->toContain('color-scheme');

    expect($headerContents)
        ->toContain('id="btn-overview"')
        ->toContain('id="btn-fullscreen"')
        ->toContain('id="slide-counter"')
        ->toContain('logoNewTDG.png')
        ->toContain('imagotipo.png')
        ->toContain('INTEGRACORP')
        ->toContain('data-presentation-logout')
        ->toContain('data-presentation-theme-toggle');
});

it('define doce diapositivas estructuradas de avances tecnologicos', function (): void {
    $slides = TechnologyAdvancesPresentationSlides::all();

    expect($slides)->toHaveCount(12)
        ->and($slides[0]['type'])->toBe('cover')
        ->and($slides[10]['type'])->toBe('future')
        ->and($slides[11]['type'])->toBe('closing')
        ->and(collect($slides)->pluck('id')->unique()->count())->toBe(12);

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
            ->and($slide['highlights'])->toBeArray()
            ->and($slide['tags'])->toBeArray()
            ->and($slide['data'])->toBeArray();
    }
});

it('incluye el contenido clave de paneles, portal, marketing, api, helpdesk e infraestructura', function (): void {
    $byId = collect(TechnologyAdvancesPresentationSlides::all())->keyBy('id');

    expect($byId->keys()->all())->toContain(
        'portada',
        'operaciones',
        'proyectos',
        'metricas',
        'portal-paciente',
        'marketing',
        'api',
        'helpdesk',
        'notificaciones',
        'infraestructura',
        'futuro',
        'cierre',
    );

    expect($byId['operaciones']['data']['pillars'])->toHaveCount(4)
        ->and($byId['proyectos']['data']['company_help'])->toHaveCount(4)
        ->and($byId['metricas']['data']['status'])->toBe('En construcción')
        ->and($byId['portal-paciente']['data']['for_analysts'])->toHaveCount(3)
        ->and($byId['marketing']['data']['suites'])->toHaveCount(4)
        ->and($byId['api']['data']['improvements'])->toHaveCount(4)
        ->and($byId['helpdesk']['data']['upgrades'])->toHaveCount(4)
        ->and($byId['notificaciones']['data']['upgrades'])->toHaveCount(4)
        ->and($byId['infraestructura']['data']['prod'])->toHaveCount(5)
        ->and($byId['infraestructura']['data']['layers'])->toHaveCount(3)
        ->and($byId['infraestructura']['data']['layers'][0]['nodes'])->toHaveCount(3)
        ->and(collect($byId['infraestructura']['data']['layers'][0]['nodes'])->pluck('id')->all())->toBe([
            'SRV-PROD-INTEGRACORP',
            'SRV-PROD-PORTALPACIENTE',
            'SRV-PROD-MARKETING',
        ])
        ->and($byId['infraestructura']['data']['layers'][1]['nodes'][0]['id'])->toBe('SRV-PROD-INTEGRACORP-API')
        ->and($byId['infraestructura']['data']['layers'][1]['nodes'][0]['kind'])->toBe('api')
        ->and($byId['infraestructura']['data']['layers'][2]['nodes'][0]['id'])->toBe('SRV-PROD-BD')
        ->and($byId['infraestructura']['data']['layers'][2]['nodes'][0]['kind'])->toBe('database')
        ->and($byId['infraestructura']['data']['dev']['id'])->toBe('SRV-DES-INTEGRACORP')
        ->and($byId['futuro']['data']['items'])->toHaveCount(4)
        ->and($byId['futuro']['title'])->toBe('Un futuro muy cercano')
        ->and(collect($byId['futuro']['data']['items'])->pluck('title')->all())->toContain(
            'Mensajería Instantánea TuDrGroup',
            'Red Social TuDrGroup',
            'Seguimiento y auto-responder con IA + N8N',
            'Automatización de procesos internos',
        )
        ->and($byId['cierre']['data']['quote'])->toContain('voto de FE');
});

it('responde la ruta de presentacion de avances tecnologicos con la vista liquid glass', function (): void {
    $this->withSession([
        \App\Support\PresentationHubGate::SESSION_KEY => [
            'colaborador_id' => 1,
            'full_name' => 'Tester',
            'authenticated_at' => now()->toIso8601String(),
        ],
    ])->get('/avances-tecnologicos')
        ->assertOk()
        ->assertSee('Avances Tecnológicos', false)
        ->assertSee('INTEGRACORP', false)
        ->assertSee('tuDrGroup', false)
        ->assertSee('liquid-glass', false)
        ->assertSee('Sesión', false)
        ->assertSee('Tester', false)
        ->assertSee('Cerrar sesión', false)
        ->assertSee('Desliza', false)
        ->assertSee('SRV-PROD-INTEGRACORP-API', false)
        ->assertSee('infra-hierarchy', false)
        ->assertSee('infra-icon--server', false)
        ->assertSee('infra-icon--api', false)
        ->assertSee('infra-icon--database', false)
        ->assertSee('Un futuro muy cercano', false)
        ->assertSee('Mensajería Instantánea TuDrGroup', false)
        ->assertSee('Red Social TuDrGroup', false)
        ->assertSee('Todo en la vida comienza con un voto de FE', false);
});
