<?php

declare(strict_types=1);

use App\Filament\Business\Pages\LiveActivityMonitor;
use App\Models\User;
use App\Support\LivePresence\ActivityContext;
use App\Support\LivePresence\CacheLivePresenceRepository;
use App\Support\LivePresence\ClientLocation;
use App\Support\LivePresence\LiveActivitySnapshot;
use App\Support\LivePresence\LivePresenceAccess;
use App\Support\LivePresence\LivePresenceStore;
use App\Support\LivePresence\UserAgentSummary;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * El monitor no escribe en MySQL: todo va a Redis o a la caché. Aquí la caché
 * es `array`, en memoria, así que las pruebas no tocan ninguna base.
 */
beforeEach(function (): void {
    config([
        'live-presence.enabled' => true,
        'live-presence.store' => 'cache',
        'live-presence.cache_store' => 'array',
        'live-presence.allowed_emails' => ['gcamacho@tudrencasa.com'],
        'live-presence.trust_cloudflare_headers' => true,
        'live-presence.geoip.database' => '/no/existe.mmdb',
        'session.driver' => 'array',
    ]);
    Cache::store('array')->flush();
    LivePresenceStore::swap(null);
});

afterEach(function (): void {
    LivePresenceStore::swap(null);
});

function presenceUser(string $email = 'analista@tudrencasa.com', int $id = 501, string $status = 'ACTIVO'): User
{
    return User::factory()->make(['id' => $id, 'name' => 'Ana Pérez', 'email' => $email, 'status' => $status]);
}

function presenceRepository(): CacheLivePresenceRepository
{
    return new CacheLivePresenceRepository(Cache::store('array'), 300, 5, 3600, 100);
}

it('reconoce navegador, sistema y dispositivo', function (string $ua, string $browser, string $os, string $device): void {
    $summary = UserAgentSummary::parse($ua);

    expect($summary['browser'])->toBe($browser)
        ->and($summary['os'])->toBe($os)
        ->and($summary['device'])->toBe($device);
})->with([
    'chrome windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36', 'Chrome', 'Windows', 'desktop'],
    'edge mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36 Edg/128.0.0.0', 'Edge', 'macOS', 'desktop'],
    'safari iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', 'Safari', 'iOS', 'mobile'],
    'samsung android' => ['Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/25.0 Chrome/121.0.0.0 Mobile Safari/537.36', 'Samsung Internet', 'Android', 'mobile'],
    'firefox linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0', 'Firefox', 'Linux', 'desktop'],
    'ipad' => ['Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', 'Safari', 'iOS', 'tablet'],
    'vacío' => ['', 'Desconocido', 'Desconocido', 'desktop'],
]);

it('toma la IP real de Cloudflare y descarta encabezados inválidos', function (): void {
    $request = Request::create('/business', 'GET', server: ['REMOTE_ADDR' => '172.70.1.1', 'HTTP_CF_CONNECTING_IP' => '190.202.10.20']);
    expect(ClientLocation::ip($request))->toBe('190.202.10.20');

    $spoof = Request::create('/business', 'GET', server: ['REMOTE_ADDR' => '172.70.1.1', 'HTTP_CF_CONNECTING_IP' => '<script>']);
    expect(ClientLocation::ip($spoof))->toBe('172.70.1.1');

    config(['live-presence.trust_cloudflare_headers' => false]);
    expect(ClientLocation::ip($request))->toBe('172.70.1.1');
});

it('ubica con los encabezados de Cloudflare o marca la red local', function (): void {
    $request = Request::create('/business', 'GET', server: [
        'REMOTE_ADDR' => '173.245.48.10',
        'HTTP_CF_IPCOUNTRY' => 've',
        'HTTP_CF_IPCITY' => 'Caracas',
        'HTTP_CF_REGION' => 'Distrito Capital',
    ]);

    expect(ClientLocation::locate($request, '190.202.10.20'))
        ->toMatchArray(['country_code' => 'VE', 'city' => 'Caracas', 'region' => 'Distrito Capital', 'source' => 'cloudflare']);

    expect(ClientLocation::locate(Request::create('/'), '127.0.0.1'))
        ->toMatchArray(['country' => 'Red local', 'source' => 'local']);

    /** Conexión directa (sin pasar por Cloudflare): los encabezados de ubicación se ignoran. */
    $spoofed = Request::create('/business', 'GET', server: ['REMOTE_ADDR' => '190.202.10.20', 'HTTP_CF_IPCOUNTRY' => 'US', 'HTTP_CF_IPCITY' => 'Falsa']);
    expect(ClientLocation::locate($spoofed, '190.202.10.20')['city'])->not->toBe('Falsa');
});

