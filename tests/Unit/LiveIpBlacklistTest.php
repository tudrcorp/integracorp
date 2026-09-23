<?php

declare(strict_types=1);

use App\Filament\Business\Pages\LiveActivityMonitor;
use App\Jobs\NotifyLiveSecurityAlertJob;
use App\Models\SecurityIpBlock;
use App\Models\User;
use App\Support\LivePresence\IpBlockList;
use App\Support\LivePresence\IpThreatAssessment;
use App\Support\LivePresence\LivePresenceStore;
use App\Support\LivePresence\SecurityMonitor;
use App\Support\LivePresence\SecuritySnapshot;
use App\Support\LivePresence\UserBlockList;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Todo en memoria: la caché es `array` y la base una sqlite `:memory:` propia,
 * así estas pruebas nunca tocan la base de desarrollo.
 */
beforeEach(function (): void {
    config()->set('database.connections.live_ip_testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]);
    $this->previousConnection = config('database.default');
    config()->set('database.default', 'live_ip_testing');
    DB::purge('live_ip_testing');
    DB::setDefaultConnection('live_ip_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite');

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
        'live-presence.allowed_emails' => ['gcamacho@tudrencasa.com'],
        'live-presence.security.trusted_ips' => [],
        'session.driver' => 'array',
        'cache.default' => 'array',
        /** La config puede estar cacheada sin las claves nuevas: se leen del archivo real. */
        'live-presence.security.attack_agents' => (require config_path('live-presence.php'))['security']['attack_agents'],
    ]);
    Cache::store('array')->flush();
    LivePresenceStore::swap(null);
    UserBlockList::flushMemo();
    IpBlockList::flushMemo();
    Bus::fake([NotifyLiveSecurityAlertJob::class]);

    Route::middleware('web')->get('/lp-ip-publica', fn () => response('ok'));
});

