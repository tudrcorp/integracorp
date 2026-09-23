<?php

declare(strict_types=1);

use App\Jobs\NotifyLiveSecurityAlertJob;
use App\Livewire\LiveMonitorTv;
use App\Models\SecurityUserBlock;
use App\Models\User;
use App\Support\LivePresence\ClientLocation;
use App\Support\LivePresence\LivePresenceStore;
use App\Support\LivePresence\SecurityAuthListener;
use App\Support\LivePresence\SecurityMonitor;
use App\Support\LivePresence\SecuritySnapshot;
use App\Support\LivePresence\UserBlockList;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Validated;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Todo en memoria: la caché es `array` y la base una sqlite `:memory:` propia,
 * así estas pruebas nunca tocan la base de desarrollo.
 */
beforeEach(function (): void {
    config()->set('database.connections.live_security_testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]);
    $this->previousConnection = config('database.default');
    config()->set('database.default', 'live_security_testing');
    DB::purge('live_security_testing');
    DB::setDefaultConnection('live_security_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite');

    (require base_path('database/migrations/2026_09_23_120100_create_security_user_blocks_table.php'))->up();
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
        'live-presence.allowed_emails' => ['gcamacho@tudrencasa.com'],
        'live-presence.trust_cloudflare_headers' => true,
        'live-presence.security.trusted_ips' => [],
        'live-presence.tv_token' => str_repeat('a1B2', 16),
        'session.driver' => 'array',
        'cache.default' => 'array',
    ]);
    Cache::store('array')->flush();
    LivePresenceStore::swap(null);
    UserBlockList::flushMemo();
    Bus::fake([NotifyLiveSecurityAlertJob::class]);
});

afterEach(function (): void {
    LivePresenceStore::swap(null);
    UserBlockList::flushMemo();
    DB::purge('live_security_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

function attackRequest(string $ip, string $path = '/business/login'): Request
{
    return Request::create($path, 'POST', server: ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/128.0']);
}

function securityUser(int $id, string $email = 'analista@tudrencasa.com', array $departments = ['NEGOCIOS']): User
{
    $user = User::factory()->make(['id' => $id, 'name' => 'Usuario '.$id, 'email' => $email, 'status' => 'ACTIVO']);
    $user->setRawAttributes([...$user->getAttributes(), 'departament' => json_encode($departments)], true);

    return $user;
}

it('solo acepta CF-Connecting-IP si la conexión viene de Cloudflare', function (): void {
    $direct = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '190.202.10.20', 'HTTP_CF_CONNECTING_IP' => '8.8.8.8']);
    $proxied = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '173.245.48.10', 'HTTP_CF_CONNECTING_IP' => '190.202.10.20']);

    expect(ClientLocation::ip($direct))->toBe('190.202.10.20')
        ->and(ClientLocation::ip($proxied))->toBe('190.202.10.20')
        ->and(ClientLocation::cameThroughCloudflare('190.202.10.20'))->toBeFalse()
        ->and(ClientLocation::cameThroughCloudflare('2606:4700::1'))->toBeTrue();
});

it('detecta fuerza bruta desde una IP y avisa una sola vez', function (): void {
    foreach (range(1, 12) as $attempt) {
        SecurityMonitor::recordFailedLogin(attackRequest('45.10.10.10'), 'victima'.$attempt.'@tudrencasa.com', 'Negocios');
    }

    $snapshot = SecuritySnapshot::build();
    $types = array_column($snapshot['events'], 'type');

    expect($snapshot['level'])->toBe(SecuritySnapshot::LEVEL_RED)
        ->and(array_count_values($types)['brute_force'] ?? 0)->toBe(1)
        ->and($types)->toContain('credential_stuffing')
        ->and($snapshot['offenders'][0]['ip'])->toBe('45.10.10.10')
        ->and($snapshot['offenders'][0]['tags'])->toContain('fuerza bruta')
        ->and($snapshot['last_minute']['failed_logins'])->toBe(12);

    /** Dos tipos críticos distintos (fuerza bruta y relleno de credenciales): un aviso por tipo. */
    Bus::assertDispatchedTimes(NotifyLiveSecurityAlertJob::class, 2);
});

it('bloquea temporalmente la cuenta atacada aunque el atacante cambie de IP', function (): void {
    foreach (range(1, 10) as $attempt) {
        SecurityMonitor::recordFailedLogin(attackRequest('45.20.0.'.$attempt), 'Victima@TuDrEnCasa.com', 'Negocios');
    }

    $lock = SecurityMonitor::accountLock(' victima@tudrencasa.com ');

    expect($lock)->not->toBeNull()
        ->and($lock['failures'])->toBe(10)
        ->and($lock['ips'])->toBe(10)
        ->and(array_column(SecuritySnapshot::build()['events'], 'type'))->toContain('account_locked', 'distributed_attack');

    expect(fn () => SecurityAuthListener::onAttempting(new Attempting('web', ['email' => 'victima@tudrencasa.com', 'password' => 'x'], false)))
        ->toThrow(ValidationException::class, 'bloqueada temporalmente');

    SecurityMonitor::unlockAccount('victima@tudrencasa.com', 'Gustavo');

    expect(SecurityMonitor::accountLock('victima@tudrencasa.com'))->toBeNull();
    SecurityAuthListener::onAttempting(new Attempting('web', ['email' => 'victima@tudrencasa.com', 'password' => 'x'], false));
});

