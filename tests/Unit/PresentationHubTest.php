<?php

declare(strict_types=1);

use App\Models\RrhhColaborador;
use App\Support\PresentationHubGate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'session.driver' => 'array',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');

    $this->withoutMiddleware([
        Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
    ]);

    Schema::dropIfExists('rrhh_colaboradors');
    Schema::create('rrhh_colaboradors', function (Blueprint $table): void {
        $table->id();
        $table->string('fullName')->nullable();
        $table->string('cedula')->nullable();
        $table->string('telefono')->nullable();
        $table->string('telefonoCorporativo')->nullable();
        $table->string('status')->default('activo');
        $table->timestamps();
    });
});

it('registra las rutas del hub de presentaciones', function (): void {
    $webRoutes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

    expect($webRoutes)
        ->toContain("Route::get('/dpto-tecnologia-sistemas', [PresentationHubController::class, 'index'])")
        ->toContain("Route::post('/dpto-tecnologia-sistemas/auth', [PresentationHubController::class, 'authenticate'])")
        ->toContain("Route::post('/dpto-tecnologia-sistemas/heartbeat', [PresentationHubController::class, 'heartbeat'])")
        ->toContain('EnsurePresentationHubAccess::class');
});

it('expone la vista presentation-hub con liquid glass y lista de urls', function (): void {
    $viewPath = dirname(__DIR__, 2).'/resources/views/presentation-hub.blade.php';
    $catalogPath = dirname(__DIR__, 2).'/app/Support/SystemsKnowledgeCatalog.php';

    expect(file_exists($viewPath))->toBeTrue()
        ->and(file_exists($catalogPath))->toBeTrue();

    $viewContents = file_get_contents($viewPath);
    $catalogContents = file_get_contents($catalogPath);

    expect($viewContents)
        ->toContain('liquid-glass')
        ->toContain('Panel de Sistemas')
        ->toContain('Departamento de Sistemas')
        ->toContain('presentaciones-sistemas-bg.png')
        ->toContain('openSection')
        ->toContain('openResource')
        ->toContain('toggleTheme')
        ->toContain('theme-toggle__icon')
        ->toContain("data-theme', 'light'")
        ->toContain('position: absolute')
        ->toContain('html[data-theme="dark"] .theme-toggle::after')
        ->toContain('visual-copy')
        ->toContain('phoneDisplay')
        ->toContain('telefonoCorporativo');

    expect($catalogContents)
        ->toContain('Scrum (desarrollo de apps)')
        ->toContain('Última presentación (avances tecnológicos)')
        ->toContain('Manuales de Tecnología')
        ->toContain('manualItems');
});

it('expone un catalogo escalable con presentaciones y manuales', function (): void {
    $sections = PresentationHubGate::sections();

    expect($sections)->toHaveCount(2)
        ->and(collect($sections)->pluck('id')->all())->toBe(['presentaciones', 'manuales'])
        ->and($sections[0]['items'])->toHaveCount(2)
        ->and($sections[1]['items'])->toBeArray()
        ->and(PresentationHubGate::isAllowedPath('/scrum-desarrollo-apps'))->toBeTrue()
        ->and(PresentationHubGate::isAllowedPath('/avances-tecnologicos'))->toBeTrue()
        ->and(PresentationHubGate::isAllowedPath('/manuales/no-existe'))->toBeFalse();
});

it('normaliza telefono al formato almacenado con mascara +58', function (): void {
    expect(PresentationHubGate::normalizePhoneInput('+584121931865'))->toBe('+584121931865')
        ->and(PresentationHubGate::normalizePhoneInput('04121931865'))->toBe('+584121931865')
        ->and(PresentationHubGate::normalizePhoneInput('4121931865'))->toBe('+584121931865')
        ->and(PresentationHubGate::normalizePhoneInput('+58 412 193 1865'))->toBe('+584121931865')
        ->and(PresentationHubGate::normalizePhoneInput('123'))->toBeNull();
});

it('compara solo digitos de la cedula', function (): void {
    expect(PresentationHubGate::digitsOnly('V-16007868'))->toBe('16007868')
        ->and(PresentationHubGate::digitsOnly('16007868'))->toBe('16007868');
});

it('autentica por cedula ignorando prefijo V-', function (): void {
    $colaborador = RrhhColaborador::query()->create([
        'fullName' => 'Ana Sistemas',
        'cedula' => 'V-16007868',
        'telefono' => '+584121931865',
        'telefonoCorporativo' => null,
        'status' => 'activo',
    ]);

    $found = PresentationHubGate::authenticate('cedula', '16007868');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($colaborador->id);
});

it('autentica por telefono personal o corporativo', function (): void {
    $colaborador = RrhhColaborador::query()->create([
        'fullName' => 'Luis Sistemas',
        'cedula' => 'V-12345678',
        'telefono' => '+584141111111',
        'telefonoCorporativo' => '+584121931865',
        'status' => 'activo',
    ]);

    $byCorporate = PresentationHubGate::authenticate('telefono', '+584121931865');
    $byPersonal = PresentationHubGate::authenticate('telefono', '04141111111');

    expect($byCorporate?->id)->toBe($colaborador->id)
        ->and($byPersonal?->id)->toBe($colaborador->id);
});

