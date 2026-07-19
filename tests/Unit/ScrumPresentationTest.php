<?php

declare(strict_types=1);

use App\Support\ScrumPresentationSlides;

uses(Tests\TestCase::class);

it('registra la ruta scrum-desarrollo-apps sin nombre de ruta', function (): void {
    $webRoutes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

    expect($webRoutes)
        ->toContain("Route::get('/scrum-desarrollo-apps', ScrumPresentationController::class)")
        ->toContain('use App\\Http\\Controllers\\ScrumPresentationController;')
        ->not->toContain("->name('scrum-desarrollo-apps')");
});

it('expone la vista scrum-presentation con navegación e interactividad liquid glass', function (): void {
    $viewPath = dirname(__DIR__, 2).'/resources/views/scrum-presentation.blade.php';
    $controllerPath = dirname(__DIR__, 2).'/app/Http/Controllers/ScrumPresentationController.php';

    expect(file_exists($viewPath))->toBeTrue()
        ->and(file_exists($controllerPath))->toBeTrue();

    $viewContents = file_get_contents($viewPath);
    $controllerContents = file_get_contents($controllerPath);

    expect($controllerContents)
        ->toContain("return view('scrum-presentation'")
        ->toContain('ScrumPresentationSlides::all()');

    expect($viewContents)
        ->toContain('id="slides-container"')
        ->toContain('id="slides-viewport"')
        ->toContain('liquid-glass')
        ->toContain('#FCA311')
        ->toContain('#14213D')
        ->toContain('#E5E5E5')
        ->toContain('INTEGRACORP')
        ->toContain('TUDRGROUP')
        ->toContain('logoNewTDG.png')
        ->toContain('imagotipo.png')
        ->toContain('id="btn-next"')
        ->toContain('id="btn-prev"')
        ->toContain('id="btn-overview"')
        ->toContain('data-play-waterfall')
        ->toContain('data-timeline-next')
        ->toContain('@json($slides)')
        ->not->toContain('color-scheme: dark')
        ->not->toContain('bg-gray-950');
});

it('define diez diapositivas estructuradas de la presentación Scrum', function (): void {
    $slides = ScrumPresentationSlides::all();

    expect($slides)->toHaveCount(10)
        ->and($slides[0]['type'])->toBe('cover')
        ->and($slides[9]['type'])->toBe('qa')
        ->and(collect($slides)->pluck('id')->unique()->count())->toBe(10);

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
            ->and($slide['color'])->toBe('#FCA311')
            ->and($slide['highlights'])->toBeArray()
            ->and($slide['tags'])->toBeArray()
            ->and($slide['data'])->toBeArray();
    }
});

it('incluye el contenido clave de roles, artefactos, ciclo y caso delivery', function (): void {
    $byId = collect(ScrumPresentationSlides::all())->keyBy('id');

    expect($byId->keys()->all())->toContain(
        'portada',
        'problema',
        'que-es-scrum',
        'roles',
        'artefactos',
        'ciclo-sprint',
        'caso-delivery',
        'beneficios',
        'conclusion',
        'preguntas',
    );

    expect($byId['roles']['data']['roles'])->toHaveCount(3)
        ->and($byId['artefactos']['data']['artifacts'])->toHaveCount(3)
        ->and($byId['ciclo-sprint']['data']['steps'])->toHaveCount(4)
        ->and($byId['caso-delivery']['data']['sprints'])->toHaveCount(3)
        ->and($byId['conclusion']['data']['pillars'])->toContain('Inspección', 'Adaptación', 'Transparencia')
        ->and($byId['preguntas']['data']['contact'])->toHaveKeys(['name', 'email', 'linkedin', 'org']);
});

it('responde la ruta de presentación scrum con la vista liquid glass', function (): void {
    $this->get('/scrum-desarrollo-apps')
        ->assertOk()
        ->assertSee('Desarrollo de Aplicaciones con Scrum', false)
        ->assertSee('INTEGRACORP', false)
        ->assertSee('TUDRGROUP', false)
        ->assertSee('liquid-glass', false)
        ->assertSee('#FCA311', false)
        ->assertSee('#14213D', false);
});