it('una IP de confianza no se marca como amenaza, pero la cuenta se sigue protegiendo', function (): void {
    config(['live-presence.security.trusted_ips' => ['200.8.0.0/16']]);

    foreach (range(1, 10) as $attempt) {
        SecurityMonitor::recordFailedLogin(attackRequest('200.8.1.5'), 'oficina@tudrencasa.com', 'Negocios');
    }

    $snapshot = SecuritySnapshot::build();

    expect($snapshot['offenders'])->toBe([])
        ->and(array_column($snapshot['events'], 'type'))->not->toContain('brute_force')
        ->and(SecurityMonitor::accountLock('oficina@tudrencasa.com'))->not->toBeNull();
});

it('detecta escáneres y bots en peticiones anónimas', function (): void {
    Route::middleware('web')->get('/lp-publica', fn () => response('ok'));

    $this->withServerVariables(['REMOTE_ADDR' => '91.1.1.1'])->get('/.env')->assertNotFound();
    $this->withServerVariables(['REMOTE_ADDR' => '91.1.1.2'])->withHeaders(['User-Agent' => 'python-requests/2.31'])->get('/lp-publica')->assertOk();

    $snapshot = SecuritySnapshot::build();
    $types = array_column($snapshot['events'], 'type');

    expect($types)->toContain('scanner', 'bot')
        ->and($snapshot['last_minute']['not_found'])->toBe(1)
        ->and($snapshot['last_minute']['anonymous'])->toBe(2)
        ->and($snapshot['level'])->toBe(SecuritySnapshot::LEVEL_AMBER);
});

it('sin señales el semáforo está en verde', function (): void {
    expect(SecuritySnapshot::build()['level'])->toBe(SecuritySnapshot::LEVEL_GREEN)
        ->and(SecuritySnapshot::sparkline([0, 5, 10], 100, 20))->toBe('0,18.5 50,10 100,1.5');
});

it('bloquea a un usuario y lo saca en su próxima petición', function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->string('status')->nullable();
        $table->text('departament')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    DB::table('users')->insert(['id' => 77, 'name' => 'Usuario 77', 'email' => 'analista@tudrencasa.com', 'password' => 'x', 'status' => 'ACTIVO', 'departament' => json_encode(['NEGOCIOS'])]);

    Route::middleware(['web', 'auth'])->get('/business/lp-privada', fn () => response('<html>privado</html>', 200, ['Content-Type' => 'text/html']));
    $admin = securityUser(2, 'gcamacho@tudrencasa.com', ['SUPERADMIN']);
    $session = [auth()->guard('web')->getName() => 77];

    $this->withSession($session)->get('/business/lp-privada')->assertOk()->assertSee('privado');

    $block = UserBlockList::block(User::query()->findOrFail(77), 'Descargó población completa sin autorización.', 60, $admin);

    expect(UserBlockList::isBlocked(77))->toBeTrue()
        ->and(UserBlockList::activeCount())->toBe(1)
        ->and($block->blocked_by_name)->toBe('Usuario 2')
        ->and($block->expires_at)->not->toBeNull();

    /** Nueva petición con la misma sesión: el guard lee al usuario de la sesión y lo saca. */
    app('auth')->forgetGuards();

    $this->withSession($session)
        ->get('/business/lp-privada')
        ->assertForbidden()
        ->assertSee('Acceso bloqueado')
        ->assertSee('fue bloqueado por el administrador');

    expect(auth()->guard('web')->check())->toBeFalse();

    UserBlockList::lift($block, 'Aclarado con su supervisor.', $admin);
    app('auth')->forgetGuards();

    expect(UserBlockList::isBlocked(77))->toBeFalse()
        ->and(SecurityUserBlock::query()->find($block->id)->lifted_by_name)->toBe('Usuario 2');

    $this->withSession($session)->get('/business/lp-privada')->assertOk();
});

it('un usuario bloqueado no puede iniciar sesión aunque la clave sea correcta', function (): void {
    UserBlockList::block(securityUser(78), 'Cuenta comprometida, en revisión.', null, securityUser(2, 'gcamacho@tudrencasa.com', ['SUPERADMIN']));

    expect(fn () => SecurityAuthListener::onValidated(new Validated('web', securityUser(78))))
        ->toThrow(ValidationException::class, 'bloqueado');
});

it('no permite bloqueos peligrosos o sin motivo', function (User $target, string $reason, string $message): void {
    $this->actingAs(securityUser(2, 'gcamacho@tudrencasa.com', ['SUPERADMIN']));

    expect(fn () => UserBlockList::block($target, $reason, 60))->toThrow(InvalidArgumentException::class, $message);
    expect(SecurityUserBlock::query()->count())->toBe(0);
})->with([
    'a sí mismo' => [fn () => securityUser(2, 'gcamacho@tudrencasa.com', ['SUPERADMIN']), 'Motivo suficientemente largo.', 'sí mismo'],
    'a un superadmin' => [fn () => securityUser(5, 'jefe@tudrencasa.com', ['SUPERADMIN']), 'Motivo suficientemente largo.', 'SUPERADMIN'],
    'sin motivo' => [fn () => securityUser(80), 'corto', 'motivo'],
]);