it('rechaza colaboradores inactivos', function (): void {
    RrhhColaborador::query()->create([
        'fullName' => 'Inactivo',
        'cedula' => 'V-99999999',
        'telefono' => '+584129999999',
        'status' => 'inactivo',
    ]);

    expect(PresentationHubGate::authenticate('cedula', '99999999'))->toBeNull();
});

it('expone la vista del hub y protege presentaciones sin sesion', function (): void {
    $this->get('/dpto-tecnologia-sistemas')
        ->assertOk()
        ->assertSee('Panel de Sistemas', false)
        ->assertSee('Presentaciones', false)
        ->assertSee('Manuales de Tecnología', false)
        ->assertSee('liquid-glass', false)
        ->assertSee('data-theme-toggle', false);

    $this->get('/scrum-desarrollo-apps')
        ->assertRedirect('/dpto-tecnologia-sistemas?intended='.urlencode('/scrum-desarrollo-apps'));

    $this->get('/avances-tecnologicos')
        ->assertRedirect('/dpto-tecnologia-sistemas?intended='.urlencode('/avances-tecnologicos'));
});

it('permite acceso a presentaciones despues de autenticar', function (): void {
    RrhhColaborador::query()->create([
        'fullName' => 'Dev Team',
        'cedula' => 'V-16007868',
        'telefono' => '+584121931865',
        'telefonoCorporativo' => '+584149998887',
        'status' => 'activo',
    ]);

    $this->postJson('/dpto-tecnologia-sistemas/auth', [
        'method' => 'telefono',
        'credential' => '+584121931865',
        'intended' => '/scrum-desarrollo-apps',
    ])->assertOk()
        ->assertJson([
            'ok' => true,
            'redirect' => '/scrum-desarrollo-apps',
        ]);

    $this->get('/scrum-desarrollo-apps')->assertOk();
});

it('autentica con cedula via endpoint y abre avances tecnologicos', function (): void {
    RrhhColaborador::query()->create([
        'fullName' => 'Colaborador Hub',
        'cedula' => 'V-16007868',
        'telefono' => '+584121000000',
        'status' => 'activo',
    ]);

    $this->postJson('/dpto-tecnologia-sistemas/auth', [
        'method' => 'cedula',
        'credential' => '16007868',
        'intended' => '/avances-tecnologicos',
    ])->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('redirect', '/avances-tecnologicos');

    expect(PresentationHubGate::check())->toBeTrue();

    $this->get('/avances-tecnologicos')->assertOk();
});

it('falla cuando la credencial no existe', function (): void {
    $this->postJson('/dpto-tecnologia-sistemas/auth', [
        'method' => 'cedula',
        'credential' => '11111111',
        'intended' => '/scrum-desarrollo-apps',
    ])->assertStatus(422)
        ->assertJsonPath('ok', false);
});

it('cierra la sesion por inactividad despues de 10 minutos', function (): void {
    $colaborador = RrhhColaborador::query()->create([
        'fullName' => 'Idle User',
        'cedula' => 'V-22222222',
        'telefono' => '+584121222222',
        'status' => 'activo',
    ]);

    PresentationHubGate::grant($colaborador);

    expect(PresentationHubGate::check())->toBeTrue();

    session([
        PresentationHubGate::SESSION_KEY => array_merge(
            (array) session(PresentationHubGate::SESSION_KEY),
            ['last_activity_at' => now()->subMinutes(11)->toIso8601String()]
        ),
    ]);

    expect(PresentationHubGate::check())->toBeFalse()
        ->and(session()->has(PresentationHubGate::SESSION_KEY))->toBeFalse();
});

it('renueva actividad con heartbeat y mantiene la sesion activa', function (): void {
    RrhhColaborador::query()->create([
        'fullName' => 'Active User',
        'cedula' => 'V-33333333',
        'telefono' => '+584121333333',
        'status' => 'activo',
    ]);

    $this->postJson('/dpto-tecnologia-sistemas/auth', [
        'method' => 'cedula',
        'credential' => '33333333',
        'intended' => '/scrum-desarrollo-apps',
    ])->assertOk();

    $this->travel(9)->minutes();

    $this->postJson('/dpto-tecnologia-sistemas/heartbeat')
        ->assertOk()
        ->assertJsonPath('ok', true);

    $this->travel(9)->minutes();

    expect(PresentationHubGate::check())->toBeTrue();
    $this->get('/scrum-desarrollo-apps')->assertOk();
});

it('redirige por idle cuando la sesion expiro al entrar a una presentacion', function (): void {
    $colaborador = RrhhColaborador::query()->create([
        'fullName' => 'Expired User',
        'cedula' => 'V-44444444',
        'telefono' => '+584121444444',
        'status' => 'activo',
    ]);

    PresentationHubGate::grant($colaborador);

    session([
        PresentationHubGate::SESSION_KEY => array_merge(
            (array) session(PresentationHubGate::SESSION_KEY),
            ['last_activity_at' => now()->subMinutes(15)->toIso8601String()]
        ),
    ]);

    $this->get('/scrum-desarrollo-apps')
        ->assertRedirect('/dpto-tecnologia-sistemas?intended='.urlencode('/scrum-desarrollo-apps'))
        ->assertSessionHas('presentation_hub_idle');
});