afterEach(function (): void {
    LivePresenceStore::swap(null);
    UserBlockList::flushMemo();
    IpBlockList::flushMemo();
    DB::purge('live_ip_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

function ipTestRequest(string $ip, string $path = '/business/login'): Request
{
    return Request::create($path, 'POST', server: ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/128.0']);
}

function ipTestAdmin(): User
{
    $user = User::factory()->make(['id' => 2, 'name' => 'Gustavo', 'email' => 'gcamacho@tudrencasa.com', 'status' => 'ACTIVO']);
    $user->setRawAttributes([...$user->getAttributes(), 'departament' => json_encode(['SUPERADMIN'])], true);

    return $user;
}

function ipTestSession(string $sessionKey, int $userId, string $name, string $ip): void
{
    LivePresenceStore::repository()->touch($sessionKey, ['user_id' => $userId, 'user_name' => $name, 'user_email' => 'u'.$userId.'@tudrencasa.com', 'panel' => 'business', 'ip' => $ip], true);
}

describe('veredicto de una IP', function (): void {
    it('una ruta de escáner es amenaza confirmada aunque la IP tenga usuarios legítimos', function (): void {
        $result = IpThreatAssessment::assess([
            'tags' => ['escáner'],
            'scanner_path' => '.env',
            'sessions' => ['Ana Pérez'],
        ]);

        expect($result['verdict'])->toBe(IpThreatAssessment::CONFIRMED)
            ->and($result['hard'])->toBeTrue()
            ->and($result['reasons'][0])->toContain('/.env')
            ->and($result['mitigations'][0])->toContain('Ana Pérez');
    });

    it('la fuerza bruta es confirmada, pero desde una IP con usuarios legítimos baja a posible', function (): void {
        $attack = ['tags' => ['login fallido', 'fuerza bruta'], 'failed_logins' => 12, 'accounts_tried' => 1];

        expect(IpThreatAssessment::assess($attack)['verdict'])->toBe(IpThreatAssessment::CONFIRMED);

        $shared = IpThreatAssessment::assess([...$attack, 'legitimate' => ['at' => time() - 120, 'account' => 'oficina@tudrencasa.com', 'logins' => 4]]);

        expect($shared['verdict'])->toBe(IpThreatAssessment::POSSIBLE)
            ->and(implode(' ', $shared['reasons']))->toContain('IP compartida');
    });

    it('un login fallido de quien luego entró bien es un probable falso positivo', function (): void {
        $result = IpThreatAssessment::assess([
            'tags' => ['login fallido'],
            'failed_logins' => 1,
            'accounts_tried' => 1,
            'counters' => ['csrf' => 4],
            'legitimate' => ['at' => time() - 60, 'account' => 'drperez3689@tudrencasa.com', 'logins' => 1],
        ]);

        expect($result['verdict'])->toBe(IpThreatAssessment::BENIGN)
            ->and($result['reasons'][0])->toContain('Solo ruido')
            ->and($result['mitigations'][0])->toContain('drperez3689@tudrencasa.com');
    });

    it('varios logins fallidos sin ningún acceso correcto es una posible amenaza', function (): void {
        expect(IpThreatAssessment::assess(['tags' => ['login fallido'], 'failed_logins' => 3, 'accounts_tried' => 1])['verdict'])
            ->toBe(IpThreatAssessment::POSSIBLE);
    });

    it('solo formularios vencidos y 404 sueltos es ruido', function (): void {
        $result = IpThreatAssessment::assess(['counters' => ['csrf' => 6, 'not_found' => 3]]);

        expect($result['verdict'])->toBe(IpThreatAssessment::BENIGN)
            ->and($result['reasons'][0])->toContain('6 formularios vencidos');
    });

    it('la marca de legítima se pierde si aparece una señal dura después', function (): void {
        $dismissal = ['at' => time() - 100, 'until' => time() + 3600, 'by' => 'Gustavo', 'note' => ''];

        expect(IpThreatAssessment::assess(['tags' => ['bot'], 'hard_at' => 0, 'dismissed' => $dismissal])['dismissed'])->toBeTrue()
            ->and(IpThreatAssessment::assess(['tags' => ['escáner'], 'scanner_path' => '.git', 'hard_at' => time(), 'dismissed' => $dismissal])['dismissed'])->toBeFalse();
    });

    it('recomienda bloqueos con vencimiento según el veredicto', function (): void {
        expect(IpThreatAssessment::suggestedMinutes(IpThreatAssessment::CONFIRMED))->toBe(10080)
            ->and(IpThreatAssessment::suggestedMinutes(IpThreatAssessment::POSSIBLE))->toBe(1440);
    });
});

it('el monitor califica y explica a un escáner real', function (): void {
    $this->withServerVariables(['REMOTE_ADDR' => '94.154.43.125'])->get('/.env')->assertNotFound();

    $offender = SecuritySnapshot::build()['offenders'][0];

    expect($offender['ip'])->toBe('94.154.43.125')
        ->and($offender['verdict'])->toBe(IpThreatAssessment::CONFIRMED)
        ->and($offender['reasons'][0])->toContain('/.env')
        ->and($offender['counters']['not_found'])->toBe(1);
});

it('una herramienta de ataque se distingue de un cliente automatizado común', function (): void {
    $this->withServerVariables(['REMOTE_ADDR' => '91.2.2.1'])->withHeaders(['User-Agent' => 'sqlmap/1.7'])->get('/lp-ip-publica')->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '91.2.2.2'])->withHeaders(['User-Agent' => 'curl/8.4'])->get('/lp-ip-publica')->assertOk();

    expect(SecuritySnapshot::offender('91.2.2.1')['verdict'])->toBe(IpThreatAssessment::CONFIRMED)
        ->and(SecuritySnapshot::offender('91.2.2.1')['tags'])->toContain('herramienta de ataque')
        ->and(SecuritySnapshot::offender('91.2.2.2')['tags'])->toContain('bot')
        ->and(SecuritySnapshot::offender('91.2.2.2')['hard'])->toBeFalse();
});

it('un login correcto desde la IP convierte sus fallos sueltos en falso positivo', function (): void {
    SecurityMonitor::recordFailedLogin(ipTestRequest('190.97.243.244'), 'drperez3689@tudrencasa.com', 'Telemedicina');

    expect(SecuritySnapshot::offender('190.97.243.244')['verdict'])->toBe(IpThreatAssessment::BENIGN);

    SecurityMonitor::recordFailedLogin(ipTestRequest('190.97.243.244'), 'drperez3689@tudrencasa.com', 'Telemedicina');
    SecurityMonitor::recordFailedLogin(ipTestRequest('190.97.243.244'), 'drperez3689@tudrencasa.com', 'Telemedicina');

    expect(SecuritySnapshot::offender('190.97.243.244')['verdict'])->toBe(IpThreatAssessment::POSSIBLE);

    SecurityMonitor::recordSuccessfulLogin('drperez3689@tudrencasa.com', '190.97.243.244');

    $offender = SecuritySnapshot::offender('190.97.243.244');

    expect($offender['verdict'])->toBe(IpThreatAssessment::BENIGN)
        ->and($offender['mitigations'][0])->toContain('Login correcto');
});

it('las amenazas confirmadas van primero y las legítimas no se listan', function (): void {
    foreach (range(1, 4) as $attempt) {
        SecurityMonitor::recordFailedLogin(ipTestRequest('45.84.107.198'), 'cuenta'.$attempt.'@gmail.com', 'Negocios');
    }

    $this->withServerVariables(['REMOTE_ADDR' => '159.203.103.240'])->get('/wp-login.php')->assertNotFound();

    expect(array_column(SecuritySnapshot::build()['offenders'], 'ip'))->toBe(['159.203.103.240', '45.84.107.198']);

    SecurityMonitor::dismissIp('45.84.107.198', 'Gustavo', 'Es un aliado probando su clave.');

    $snapshot = SecuritySnapshot::build();

    expect(array_column($snapshot['offenders'], 'ip'))->toBe(['159.203.103.240'])
        ->and($snapshot['dismissed_ips'])->toBe(1)
        ->and(SecurityMonitor::dismissals()['45.84.107.198']['note'])->toBe('Es un aliado probando su clave.');

    SecurityMonitor::undismissIp('45.84.107.198');

    expect(array_column(SecuritySnapshot::build()['offenders'], 'ip'))->toContain('45.84.107.198');
});

it('una IP en la lista negra no entra a ninguna parte y se puede levantar', function (): void {
    $this->withServerVariables(['REMOTE_ADDR' => '94.154.43.125'])->get('/lp-ip-publica')->assertOk();

    $block = IpBlockList::block('94.154.43.125', 'Escáner buscando /.env y /.git.', 60, IpThreatAssessment::CONFIRMED, ['reasons' => ['Pidió /.env']], ipTestAdmin(), '200.1.1.1');

    expect($block->blocked_by_name)->toBe('Gustavo')
        ->and($block->evidence['reasons'])->toBe(['Pidió /.env'])
        ->and(IpBlockList::activeIps())->toBe(['94.154.43.125']);

    $this->withServerVariables(['REMOTE_ADDR' => '94.154.43.125'])->get('/lp-ip-publica')
        ->assertForbidden()
        ->assertSee('Acceso denegado.')
        ->assertDontSee('Escáner');
    $this->withServerVariables(['REMOTE_ADDR' => '94.154.43.126'])->get('/lp-ip-publica')->assertOk();

    $snapshot = SecuritySnapshot::build();

    expect($snapshot['last_minute']['blocked'])->toBe(1)
        ->and($snapshot['blocked_ips'])->toBe(1);

    IpBlockList::lift($block, 'Era una prueba.', ipTestAdmin());

    expect(SecurityIpBlock::query()->find($block->id)->lifted_by_name)->toBe('Gustavo');
    $this->withServerVariables(['REMOTE_ADDR' => '94.154.43.125'])->get('/lp-ip-publica')->assertOk();
});

it('respeta vencimientos y rangos, y una IP de confianza siempre pasa', function (): void {
    SecurityIpBlock::query()->create(['ip' => '45.10.0.0/16', 'reason' => 'Rango de un proveedor de ataques.']);
    SecurityIpBlock::query()->create(['ip' => '91.9.9.9', 'reason' => 'Bloqueo ya vencido.', 'expires_at' => now()->subMinute()]);
    IpBlockList::refresh();

    expect(IpBlockList::isBlocked('45.10.200.7'))->toBeTrue()
        ->and(IpBlockList::isBlocked('45.11.0.1'))->toBeFalse()
        ->and(IpBlockList::isBlocked('91.9.9.9'))->toBeFalse();

    config(['live-presence.security.trusted_ips' => ['45.10.200.0/24']]);

    expect(IpBlockList::isBlocked('45.10.200.7'))->toBeFalse();
});

it('no permite bloqueos peligrosos o sin motivo', function (string $ip, string $reason, string $message): void {
    config(['live-presence.security.trusted_ips' => ['200.8.0.0/16']]);
    ipTestSession('ffffffffffffffffffffffff', 2, 'Gustavo', '186.1.1.1');

    expect(fn () => IpBlockList::block($ip, $reason, 60, null, [], ipTestAdmin(), '200.50.50.50'))
        ->toThrow(InvalidArgumentException::class, $message);
    expect(SecurityIpBlock::query()->count())->toBe(0);
})->with([
    'IP inválida' => ['no-es-ip', 'Motivo suficientemente largo.', 'no es válida'],
    'IP privada' => ['192.168.1.10', 'Motivo suficientemente largo.', 'privada'],
    'IP de confianza' => ['200.8.4.4', 'Motivo suficientemente largo.', 'confianza'],
    'su propia IP' => ['200.50.50.50', 'Motivo suficientemente largo.', 'propia IP'],
    'IP de quien administra' => ['186.1.1.1', 'Motivo suficientemente largo.', 'no se puede bloquear desde aquí'],
    'sin motivo' => ['94.1.1.1', 'corto', 'motivo'],
]);

it('desde el monitor se mueve una IP a la lista negra con su evidencia', function (): void {
    Filament::setCurrentPanel('business');
    $this->withServerVariables(['REMOTE_ADDR' => '94.154.43.125'])->get('/.env')->assertNotFound();
    $this->actingAs(ipTestAdmin());

    Livewire::test(LiveActivityMonitor::class)
        ->assertSee('Amenaza confirmada')
        ->assertSee('una ruta que solo buscan los escáneres')
        ->mountAction('blacklistIp', ['ip' => '94.154.43.125'])
        ->assertSet('mountedActions.0.data.duration', '10080')
        ->set('mountedActions.0.data.reason', 'Escáner buscando archivos de configuración.')
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertSee('IPs en lista negra')
        ->assertSee('Escáner buscando archivos de configuración.');

    $block = SecurityIpBlock::query()->sole();

    expect($block->verdict)->toBe(IpThreatAssessment::CONFIRMED)
        ->and($block->expires_at)->not->toBeNull()
        ->and($block->evidence['reasons'][0])->toContain('/.env')
        ->and(IpBlockList::isBlocked('94.154.43.125'))->toBeTrue();
});

it('exige confirmar a quién deja sin acceso si hay sesiones abiertas desde la IP', function (): void {
    Filament::setCurrentPanel('business');
    $this->withServerVariables(['REMOTE_ADDR' => '190.97.243.244'])->get('/wp-login.php')->assertNotFound();
    ipTestSession('aaaaaaaaaaaaaaaaaaaaaaaa', 55, 'Dr. Pérez', '190.97.243.244');
    $this->actingAs(ipTestAdmin());

    Livewire::test(LiveActivityMonitor::class)
        ->mountAction('blacklistIp', ['ip' => '190.97.243.244'])
        ->set('mountedActions.0.data.reason', 'Escáner desde una IP compartida.')
        ->callMountedAction()
        ->assertHasActionErrors(['acknowledge_affected' => 'accepted']);

    expect(SecurityIpBlock::query()->count())->toBe(0);
});

it('desde el monitor se marca una IP como legítima y se deshace', function (): void {
    Filament::setCurrentPanel('business');
    SecurityMonitor::recordFailedLogin(ipTestRequest('190.153.66.197'), 'analista@tudrencasa.com', 'Negocios');
    $this->actingAs(ipTestAdmin());

    Livewire::test(LiveActivityMonitor::class)
        ->assertSee('190.153.66.197')
        ->mountAction('dismissIp', ['ip' => '190.153.66.197'])
        ->set('mountedActions.0.data.note', 'Oficina de Maracay.')
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertSee('Marcadas como legítimas')
        ->assertSee('Oficina de Maracay.')
        ->callAction('undismissIp', arguments: ['ip' => '190.153.66.197'])
        ->assertDontSee('Marcadas como legítimas');

    expect(SecurityMonitor::dismissals())->toBe([]);
});

it('el comando de emergencia saca una IP de la lista negra', function (): void {
    IpBlockList::block('94.154.43.125', 'Bloqueo que resultó ser la oficina.', null, null, [], ipTestAdmin(), '200.1.1.1');

    $this->artisan('live-presence:ip-unblock', ['ip' => '94.154.43.125', '--reason' => 'Era la oficina.'])
        ->expectsOutputToContain('ya puede entrar')
        ->assertSuccessful();

    expect(IpBlockList::isBlocked('94.154.43.125'))->toBeFalse()
        ->and(SecurityIpBlock::query()->sole()->lift_reason)->toBe('Era la oficina.');
});

describe('inundación y sesiones desbocadas', function (): void {
    it('una IP sin sesión que inunda suma puntaje una sola vez por minuto', function (): void {
        foreach (range(1, 450) as $i) {
            SecurityMonitor::recordRequest(ipTestRequest('45.33.1.1', '/'), null, false);
        }

        $offender = SecuritySnapshot::offender('45.33.1.1');

        expect($offender['score'])->toBe(5)
            ->and($offender['tags'])->toContain('inundación')
            ->and($offender['verdict'])->toBe(IpThreatAssessment::CONFIRMED)
            ->and(array_count_values(array_column(SecuritySnapshot::build()['events'], 'type'))['flood'] ?? 0)->toBe(1);
    });

    it('un usuario con sesión que hace demasiadas peticiones es un aviso de rendimiento, no una amenaza', function (): void {
        $user = User::factory()->make(['id' => 70, 'name' => 'Christopher Reyes', 'email' => 'creyes@tudrencasa.com']);

        foreach (range(1, 453) as $i) {
            SecurityMonitor::recordRequest(ipTestRequest('82.86.134.252', '/business/notifications/bell-alert'), null, true, $user);
        }

        $snapshot = SecuritySnapshot::build();
        $runaway = array_values(array_filter($snapshot['events'], fn (array $event): bool => $event['type'] === 'runaway_session'));

        expect(array_column($snapshot['offenders'], 'ip'))->not->toContain('82.86.134.252')
            ->and($runaway)->toHaveCount(1)
            ->and($runaway[0]['severity'])->toBe(SecurityMonitor::SEVERITY_INFO)
            ->and($runaway[0]['detail'])->toContain('Christopher Reyes')
            ->and($runaway[0]['detail'])->toContain('/business/notifications/bell-alert')
            ->and($snapshot['level'])->toBe(SecuritySnapshot::LEVEL_GREEN);
    });

    it('una etiqueta «bot» vieja no acusa a un navegador normal', function (): void {
        expect(IpThreatAssessment::assess(['tags' => ['bot'], 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/128.0'])['reasons'])
            ->not->toContain('Cliente automatizado (sin navegador).')
            ->and(IpThreatAssessment::assess(['tags' => ['bot'], 'user_agent' => 'python-requests/2.31'])['reasons'])
            ->toContain('Cliente automatizado (sin navegador).');
    });
});