it('sabe en qué panel y página está el usuario', function (): void {
    expect(ActivityContext::panelFor('/business/affiliation-corporates/15'))->toBe('business')
        ->and(ActivityContext::panelFor('/app/cotizaciones'))->toBe('pwa')
        ->and(ActivityContext::panelFor('/plk/10'))->toBe('web')
        ->and(ActivityContext::pageLabel('/business/affiliation-corporates/15'))->toBe('Afiliaciones › Corporativas · ver #15')
        ->and(ActivityContext::pageLabel('/business/affiliation-corporates/15/edit'))->toBe('Afiliaciones › Corporativas · editar #15')
        ->and(ActivityContext::pageLabel('/operations/suppliers'))->toBe('Proveedores Jurídicos')
        ->and(ActivityContext::pageLabel('/business'))->toBe('Escritorio')
        ->and(ActivityContext::pageLabel('/ruta/sin-pagina-conocida'))->toBe('Ruta · Sin pagina conocida');

    $livewire = Request::create('/livewire/update', 'POST', server: ['HTTP_REFERER' => 'https://www.integracorp.test/operations/suppliers?page=2']);
    expect(ActivityContext::pagePath($livewire))->toBe('/operations/suppliers');
});

/**
 * @param  list<array{method: string, params?: array<int, mixed>}>  $calls
 * @param  array<string, mixed>  $updates
 * @param  array<string, string>  $headers
 */
