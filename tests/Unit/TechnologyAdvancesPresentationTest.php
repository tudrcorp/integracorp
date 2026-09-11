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

    $devicesPartialPath = dirname(__DIR__, 2).'/resources/views/partials/presentation-pwa-devices.blade.php';
    $portalDevicesPartialPath = dirname(__DIR__, 2).'/resources/views/partials/presentation-portal-login-devices.blade.php';
    $portalLoginPartialPath = dirname(__DIR__, 2).'/resources/views/partials/presentation-portal-login-screen.blade.php';
    $portalLoginImagePath = dirname(__DIR__, 2).'/public/image/storefront/portal-paciente-login.jpg';
    $marketingDevicesPartialPath = dirname(__DIR__, 2).'/resources/views/partials/presentation-marketing-devices.blade.php';
    $marketingLoginPartialPath = dirname(__DIR__, 2).'/resources/views/partials/presentation-marketing-login-screen.blade.php';
    $marketingLandingPartialPath = dirname(__DIR__, 2).'/resources/views/partials/presentation-marketing-landing-screen.blade.php';
    $marketingCasaImagePath = dirname(__DIR__, 2).'/public/image/storefront/tdg-casa-bg.jpg';
    $marketingViajesImagePath = dirname(__DIR__, 2).'/public/image/storefront/tdg-viajes-bg.jpg';
    $sistemasDevicesPartialPath = dirname(__DIR__, 2).'/resources/views/partials/presentation-sistemas-devices.blade.php';
    $sistemasHubPartialPath = dirname(__DIR__, 2).'/resources/views/partials/presentation-sistemas-hub-screen.blade.php';
    $sistemasHeroImagePath = dirname(__DIR__, 2).'/public/image/presentaciones-sistemas-bg.png';
    $intraDevicesPartialPath = dirname(__DIR__, 2).'/resources/views/partials/presentation-intra-devices.blade.php';
    $intraIndexPartialPath = dirname(__DIR__, 2).'/resources/views/partials/presentation-intra-index-screen.blade.php';

    expect(file_exists($viewPath))->toBeTrue()
        ->and(file_exists($headerPath))->toBeTrue()
        ->and(file_exists($controllerPath))->toBeTrue()
        ->and(file_exists($devicesPartialPath))->toBeTrue()
        ->and(file_exists($portalDevicesPartialPath))->toBeTrue()
        ->and(file_exists($portalLoginPartialPath))->toBeTrue()
        ->and(file_exists($portalLoginImagePath))->toBeTrue()
        ->and(file_exists($marketingDevicesPartialPath))->toBeTrue()
        ->and(file_exists($marketingLoginPartialPath))->toBeTrue()
        ->and(file_exists($marketingLandingPartialPath))->toBeTrue()
        ->and(file_exists($marketingCasaImagePath))->toBeTrue()
        ->and(file_exists($marketingViajesImagePath))->toBeTrue()
        ->and(file_exists($sistemasDevicesPartialPath))->toBeTrue()
        ->and(file_exists($sistemasHubPartialPath))->toBeTrue()
        ->and(file_exists($sistemasHeroImagePath))->toBeTrue()
        ->and(file_exists($intraDevicesPartialPath))->toBeTrue()
        ->and(file_exists($intraIndexPartialPath))->toBeTrue();

    $viewContents = file_get_contents($viewPath);
    $headerContents = file_get_contents($headerPath);
    $controllerContents = file_get_contents($controllerPath);
    $devicesPartial = file_get_contents($devicesPartialPath);
    $portalDevicesPartial = file_get_contents($portalDevicesPartialPath);
    $portalLoginPartial = file_get_contents($portalLoginPartialPath);
    $marketingDevicesPartial = file_get_contents($marketingDevicesPartialPath);
    $marketingLoginPartial = file_get_contents($marketingLoginPartialPath);
    $marketingLandingPartial = file_get_contents($marketingLandingPartialPath);
    $sistemasDevicesPartial = file_get_contents($sistemasDevicesPartialPath);
    $sistemasHubPartial = file_get_contents($sistemasHubPartialPath);
    $intraDevicesPartial = file_get_contents($intraDevicesPartialPath);
    $intraIndexPartial = file_get_contents($intraIndexPartialPath);

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
        ->toContain('presentation-badge')
        ->toContain('presentation-badge--module')
        ->toContain('presentation-badge--chip')
        ->toContain('partials.presentation-theme-script')
        ->toContain('data-theme')
        ->toContain('color-scheme')
        ->toContain('cover-tracks')
        ->toContain('lifecycle-step')
        ->toContain('hub-url')
        ->toContain('flow-rail')
        ->toContain('pwa-devices')
        ->toContain('partials.presentation-pwa-devices')
        ->toContain('partials.presentation-portal-login-devices')
        ->toContain('portal-devices')
        ->toContain('portal-monitor')
        ->toContain('partials.presentation-marketing-devices')
        ->toContain('mkt-devices')
        ->toContain('partials.presentation-sistemas-devices')
        ->toContain('sys-devices')
        ->toContain('partials.presentation-intra-devices')
        ->toContain('intra-devices');

    expect($devicesPartial)
        ->toContain('pwa-device--phone')
        ->not->toContain('pwa-device--tablet')
        ->toContain('pwa-welcome')
        ->toContain('pwa-plans')
        ->toContain('image/storefront/welcome')
        ->toContain('Tu propia')
        ->toContain('iPhone · Planes');

    expect($portalDevicesPartial)
        ->toContain('pwa-device--phone')
        ->toContain('portal-monitor')
        ->toContain('iPhone · Login')
        ->toContain('PC · Login')
        ->not->toContain('iPad');

    expect($portalLoginPartial)
        ->toContain('Portal del paciente')
        ->toContain('Entrar al portal')
        ->toContain('Documento de Identificación')
        ->toContain('image/storefront/portal-paciente-login.jpg');

    expect($marketingDevicesPartial)
        ->toContain('pwa-device--phone')
        ->toContain('pwa-device--tablet')
        ->toContain('portal-monitor')
        ->toContain('iPhone · Acceso')
        ->toContain('iPad · Acceso')
        ->toContain('PC · Landing');

    expect($marketingLoginPartial)
        ->toContain('Iniciar sesión')
        ->toContain('Aplicación de Marketing')
        ->toContain('Correo electrónico');

    expect($marketingLandingPartial)
        ->toContain('Acceso al panel')
        ->toContain('Tu Dr en Casa')
        ->toContain('Tu Dr en Viajes')
        ->toContain('tdg-casa-bg.jpg');

    expect($sistemasDevicesPartial)
        ->toContain('pwa-device--phone')
        ->toContain('portal-monitor')
        ->toContain('iPhone · Identidad')
        ->toContain('PC · Panel')
        ->toContain('dpto-tecnologia-sistemas');

    expect($sistemasHubPartial)
        ->toContain('Departamento de Sistemas')
        ->toContain('Verifica tu identidad')
        ->toContain('presentaciones-sistemas-bg.png')
        ->toContain('Presentaciones');

    expect($intraDevicesPartial)
        ->toContain('portal-monitor')
        ->toContain('intra.tudrgroup.com')
        ->toContain('PC · Índice');

    expect($intraIndexPartial)
        ->toContain('Índice de portales')
        ->toContain('Producción')
        ->toContain('Desarrollo')
        ->toContain('Tecnología y Sistemas')
        ->toContain('Integracorp App');

    $themePath = dirname(__DIR__, 2).'/resources/views/partials/presentation-theme-styles.blade.php';
    $themeContents = file_get_contents($themePath);

    expect($themeContents)
        ->toContain('.presentation-badge')
        ->toContain('html[data-theme="dark"] .presentation-badge')
        ->toContain('.presentation-badge--module')
        ->toContain('.presentation-badge--chip');

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

