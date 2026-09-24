<?php

declare(strict_types=1);

use App\Filament\Shared\Auth\PanelAwareLogin;
use App\Jobs\NotifyLiveSecurityAlertJob;
use App\Support\Filament\PanelAccessResolver;
use App\Support\LivePresence\AccessDiagnosis;
use App\Support\LivePresence\IpBlockList;
use App\Support\LivePresence\LivePresenceStore;
use App\Support\LivePresence\SecurityMonitor;
use App\Support\LivePresence\SecuritySnapshot;
use App\Support\LivePresence\UserBlockList;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Todo en memoria: la caché es `array` y la base una sqlite `:memory:` propia,
 * así estas pruebas nunca tocan la base de desarrollo.
 */
beforeEach(function (): void {
    config()->set('database.connections.panel_login_testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]);
    $this->previousConnection = config('database.default');
    config()->set('database.default', 'panel_login_testing');
    DB::purge('panel_login_testing');
    DB::setDefaultConnection('panel_login_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite');

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->string('status')->nullable();
        $table->text('departament')->nullable();
        $table->boolean('is_admin')->default(false);
        $table->boolean('is_agent')->default(false);
        $table->boolean('is_agency')->default(false);
        $table->string('agency_type')->nullable();
        $table->boolean('is_subagent')->default(false);
        $table->boolean('is_proveedor_amd')->default(false);
        $table->unsignedBigInteger('supplier_id')->nullable();
        $table->unsignedBigInteger('doctor_id')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    (require base_path('database/migrations/2026_09_23_120100_create_security_user_blocks_table.php'))->up();
    (require base_path('database/migrations/2026_09_23_180000_create_security_ip_blocks_table.php'))->up();
    Schema::create('logs', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('action')->nullable();
        $table->string('route')->nullable();
        $table->text('response')->nullable();
        $table->string('method')->nullable();
        $table->string('ip')->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamps();
    });

    config([
        'live-presence.enabled' => true,
        'live-presence.store' => 'cache',
        'live-presence.cache_store' => 'array',
        'live-presence.security.trusted_ips' => [],
        'live-presence.allowed_emails' => ['gcamacho@tudrencasa.com'],
        'session.driver' => 'array',
        'cache.default' => 'array',
    ]);
    Cache::store('array')->flush();
    LivePresenceStore::swap(null);
    UserBlockList::flushMemo();
    IpBlockList::flushMemo();
    Bus::fake([NotifyLiveSecurityAlertJob::class]);
});