function livewireCall(array $calls, array $updates = [], array $headers = []): Request
{
    $request = Request::create('/livewire/update', 'POST', [
        'components' => [['snapshot' => '{}', 'calls' => $calls, 'updates' => $updates]],
    ]);

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

it('dice en español lo que hizo el usuario, con el texto del botón que pulsó', function (): void {
    expect(ActivityContext::livewireAction(livewireCall([['method' => 'mountAction', 'params' => ['report_suppliers']]], headers: ['X-Presence-Click' => rawurlencode('Reporte de Proveedores')])))
        ->toBe('Abrió «Reporte de Proveedores»')
        ->and(ActivityContext::livewireAction(livewireCall([['method' => 'mountAction', 'params' => ['report_suppliers']]])))
        ->toBe('Abrió «Report suppliers»')
        ->and(ActivityContext::livewireAction(livewireCall([['method' => 'callMountedAction']], headers: ['X-Presence-Click' => rawurlencode('Cambiar frecuencia')])))
        ->toBe('Confirmó «Cambiar frecuencia»')
        ->and(ActivityContext::livewireAction(livewireCall([['method' => 'unmountAction']])))->toBe('Cerró la ventana')
        ->and(ActivityContext::livewireAction(livewireCall([['method' => 'save']])))->toBe('Guardó los cambios')
        ->and(ActivityContext::livewireAction(livewireCall([['method' => 'sendMessage']], headers: ['X-Presence-Click' => rawurlencode('<b>Enviar</b>')])))->toBe('Pulsó «Enviar»')
        ->and(ActivityContext::livewireAction(livewireCall([['method' => 'sendMessage']])))->toBe('Ejecutó «Send message»');
});

it('ignora lo automático: refrescos, sondeos del chat y gráficas', function (string $method): void {
    expect(ActivityContext::livewireAction(livewireCall([['method' => $method]])))->toBeNull();
})->with(['$refresh', 'pollHeartbeat', 'updateChartData', '__lazyLoad', 'refreshTickets', 'checkForUpdates']);

it('registra búsquedas, filtros y pestañas pero no el tecleo en formularios', function (): void {
    expect(ActivityContext::livewireAction(livewireCall([], ['tableSearch' => 'clinica caracas'])))->toBe('Buscó «clinica caracas»')
        ->and(ActivityContext::livewireAction(livewireCall([], ['tableFilters.status.value' => 'ACTIVO'])))->toBe('Cambió los filtros de la tabla')
        ->and(ActivityContext::livewireAction(livewireCall([], ['activeTab' => 'por_validar'])))->toBe('Cambió a la pestaña «Por validar»')
        ->and(ActivityContext::livewireAction(livewireCall([], ['data.email' => 'algo@x.com'])))->toBeNull();
});

it('describe descargas y PDFs en lenguaje claro', function (): void {
    expect(ActivityContext::downloadLabel('/operations/suppliers/report/preview', 'application/pdf', 'Proveedores Jurídicos'))
        ->toBe('Abrió un PDF (reporte · vista previa) desde Proveedores Jurídicos')
        ->and(ActivityContext::downloadLabel('/business/export-affiliations', 'text/csv; charset=UTF-8', null))
        ->toStartWith('Descargó una hoja de cálculo');
});

it('el almacén mezcla campos, ordena por actividad y excluye a los inactivos', function (): void {
    $store = presenceRepository();

    $store->touch('aaaaaaaaaaaaaaaaaaaaaaaa', ['user_id' => 1, 'user_name' => 'Uno', 'panel' => 'business'], true);
    $store->touch('aaaaaaaaaaaaaaaaaaaaaaaa', ['rtt_ms' => 120, 'visible' => true], false);
    $store->touch('bbbbbbbbbbbbbbbbbbbbbbbb', ['user_id' => 2, 'user_name' => 'Dos', 'panel' => 'pwa'], true);

    $online = $store->online(90);

    expect($online)->toHaveCount(2)
        ->and(collect($online)->firstWhere('session_key', 'aaaaaaaaaaaaaaaaaaaaaaaa'))
        ->toMatchArray(['user_name' => 'Uno', 'panel' => 'business', 'rtt_ms' => '120', 'visible' => '1', 'requests' => '1']);

    $index = Cache::store('array')->get('lp:online');
    $index['bbbbbbbbbbbbbbbbbbbbbbbb'] = time() - 600;
    Cache::store('array')->put('lp:online', $index, 300);

    expect(array_column($store->online(90), 'session_key'))->toBe(['aaaaaaaaaaaaaaaaaaaaaaaa']);
});

it('la línea de tiempo queda acotada y en orden inverso', function (): void {
    $store = presenceRepository();

    foreach (range(1, 8) as $i) {
        $store->pushTimeline(7, ['type' => 'page', 'label' => 'Página '.$i, 'at' => $i]);
    }

    expect(array_column($store->timeline(7, 50), 'label'))->toBe(['Página 8', 'Página 7', 'Página 6', 'Página 5', 'Página 4']);
});

it('calcula peticiones por minuto, media y p95 del servidor', function (): void {
    $store = presenceRepository();
    LivePresenceStore::swap($store);

    foreach ([100, 120, 90, 110, 2000] as $ms) {
        $store->recordRequestDuration($ms);
    }

    expect(LiveActivitySnapshot::performance())->toBe(['rpm' => 5, 'avg_ms' => 484, 'p95_ms' => 2000, 'max_ms' => 2000]);
});

it('solo la lista blanca abre el monitor', function (): void {
    expect(LivePresenceAccess::allows(presenceUser('gcamacho@tudrencasa.com')))->toBeTrue()
        ->and(LivePresenceAccess::allows(presenceUser(' GCAMACHO@tudrencasa.com ')))->toBeTrue()
        ->and(LivePresenceAccess::allows(presenceUser('otro@tudrencasa.com')))->toBeFalse()
        ->and(LivePresenceAccess::allows(presenceUser('gcamacho@tudrencasa.com', status: 'INACTIVO')))->toBeFalse()
        ->and(LivePresenceAccess::allows(null))->toBeFalse();
});

it('cada petición autenticada queda registrada después de responder', function (): void {
    Route::middleware('web')->get('/business/lp-prueba', fn () => response('<html><body>ok</body></html>', 200, ['Content-Type' => 'text/html']));

    $this->actingAs(presenceUser())
        ->withServerVariables(['REMOTE_ADDR' => '190.202.10.20'])
        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36'])
        ->get('/business/lp-prueba')
        ->assertOk();

    $session = LivePresenceStore::repository()->online(90)[0] ?? null;

    expect($session)->not->toBeNull()
        ->and($session)->toMatchArray([
            'user_id' => '501',
            'panel' => 'business',
            'page_label' => 'Lp prueba',
            'ip' => '190.202.10.20',
            'browser' => 'Chrome',
            'os' => 'Windows',
        ])
        ->and((float) $session['server_ms'])->toBeGreaterThanOrEqual(0.0)
        ->and(LivePresenceStore::repository()->timeline(501, 10)[0]['type'] ?? null)->toBe('page');
});

it('los invitados no dejan rastro', function (): void {
    Route::middleware('web')->get('/business/lp-invitado', fn () => response('<html>ok</html>', 200, ['Content-Type' => 'text/html']));

    $this->get('/business/lp-invitado')->assertOk();

    expect(LivePresenceStore::repository()->online(90))->toBe([]);
});

it('el latido guarda latencia, red y PWA instalada', function (): void {
    $this->actingAs(presenceUser())
        ->withHeaders(['X-CSRF-TOKEN' => 'x'])
        ->withoutMiddleware(Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
        ->postJson('/live-presence/ping', [
            'reason' => 'heartbeat',
            'path' => '/app/cotizaciones?token=secreto',
            'title' => 'Cotizaciones',
            'rtt' => 87,
            'visible' => false,
            'effective_type' => '4g',
            'standalone' => true,
            'screen' => '390x844',
        ])
        ->assertNoContent();

    expect(LivePresenceStore::repository()->online(90)[0])->toMatchArray([
        'panel' => 'pwa',
        'page_path' => '/app/cotizaciones',
        'rtt_ms' => '87',
        'visible' => '0',
        'conn_type' => '4g',
        'pwa_installed' => '1',
    ]);
});

it('el latido rechaza valores fuera de rango y exige sesión', function (): void {
    $this->withoutMiddleware(Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

    $this->postJson('/live-presence/ping', ['rtt' => 10])->assertUnauthorized();

    $this->actingAs(presenceUser())->postJson('/live-presence/ping', ['rtt' => -5])->assertUnprocessable();

    expect(LivePresenceStore::repository()->online(90))->toBe([]);
});

it('el monitor muestra a los conectados y bloquea a quien no está en la lista', function (): void {
    Filament::setCurrentPanel('business');
    LivePresenceStore::repository()->touch('cccccccccccccccccccccccc', [
        'user_id' => 42,
        'user_name' => 'María Operaciones',
        'user_email' => 'maria@tudrencasa.com',
        'panel' => 'operations',
        'panel_label' => 'Operaciones',
        'page_label' => 'Suppliers',
        'ip' => '190.202.10.20',
        'city' => 'Caracas',
        'country' => 'Venezuela',
        'country_code' => 'VE',
        'browser' => 'Chrome',
        'os' => 'Windows',
        'rtt_ms' => 95,
        'activity' => 'Guardó los cambios',
        'activity_at' => time(),
        /** Formato técnico anterior: ya no debe mostrarse. */
        'last_action' => 'Case Follow Up Chat Panel › pollHeartbeat',
    ], true);

    $this->actingAs(presenceUser('gcamacho@tudrencasa.com', 2));

    Livewire::test(LiveActivityMonitor::class)
        ->assertOk()
        ->assertSee('María Operaciones')
        ->assertSee('Operaciones')
        ->assertSee('Caracas, Venezuela')
        ->assertSee('95 ms')
        ->assertSee('Guardó los cambios')
        ->assertDontSee('pollHeartbeat')
        ->call('selectSession', 'cccccccccccccccccccccccc')
        ->assertSee('Línea de tiempo')
        ->set('search', 'nadie-coincide')
        ->assertSee('Nadie coincide con el filtro.');

    $this->actingAs(presenceUser('otro@tudrencasa.com', 3));

    expect(LiveActivityMonitor::canAccess())->toBeFalse();
    Livewire::test(LiveActivityMonitor::class)->assertForbidden();
});

it('el latido solo se imprime con sesión iniciada', function (): void {
    expect(view('live-presence.beacon')->render())->not->toContain('__livePresence');

    $this->actingAs(presenceUser());

    expect(view('live-presence.beacon')->render())
        ->toContain('__livePresence')
        ->toContain('live-presence')
        ->toContain('data-navigate-once');
});

it('la actualización de la base de ubicación prueba el mes anterior y conserva la base vigente si falla', function (int $status, string $body): void {
    $target = sys_get_temp_dir().'/lp-ip-city-'.bin2hex(random_bytes(4)).'.mmdb';
    file_put_contents($target, 'BASE-VIGENTE');
    config([
        'live-presence.geoip.database' => $target,
        'live-presence.geoip.download_url' => 'https://download.db-ip.com/free/dbip-city-lite-{month}.mmdb.gz',
    ]);

    Illuminate\Support\Facades\Http::preventStrayRequests();
    Illuminate\Support\Facades\Http::fake(['download.db-ip.com/*' => Illuminate\Support\Facades\Http::response($body, $status)]);

    $this->artisan('live-presence:geoip-update')
        ->expectsOutputToContain('La base anterior sigue en uso sin cambios.')
        ->assertFailed();

    expect(file_get_contents($target))->toBe('BASE-VIGENTE');

    $urls = collect(Illuminate\Support\Facades\Http::recorded())->map(fn (array $pair): string => $pair[0]->url())->unique()->values()->all();
    expect($urls)->toBe([
        'https://download.db-ip.com/free/dbip-city-lite-'.now()->format('Y-m').'.mmdb.gz',
        'https://download.db-ip.com/free/dbip-city-lite-'.now()->subMonthNoOverflow()->format('Y-m').'.mmdb.gz',
    ]);

    @unlink($target);
})->with([
    'servidor rechaza' => [404, 'Not found'],
    'respuesta que no es la base' => [200, 'ok'],
]);