it('la alerta por WhatsApp dice qué pasó, dónde y cuándo', function (): void {
    $body = NotifyLiveSecurityAlertJob::whatsappBody([
        'title' => 'Fuerza bruta',
        'detail' => '12 logins fallidos desde 45.10.10.10.',
        'ip' => '45.10.10.10',
        'account' => 'victima@tudrencasa.com',
        'at' => mktime(10, 0, 0, 9, 23, 2026),
    ]);

    expect($body)->toContain('*Fuerza bruta*')
        ->toContain('IP: 45.10.10.10')
        ->toContain('Cuenta: victima@tudrencasa.com')
        ->toContain('23/09/2026 10:00:00')
        ->toContain('Monitor en vivo');
});

it('la pantalla de TV exige el token exacto y muestra el semáforo', function (): void {
    SecurityMonitor::recordFailedLogin(attackRequest('45.10.10.10'), 'victima@tudrencasa.com', 'Negocios');

    $this->get('/monitor/tv/'.str_repeat('x', 64))->assertNotFound();
    $this->get('/monitor/tv/'.str_repeat('a1B2', 16))->assertOk()->assertSee('Monitor en vivo')->assertSee('Logins fallidos');

    Livewire::test(LiveMonitorTv::class, ['token' => str_repeat('a1B2', 16)])
        ->assertSee('victima@tudrencasa.com')
        ->assertSee('45.10.10.10');

    config(['live-presence.tv_token' => 'corto']);
    $this->get('/monitor/tv/'.str_repeat('a1B2', 16))->assertNotFound();
});

it('desde el monitor se ve la seguridad y se bloquea a un usuario con motivo', function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->string('status')->nullable();
        $table->text('departament')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    DB::table('users')->insert(['id' => 90, 'name' => 'Usuario Sospechoso', 'email' => 'sospechoso@tudrencasa.com', 'password' => 'x', 'status' => 'ACTIVO', 'departament' => json_encode(['NEGOCIOS'])]);

    Filament\Facades\Filament::setCurrentPanel('business');
    SecurityMonitor::recordFailedLogin(attackRequest('45.10.10.10'), 'victima@tudrencasa.com', 'Negocios');
    $this->actingAs(securityUser(2, 'gcamacho@tudrencasa.com', ['SUPERADMIN']));

    Livewire::test(App\Filament\Business\Pages\LiveActivityMonitor::class)
        ->assertOk()
        ->assertSee('IPs sospechosas')
        ->assertSee('45.10.10.10')
        ->assertSee('Lista negra')
        ->mountAction('blockUser', ['userId' => 90])
        ->set('mountedActions.0.data.duration', '10080')
        ->set('mountedActions.0.data.reason', 'Intentó exportar datos de otros agentes.')
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertSee('Usuario Sospechoso');

    expect(UserBlockList::isBlocked(90))->toBeTrue()
        ->and(SecurityUserBlock::query()->where('user_id', 90)->value('reason'))->toBe('Intentó exportar datos de otros agentes.');
});

it('el detalle solo ofrece bloquear a quien se puede bloquear', function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->string('status')->nullable();
        $table->text('departament')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    DB::table('users')->insert([
        ['id' => 2, 'name' => 'Gustavo Camacho', 'email' => 'gcamacho@tudrencasa.com', 'password' => 'x', 'status' => 'ACTIVO', 'departament' => json_encode(['SUPERADMIN'])],
        ['id' => 91, 'name' => 'Analista Común', 'email' => 'comun@tudrencasa.com', 'password' => 'x', 'status' => 'ACTIVO', 'departament' => json_encode(['NEGOCIOS'])],
    ]);

    $store = LivePresenceStore::repository();
    $store->touch('dddddddddddddddddddddddd', ['user_id' => 2, 'user_name' => 'Gustavo Camacho', 'panel' => 'business', 'page_label' => 'Monitor en vivo', 'rtt_ms' => 40], true);
    $store->touch('eeeeeeeeeeeeeeeeeeeeeeee', ['user_id' => 91, 'user_name' => 'Analista Común', 'panel' => 'operations', 'page_label' => 'Suppliers', 'rtt_ms' => 700], true);

    Filament\Facades\Filament::setCurrentPanel('business');
    $this->actingAs(User::query()->findOrFail(2));

    Livewire::test(App\Filament\Business\Pages\LiveActivityMonitor::class)
        ->call('selectSession', 'dddddddddddddddddddddddd')
        ->assertSee('Ahora')
        ->assertSee('Línea de tiempo')
        ->assertDontSee('Bloquear usuario')
        ->call('selectSession', 'eeeeeeeeeeeeeeeeeeeeeeee')
        ->assertSee('Analista Común')
        ->assertSee('Bloquear usuario');
});