afterEach(function (): void {
    LivePresenceStore::swap(null);
    UserBlockList::flushMemo();
    DB::purge('panel_login_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

/**
 * @param  list<string>  $departments
 */
function panelLoginUser(string $email, array $departments, string $password = 'Clave-Correcta-1'): int
{
    return (int) DB::table('users')->insertGetId([
        'name' => 'Usuario '.$email,
        'email' => $email,
        'password' => Hash::make($password),
        'status' => 'ACTIVO',
        'departament' => json_encode($departments),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function loginOn(string $panelId): \Livewire\Features\SupportTesting\Testable
{
    Filament::setCurrentPanel($panelId);

    return Livewire::test(PanelAwareLogin::class);
}

it('usa el login que entiende de paneles en los once paneles', function (): void {
    foreach (glob(dirname(__DIR__, 2).'/app/Providers/Filament/*PanelProvider.php') as $provider) {
        expect(file_get_contents($provider))
            ->toContain('->login(PanelAwareLogin::class)')
            ->not->toContain('->login()');
    }
});

it('con la clave correcta en el panel equivocado indica el panel correcto y no cuenta el fallo', function (): void {
    Event::fake([Failed::class]);
    panelLoginUser('doctora@gmail.com', ['TELEMEDICINA']);

    loginOn('business')
        ->set('data.email', 'doctora@gmail.com')
        ->set('data.password', 'Clave-Correcta-1')
        ->call('authenticate')
        ->assertHasErrors(['data.email'])
        ->assertSee('Su usuario no ingresa por este panel. Entre por: Telemedicina.')
        ->assertNotified('Está entrando por el panel equivocado');

    expect(Auth::check())->toBeFalse();
    Event::assertNotDispatched(Failed::class);

    $events = SecuritySnapshot::build()['events'];
    expect(array_column($events, 'type'))->toContain('wrong_panel')
        ->not->toContain('failed_login');
});

it('muchos ingresos por el panel equivocado nunca bloquean la cuenta', function (): void {
    panelLoginUser('doctora@gmail.com', ['TELEMEDICINA']);

    foreach (range(1, 12) as $attempt) {
        RateLimiter::clear('livewire-rate-limiter:'.sha1(PanelAwareLogin::class.'|authenticate|127.0.0.1'));
        RateLimiter::clear('livewire-rate-limiter:'.sha1(PanelAwareLogin::class.'|wrongPanelCheck|127.0.0.1'));

        loginOn('business')
            ->set('data.email', 'doctora@gmail.com')
            ->set('data.password', 'Clave-Correcta-1')
            ->call('authenticate');
    }

    expect(SecurityMonitor::accountLock('doctora@gmail.com'))->toBeNull();
});

it('con la clave incorrecta en el panel equivocado responde y cuenta como siempre', function (): void {
    Event::fake([Failed::class]);
    panelLoginUser('doctora@gmail.com', ['TELEMEDICINA']);

    loginOn('business')
        ->set('data.email', 'doctora@gmail.com')
        ->set('data.password', 'otra-clave')
        ->call('authenticate')
        ->assertHasErrors(['data.email'])
        ->assertDontSee('Entre por:')
        ->assertNotNotified('Está entrando por el panel equivocado');

    Event::assertDispatched(Failed::class);
});

it('un correo que no existe no revela nada', function (): void {
    Event::fake([Failed::class]);

    loginOn('business')
        ->set('data.email', 'nadie@gmail.com')
        ->set('data.password', 'Clave-Correcta-1')
        ->call('authenticate')
        ->assertHasErrors(['data.email'])
        ->assertDontSee('Entre por:');

    Event::assertDispatched(Failed::class);
});

it('un usuario en la lista negra no recibe la pista del panel', function (): void {
    $id = panelLoginUser('doctora@gmail.com', ['TELEMEDICINA']);
    DB::table('security_user_blocks')->insert([
        'user_id' => $id,
        'reason' => 'Bloqueo de prueba del administrador',
        'blocked_by_name' => 'Prueba',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    UserBlockList::refresh();

    loginOn('business')
        ->set('data.email', 'doctora@gmail.com')
        ->set('data.password', 'Clave-Correcta-1')
        ->call('authenticate')
        ->assertDontSee('Entre por:')
        ->assertNotNotified('Está entrando por el panel equivocado');
});

it('con la cuenta bloqueada por intentos no revela el panel', function (): void {
    panelLoginUser('doctora@gmail.com', ['TELEMEDICINA']);
    $request = Illuminate\Http\Request::create('/business/login', 'POST', server: ['REMOTE_ADDR' => '45.10.10.10']);
    foreach (range(1, 10) as $attempt) {
        SecurityMonitor::recordFailedLogin($request, 'doctora@gmail.com', 'Negocios');
    }

    loginOn('business')
        ->set('data.email', 'doctora@gmail.com')
        ->set('data.password', 'Clave-Correcta-1')
        ->call('authenticate')
        ->assertSee('bloqueada temporalmente')
        ->assertDontSee('Entre por:');
});

it('un usuario sin ningún panel recibe el aviso de soporte y no se cuenta como fallo', function (): void {
    Event::fake([Failed::class]);
    panelLoginUser('sinpanel@gmail.com', []);

    loginOn('business')
        ->set('data.email', 'sinpanel@gmail.com')
        ->set('data.password', 'Clave-Correcta-1')
        ->call('authenticate')
        ->assertSee('Su usuario no tiene acceso a ningún panel. Contacte a soporte.');

    Event::assertNotDispatched(Failed::class);
});

it('en su panel correcto el usuario entra normalmente', function (): void {
    panelLoginUser('doctora@gmail.com', ['TELEMEDICINA']);

    loginOn('telemedicina')
        ->set('data.email', 'doctora@gmail.com')
        ->set('data.password', 'Clave-Correcta-1')
        ->call('authenticate')
        ->assertHasNoErrors();

    expect(Auth::check())->toBeTrue()
        ->and(Auth::user()->email)->toBe('doctora@gmail.com');
});

it('lista los paneles accesibles con su dirección de login, sin el actual', function (): void {
    $id = panelLoginUser('analista@tudrencasa.com', ['NEGOCIOS', 'TELEMEDICINA']);
    $user = App\Models\User::query()->findOrFail($id);

    $panels = PanelAccessResolver::accessiblePanels($user, 'business');

    expect(array_column($panels, 'label'))->toBe(['Telemedicina'])
        ->and($panels[0]['url'])->toEndWith('/telemedicina/login')
        ->and($user->status)->toBe('ACTIVO');
});

function diagnosisTitles(string $email): array
{
    return array_column(AccessDiagnosis::for($email)['findings'] ?? [], 'title');
}

it('el diagnóstico ignora textos que no son un correo', function (): void {
    expect(AccessDiagnosis::for(''))->toBeNull()
        ->and(AccessDiagnosis::for('doctora@'))->toBeNull();
});

it('el diagnóstico avisa que el correo no existe y sugiere parecidos', function (): void {
    panelLoginUser('drperez3689@gmail.com', ['TELEMEDICINA']);

    $diagnosis = AccessDiagnosis::for('drperez368@gmail.com');

    expect(array_column($diagnosis['findings'], 'title'))->toBe(['No existe un usuario con ese correo'])
        ->and(array_column($diagnosis['suggestions'], 'email'))->toBe(['drperez3689@gmail.com']);
});

it('el diagnóstico sin bloqueos dice por dónde entrar', function (): void {
    panelLoginUser('doctora@gmail.com', ['TELEMEDICINA']);

    $diagnosis = AccessDiagnosis::for('Doctora@Gmail.com ');

    expect(array_column($diagnosis['findings'], 'severity'))->toBe(['ok'])
        ->and(array_column($diagnosis['panels'], 'label'))->toBe(['Telemedicina']);
});

it('el diagnóstico detecta el panel equivocado y la cuenta bloqueada', function (): void {
    panelLoginUser('doctora@gmail.com', ['TELEMEDICINA']);
    $request = Illuminate\Http\Request::create('/business/login', 'POST', server: ['REMOTE_ADDR' => '190.97.243.244']);
    foreach (range(1, 10) as $attempt) {
        SecurityMonitor::recordFailedLogin($request, 'doctora@gmail.com', 'Negocios');
    }

    expect(diagnosisTitles('doctora@gmail.com'))
        ->toContain('Cuenta bloqueada por intentos fallidos')
        ->toContain('Intenta entrar por el panel equivocado');
});

it('el diagnóstico sugiere restablecer la clave si falla en su propio panel', function (): void {
    panelLoginUser('doctora@gmail.com', ['TELEMEDICINA']);
    $request = Illuminate\Http\Request::create('/telemedicina/login', 'POST', server: ['REMOTE_ADDR' => '190.97.243.244']);
    SecurityMonitor::recordFailedLogin($request, 'doctora@gmail.com', 'Telemedicina');
    SecurityMonitor::recordFailedLogin($request, 'doctora@gmail.com', 'Telemedicina');

    expect(diagnosisTitles('doctora@gmail.com'))->toContain('2 intentos fallidos en su propio panel');
});

it('el diagnóstico detecta el bloqueo de usuario y la IP en lista negra', function (): void {
    $id = panelLoginUser('doctora@gmail.com', ['TELEMEDICINA']);
    DB::table('security_user_blocks')->insert([
        'user_id' => $id,
        'reason' => 'Bloqueo de prueba del administrador',
        'blocked_by_name' => 'Prueba',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    UserBlockList::refresh();
    DB::table('security_ip_blocks')->insert([
        'ip' => '190.97.243.244',
        'reason' => 'Bloqueo de prueba de la IP',
        'blocked_by_name' => 'Prueba',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    IpBlockList::refresh();
    $request = Illuminate\Http\Request::create('/telemedicina/login', 'POST', server: ['REMOTE_ADDR' => '190.97.243.244']);
    SecurityMonitor::recordFailedLogin($request, 'doctora@gmail.com', 'Telemedicina');

    $diagnosis = AccessDiagnosis::for('doctora@gmail.com');

    expect(array_column($diagnosis['findings'], 'title'))
        ->toContain('Bloqueado por un administrador')
        ->toContain('Se conecta desde una IP en la lista negra')
        ->and($diagnosis['ips'])->toBe([['ip' => '190.97.243.244', 'blocked' => true]]);
});

it('desde el monitor se diagnostica un correo y se desbloquea la cuenta', function (): void {
    Filament::setCurrentPanel('business');
    panelLoginUser('doctora@gmail.com', ['TELEMEDICINA']);
    $admin = App\Models\User::factory()->make(['id' => 2, 'name' => 'Gustavo', 'email' => 'gcamacho@tudrencasa.com', 'status' => 'ACTIVO']);
    $admin->setRawAttributes([...$admin->getAttributes(), 'departament' => json_encode(['SUPERADMIN'])], true);
    $this->actingAs($admin);
    $request = Illuminate\Http\Request::create('/business/login', 'POST', server: ['REMOTE_ADDR' => '190.97.243.244']);
    foreach (range(1, 10) as $attempt) {
        SecurityMonitor::recordFailedLogin($request, 'doctora@gmail.com', 'Negocios');
    }

    Livewire::test(App\Filament\Business\Pages\LiveActivityMonitor::class)
        ->assertSee('¿Por qué no puede entrar?')
        ->assertActionExists('diagnoseAccess')
        ->mountAction('diagnoseAccess')
        ->assertActionMounted('diagnoseAccess')
        ->set('mountedActions.0.data.email', 'doctora@gmail.com')
        ->assertHasNoErrors()
        ->assertSet('mountedActions.0.data.email', 'doctora@gmail.com');

    expect(diagnosisTitles('doctora@gmail.com'))->toContain('Cuenta bloqueada por intentos fallidos');

    SecurityMonitor::unlockAccount('doctora@gmail.com', 'Gustavo');

    expect(SecurityMonitor::accountLock('doctora@gmail.com'))->toBeNull();
});

it('la vista del diagnóstico muestra hallazgos, panel e intentos', function (): void {
    panelLoginUser('doctora@gmail.com', ['TELEMEDICINA']);
    $request = Illuminate\Http\Request::create('/business/login', 'POST', server: ['REMOTE_ADDR' => '190.97.243.244']);
    SecurityMonitor::recordFailedLogin($request, 'doctora@gmail.com', 'Negocios');

    $html = view('live-presence.partials.access-diagnosis', ['diagnosis' => AccessDiagnosis::for('doctora@gmail.com')])->render();
    $empty = view('live-presence.partials.access-diagnosis', ['diagnosis' => null])->render();

    expect($html)->toContain('Intenta entrar por el panel equivocado')
        ->toContain('Telemedicina')
        ->toContain('/telemedicina/login')
        ->toContain('190.97.243.244')
        ->toContain('Login fallido')
        ->and($empty)->toContain('Escriba un correo completo para ver el diagnóstico.');
});