it('define nueve diapositivas estructuradas de avances tecnologicos', function (): void {
    $slides = TechnologyAdvancesPresentationSlides::all();

    expect($slides)->toHaveCount(9)
        ->and($slides[0]['type'])->toBe('cover')
        ->and($slides[2]['type'])->toBe('lifecycle')
        ->and($slides[3]['id'])->toBe('panel-sistemas')
        ->and($slides[3]['type'])->toBe('devices')
        ->and($slides[4]['type'])->toBe('devices')
        ->and($slides[7]['type'])->toBe('hub')
        ->and($slides[8]['type'])->toBe('closing')
        ->and(collect($slides)->pluck('id')->unique()->count())->toBe(9);

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

it('incluye el contenido clave de operaciones, planes, pwa, portal, marketing e intra', function (): void {
    $byId = collect(TechnologyAdvancesPresentationSlides::all())->keyBy('id');

    expect($byId->keys()->all())->toContain(
        'portada',
        'operaciones',
        'generador-planes',
        'panel-sistemas',
        'pwa',
        'portal-paciente',
        'marketing',
        'intra',
        'cierre',
    );

    expect($byId['portada']['data']['tracks'])->toHaveCount(7)
        ->and(collect($byId['portada']['data']['tracks'])->pluck('label')->all())->toContain(
            'Operaciones',
            'Planes',
            'Hub',
            'PWA',
            'Portal',
            'Marketing',
            'Intra',
        )
        ->and($byId['operaciones']['data']['pillars'])->toHaveCount(4)
        ->and(collect($byId['operaciones']['data']['pillars'])->pluck('title')->all())->toContain('Cupos clínicos')
        ->and($byId['generador-planes']['data']['steps'])->toHaveCount(4)
        ->and($byId['generador-planes']['title'])->toContain('carga desde el catálogo')
        ->and($byId['panel-sistemas']['type'])->toBe('devices')
        ->and($byId['panel-sistemas']['data']['device_set'])->toBe('sistemas')
        ->and($byId['panel-sistemas']['data']['url'])->toContain('dpto-tecnologia-sistemas')
        ->and($byId['panel-sistemas']['data']['hero_image'])->toContain('presentaciones-sistemas-bg.png')
        ->and($byId['panel-sistemas']['data']['phone_caption'])->toContain('iPhone')
        ->and($byId['panel-sistemas']['data']['monitor_caption'])->toContain('PC')
        ->and($byId['pwa']['data']['steps'])->toHaveCount(4)
        ->and($byId['pwa']['type'])->toBe('devices')
        ->and($byId['pwa']['data']['device_set'])->toBe('pwa')
        ->and($byId['pwa']['title'])->toContain('PWA comercial')
        ->and($byId['pwa']['data']['phone_caption'])->toContain('Bienvenida')
        ->and($byId['pwa']['data']['plans_caption'])->toContain('Planes')
        ->and($byId['pwa']['data']['plans'])->toHaveCount(3)
        ->and(collect($byId['pwa']['data']['plans'])->pluck('title')->all())->toBe([
            'Plan Inicial',
            'Plan Ideal',
            'Plan Especial',
        ])
        ->and($byId['portal-paciente']['type'])->toBe('devices')
        ->and($byId['portal-paciente']['data']['device_set'])->toBe('portal')
        ->and($byId['portal-paciente']['data']['phone_caption'])->toContain('iPhone')
        ->and($byId['portal-paciente']['data']['monitor_caption'])->toContain('PC')
        ->and($byId['portal-paciente']['data']['login_image'])->toContain('portal-paciente-login.jpg')
        ->and($byId['portal-paciente']['data']['for_analysts'])->toHaveCount(3)
        ->and($byId['marketing']['title'])->toBe('Sistema de Marketing')
        ->and($byId['marketing']['type'])->toBe('devices')
        ->and($byId['marketing']['data']['device_set'])->toBe('marketing')
        ->and($byId['marketing']['data']['phone_caption'])->toContain('iPhone')
        ->and($byId['marketing']['data']['tablet_caption'])->toContain('iPad')
        ->and($byId['marketing']['data']['monitor_caption'])->toContain('Landing')
        ->and($byId['marketing']['data']['casa_image'])->toContain('tdg-casa-bg.jpg')
        ->and($byId['marketing']['data']['suites'])->toHaveCount(4)
        ->and($byId['intra']['data']['url'])->toBe('https://intra.tudrgroup.com')
        ->and($byId['intra']['data']['device_set'])->toBe('intra')
        ->and($byId['intra']['data']['monitor_caption'])->toContain('PC · Índice')
        ->and($byId['intra']['data']['cards'])->toHaveCount(3)
        ->and($byId['cierre']['data']['quote'])->toContain('Lo más difícil de ver es lo bueno');
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
        ->assertSee('Lo más difícil de ver es lo bueno', false)
        ->assertSee('En tu Doctor Group, lo bueno pesa muchísimo más', false)
        ->assertSee('Cupos clínicos', false)
        ->assertSee('Generador de planes', false)
        ->assertSee('Cargar estructura', false)
        ->assertSee('Panel de Sistemas', false)
        ->assertSee('iPhone · Identidad', false)
        ->assertSee('PC · Panel', false)
        ->assertSee('Verifica tu identidad', false)
        ->assertSee('dpto-tecnologia-sistemas', false)
        ->assertSee('presentaciones-sistemas-bg', false)
        ->assertSee('PWA comercial', false)
        ->assertSee('iPhone · Bienvenida', false)
        ->assertSee('iPhone · Planes', false)
        ->assertSee('Tu propia', false)
        ->assertSee('Plan Inicial', false)
        ->assertSee('Plan Ideal', false)
        ->assertSee('Plan Especial', false)
        ->assertSee('pwa-device--phone', false)
        ->assertDontSee('iPad · Planes', false)
        ->assertSee('image/storefront/welcome', false)
        ->assertSee('image/storefront/plan-inicial', false)
        ->assertSee('Sistema de Marketing', false)
        ->assertSee('iPhone · Acceso', false)
        ->assertSee('iPad · Acceso', false)
        ->assertSee('PC · Landing', false)
        ->assertSee('Aplicación de Marketing', false)
        ->assertSee('Acceso al panel', false)
        ->assertSee('tdg-casa-bg', false)
        ->assertSee('pwa-device--tablet', false)
        ->assertSee('intra.tudrgroup.com', false)
        ->assertSee('Índice de portales', false)
        ->assertSee('Tecnología y Sistemas', false)
        ->assertSee('PC · Índice de portales', false)
        ->assertSee('Portal del Paciente', false)
        ->assertSee('iPhone · Login', false)
        ->assertSee('PC · Login', false)
        ->assertSee('Entrar al portal', false)
        ->assertSee('portal-paciente-login', false)
        ->assertSee('portal-monitor', false)
        ->assertSee('Documento de Identificación', false)
        ->assertSee('cover-tracks', false)
        ->assertSee('presentation-badge', false)
        ->assertSee('lifecycle-step', false)
        ->assertSee('hub-url', false);
});
