<?php

declare(strict_types=1);

use App\Filament\Business\Pages\SupervisionWhatsapp;
use App\Mail\InternalNotificationMail;
use App\Models\CrmHandoffEnvelope;
use App\Models\User;
use App\Support\CrmInbox\CrmBotMetrics;
use App\Support\CrmInbox\CrmChannelHealth;
use App\Support\CrmInbox\CrmChannelHeartbeat;
use App\Support\CrmInbox\CrmInboxCache;
use App\Support\CrmInbox\CrmInboxTrends;
use App\Support\CrmInbox\CrmSupervisionExport;
use App\Support\CrmInbox\CrmSupervisionInsights;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Supervisión WhatsApp como centro de control: métricas del bot, salud en vivo de WhatsApp,
 * n8n, bot y bandeja, historial e incidentes con avisos a SUPERADMIN, tendencias, hallazgos y
 * exportación. Todo lo externo se simula. Escribe dentro de una transacción que siempre se revierte;
 * las claves de caché que toca se guardan y se restauran, así el historial real no se pierde.
 */
const SUP_KEYS = ['crm-channel:beats', 'crm-channel:state', 'crm-channel:incidents', 'crm-channel:health', 'crm-bot:live',
    'crm-bot:metrics:hoy', 'crm-bot:metrics:7d', 'crm-bot:metrics:30d', 'crm-channel:number:sol-qa',
    'crm-inbox:dashboard:business:hoy', 'crm-inbox:dashboard:business:7d', 'crm-inbox:dashboard:business:30d'];

beforeEach(function (): void {
    DB::beginTransaction();
    CrmInboxCache::reset();
    $this->savedCache = [];

    foreach (SUP_KEYS as $key) {
        $this->savedCache[$key] = CrmInboxCache::attempt(fn ($store) => $store->get($key));
        CrmInboxCache::attempt(fn ($store) => $store->forget($key));
    }

    config([
        'crm-inbox.takeover_url' => 'http://n8n.test/webhook/handoff/takeover',
        'crm-inbox.takeover_key' => 'clave-qa',
        'crm-inbox.metrics_url' => '',
        'crm-inbox.n8n_health_url' => '',
        'crm-inbox.evolution.url' => 'http://evo.test',
        'crm-inbox.evolution.key' => 'evo-qa',
        'crm-inbox.evolution.instance' => 'sol-qa',
        'crm-inbox.health_alert_minutes' => 2,
    ]);
});

afterEach(function (): void {
    DB::rollBack();

    foreach (SUP_KEYS as $key) {
        $value = $this->savedCache[$key] ?? null;
        CrmInboxCache::attempt(fn ($store) => $value === null ? $store->forget($key) : $store->forever($key, $value));
    }
});

function supMetricsPayload(array $live = []): array
{
    return [
        'ok' => true, 'version' => 1,
        'live' => ['last_in_at' => now()->subMinute()->toIso8601String(), 'last_out_at' => now()->subMinute()->toIso8601String(), 'in_5m' => 2, 'out_5m' => 3,
            'in_60m' => 12, 'out_60m' => 15, 'stuck' => 0, 'stuck_since' => null, 'open_handoffs' => 1, 'takeover_now' => 1, 'crm_pending' => 0, 'errors_60m' => 0, ...$live],
        'periodo' => ['clientes' => 20, 'clientes_nuevos' => 8, 'mensajes_in' => 140, 'mensajes_out' => 190, 'audios_in' => 9, 'cotizaciones' => 11, 'clientes_cotizados' => 9,
            'propuestas_pdf' => 6, 'derivaciones' => 7, 'clientes_derivados' => 6, 'resueltos_sin_humano' => 14, 'derivaciones_repetidas' => 1, 'errores' => 2,
            'errores_escalados' => 1, 'seguimientos_enviados' => 10, 'seguimientos_respondidos' => 4, 'citas' => 0, 'links_pago' => 0, 'ofrecio_humano' => 2, 'finalizadas' => 3],
        'areas' => ['comercial' => 5, 'emergencias' => 2],
        'motivos_recientes' => [['motivo' => 'Quiere hablar con un analista', 'area' => 'comercial', 'at' => now()->subHour()->toIso8601String()]],
        'errores_recientes' => [['error' => 'Timeout del modelo', 'event' => 'agent_error_escalated', 'at' => now()->subHours(2)->toIso8601String()]],
        'eventos' => ['handoff' => 7, 'pdf_sent' => 6],
        'etapas' => ['quote' => 6, 'handoff' => 4, 'discovery' => 10],
        'por_dia' => [['dia' => now('America/Caracas')->format('Y-m-d'), 'clientes' => 20, 'mensajes_in' => 140, 'derivaciones' => 7]],
        'por_hora' => [['hora' => 9, 'mensajes_in' => 40], ['hora' => 14, 'mensajes_in' => 60], ['hora' => 99, 'mensajes_in' => 5]],
    ];
}

function supFakeAll(string $state = 'open', array $live = [], int $metricsStatus = 200, bool $n8nUp = true): void
{
    Http::swap(new Illuminate\Http\Client\Factory(app('events')));
    Http::fake([
        'evo.test/instance/connectionState/*' => Http::response(['instance' => ['instanceName' => 'sol-qa', 'state' => $state]]),
        'evo.test/instance/fetchInstances*' => Http::response([['name' => 'sol-qa', 'ownerJid' => '584142510805@s.whatsapp.net', 'connectionStatus' => $state]]),
        'n8n.test/healthz' => $n8nUp ? Http::response(['status' => 'ok']) : Http::response('', 503),
        'n8n.test/webhook/handoff/metrics' => $metricsStatus === 200 ? Http::response(supMetricsPayload($live)) : Http::response(['ok' => false], $metricsStatus),
    ]);
}

it('lee las métricas del bot con la clave y las deja con tipos seguros', function (): void {
    supFakeAll();

    $data = CrmBotMetrics::fetch(now()->subDay());

    expect($data['period']['clientes'])->toBe(20)
        ->and($data['period']['resueltos_sin_humano'])->toBe(14)
        ->and($data['hours'])->toHaveCount(24)
        ->and($data['hours'][14])->toBe(60)
        ->and($data['areas'])->toBe(['comercial' => 5, 'emergencias' => 2])
        ->and($data['errors'][0]['escalated'])->toBeTrue()
        ->and(is_int($data['live']['last_in_at']))->toBeTrue();

    $sent = Http::recorded(fn ($request): bool => $request->url() === 'http://n8n.test/webhook/handoff/metrics' && $request->header('X-Handoff-Key')[0] === 'clave-qa' && isset($request['desde']));
    expect($sent)->toHaveCount(1);
});

it('sin webhook publicado, con clave errada o con basura no rompe: devuelve null con el motivo', function (int $status, string $reason): void {
    supFakeAll(metricsStatus: $status);
    $error = null;

    expect(CrmBotMetrics::fetch(now()->subDay(), $error))->toBeNull()
        ->and($error)->toContain($reason);
})->with([
    'no publicado' => [404, 'no está publicado'],
    'clave errada' => [401, 'clave'],
    'error interno' => [500, 'inválida'],
]);

it('normaliza datos inesperados sin fallar', function (): void {
    $data = CrmBotMetrics::normalize(['live' => ['in_5m' => 'x', 'last_in_at' => 'no-es-fecha'], 'periodo' => 'basura', 'areas' => ['ok' => 3, 5 => 2, 'malo' => 'x'], 'por_hora' => 'nada']);

    expect($data['live']['in_5m'])->toBe(0)
        ->and($data['live']['last_in_at'])->toBeNull()
        ->and($data['period']['clientes'])->toBe(0)
        ->and($data['areas'])->toBe(['ok' => 3])
        ->and($data['hours'])->toBe(array_fill(0, 24, 0));
});

it('la salud marca WhatsApp según la conexión del número en Evolution', function (string $state, string $expected): void {
    supFakeAll($state);

    $check = CrmChannelHealth::probe()['checks']['whatsapp'];

    expect($check['status'])->toBe($expected);

    if ($state === 'open') {
        expect($check['detail'])->toContain('+584142510805');
    }
})->with([
    'conectado' => ['open', CrmChannelHealth::OK],
    'reconectando' => ['connecting', CrmChannelHealth::WARN],
    'desconectado' => ['close', CrmChannelHealth::DOWN],
]);

it('sin clave de Evolution, WhatsApp queda «sin configurar» y no se llama a nadie', function (): void {
    config(['crm-inbox.evolution.key' => '']);
    supFakeAll();

    $check = CrmChannelHealth::probe()['checks']['whatsapp'];

    expect($check['status'])->toBe(CrmChannelHealth::UNKNOWN)
        ->and($check['detail'])->toContain('CRM_EVOLUTION_API_KEY')
        ->and(Http::recorded(fn ($request): bool => str_contains($request->url(), 'evo.test')))->toHaveCount(0);
});

it('n8n caído, bot con mensajes sin responder y bot con errores', function (): void {
    supFakeAll(n8nUp: false, live: ['stuck' => 3, 'stuck_since' => now()->subMinutes(6)->toIso8601String()]);
    $checks = CrmChannelHealth::probe()['checks'];

    expect($checks['n8n']['status'])->toBe(CrmChannelHealth::DOWN)
        ->and($checks['bot']['status'])->toBe(CrmChannelHealth::DOWN)
        ->and($checks['bot']['detail'])->toContain('3 mensaje(s) de clientes sin responder');

    CrmInboxCache::attempt(fn ($store) => $store->forget('crm-bot:live'));
    supFakeAll(live: ['errors_60m' => 4]);

    expect(CrmChannelHealth::probe()['checks']['bot']['status'])->toBe(CrmChannelHealth::WARN)
        ->and(CrmChannelHealth::worst(CrmChannelHealth::probe()['checks']))->toBe(CrmChannelHealth::WARN);
});

it('guarda una medición por minuto, abre y cierra incidentes y avisa a SUPERADMIN una sola vez', function (): void {
    if (CrmChannelHeartbeat::recipients()->isEmpty()) {
        $this->markTestSkipped('No hay SUPERADMIN activos.');
    }

    Mail::fake();
    Queue::fake();
    $start = Carbon::parse('2026-11-26 10:00:00');
    $probe = fn (string $wa): array => ['at' => 0, 'checks' => [
        'whatsapp' => ['key' => 'whatsapp', 'label' => 'WhatsApp (Evolution)', 'status' => $wa, 'detail' => $wa === 'down' ? 'Número desconectado.' : 'Conectado.'],
        'n8n' => ['key' => 'n8n', 'label' => 'Motor del bot (n8n)', 'status' => 'ok', 'detail' => 'En línea.'],
    ]];

    CrmChannelHeartbeat::record($probe('ok'), $start);
    $first = CrmChannelHeartbeat::record($probe('down'), $start->copy()->addMinute());
    $stillShort = CrmChannelHeartbeat::record($probe('down'), $start->copy()->addMinutes(2));
    $alerted = CrmChannelHeartbeat::record($probe('down'), $start->copy()->addMinutes(3));
    $again = CrmChannelHeartbeat::record($probe('down'), $start->copy()->addMinutes(4));

    expect($first['alerts'] + $stillShort['alerts'])->toBe(0)
        ->and($alerted['alerts'])->toBe(1)
        ->and($again['alerts'])->toBe(0)
        ->and(Mail::queued(InternalNotificationMail::class)->count())->toBe(CrmChannelHeartbeat::recipients()->filter(fn (User $u): bool => filter_var($u->email, FILTER_VALIDATE_EMAIL) !== false)->count());

    $open = CrmChannelHeartbeat::incidents();
    expect($open[0]['key'])->toBe('whatsapp')->and($open[0]['to'])->toBeNull();

    $recovered = CrmChannelHeartbeat::record($probe('ok'), $start->copy()->addMinutes(6));
    $closed = CrmChannelHeartbeat::incidents();

    expect($recovered['recoveries'])->toBe(1)
        ->and($closed[0]['to'])->toBe($start->copy()->addMinutes(6)->getTimestamp())
        ->and($closed[0]['status'])->toBe('down');

    $history = CrmChannelHeartbeat::history(10, $start->copy()->addMinutes(6));
    expect($history)->toHaveCount(10)
        ->and($history[9]['s']['whatsapp'])->toBe('ok')
        ->and($history[5]['s']['whatsapp'])->toBe('down')
        ->and(is_float(CrmChannelHeartbeat::uptime($start->copy()->addMinutes(6))['whatsapp']))->toBeTrue();
});

it('las tendencias miden toma a tiempo, abandonos, áreas y horas', function (): void {
    $now = Carbon::now();
    $phone = '58414'.random_int(1000000, 9999999);
    $make = function (int $acceptedMinutesAgo, ?int $takenAfterMinutes, string $area) use ($phone, $now): void {
        CrmHandoffEnvelope::query()->create([
            'handoff_id' => (string) random_int(600000000, 699999999), 'phone' => $phone, 'area' => $area, 'payload' => [],
            'accepted_at' => $now->copy()->subMinutes($acceptedMinutesAgo),
            'taken_at' => $takenAfterMinutes !== null ? $now->copy()->subMinutes($acceptedMinutesAgo - $takenAfterMinutes) : null,
        ]);
    };
    $make(120, 5, 'comercial');
    $make(110, 40, 'comercial');
    $make(100, 3, 'operaciones');
    $make(90, null, 'comercial');

    $scope = fn () => CrmHandoffEnvelope::query()->where('phone', $phone);
    $trends = CrmInboxTrends::compute($scope, $now->copy()->subHours(3), $now);

    expect($trends['sla'])->toBe(50)
        ->and($trends['abandoned'])->toBe(2)
        ->and($trends['areas'][0]['label'])->toBe(\App\Support\CrmInbox\CrmInboxAreas::label('comercial'))
        ->and($trends['areas'][0]['incoming'])->toBe(3)
        ->and(array_sum(array_column($trends['hours'], 'incoming')))->toBe(4)
        ->and(array_sum(array_column($trends['days'], 'incoming')))->toBe(4);
});

it('los hallazgos salen de los números y ponen primero lo urgente', function (): void {
    $data = ['kpis' => ['won' => 2], 'trends' => ['sla' => 60, 'sla_minutes' => 15, 'wait_median' => 1200, 'abandoned' => 3, 'abandon_minutes' => 30, 'merged' => 1,
        'areas' => [['label' => 'Comercial', 'incoming' => 8, 'wait_median' => 900], ['label' => 'Operaciones', 'incoming' => 2, 'wait_median' => 60]],
        'peak_wait_hours' => [['hour' => 14, 'incoming' => 5, 'wait_median' => 1800]]]];
    $bot = CrmBotMetrics::normalize(supMetricsPayload());

    $insights = CrmSupervisionInsights::build($data, $bot);
    $titles = array_column($insights, 'title');

    expect($insights[0]['tone'])->toBe(CrmSupervisionInsights::BAD)
        ->and(implode(' | ', $titles))->toContain('El bot resolvió solo al 70 %')
        ->and(implode(' | ', $titles))->toContain('Entre las 14:00 y las 15:00 faltan manos')
        ->and(implode(' | ', $titles))->toContain('3 caso(s) esperaron demasiado')
        ->and(implode(' | ', $titles))->toContain('40 % responde a los seguimientos');

    expect(array_column(CrmSupervisionInsights::build(['kpis' => [], 'trends' => []], null), 'title'))->toContain('Sin datos del bot');
});

it('exporta CSV para Excel (con BOM y sin fórmulas) y PDF', function (): void {
    $report = [
        'range_label' => 'Hoy',
        'data' => ['kpis' => ['incoming' => 1, 'attended' => 1, 'first_reply_median' => 60, 'finished' => 0, 'handle_median' => null, 'released' => 0, 'quotes' => 0, 'conversion' => null, 'won' => 0, 'closed' => 0],
            'trends' => ['days' => [], 'areas' => [], 'hours' => [], 'sla' => null, 'sla_minutes' => 15, 'wait_median' => null, 'abandoned' => 0, 'merged' => 0],
            'analysts' => [['name' => '=CMD()', 'state_label' => 'Disponible', 'active' => 0, 'waiting' => 0, 'attended' => 1, 'reply_median' => 30, 'finished' => 0, 'handle_median' => null, 'released' => 0, 'closed' => 0, 'won' => 0]],
            'outcomes' => []],
        'bot' => CrmBotMetrics::normalize(supMetricsPayload()),
        'insights' => [['tone' => 'info', 'title' => 'Prueba', 'text' => 'Texto']],
    ];

    $csv = CrmSupervisionExport::csv($report);
    $pdf = CrmSupervisionExport::pdf($report);

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('BOT SOL')
        ->and($csv)->toContain('Resueltos sin una persona')
        ->and($csv)->toContain("'=CMD()")
        ->and($csv)->not->toContain(',=CMD()')
        ->and($pdf)->toStartWith('%PDF');
});

it('la salud técnica es solo para SUPERADMIN: un supervisor ve el resto sin la pestaña ni la barra', function (): void {
    $permission = DB::table('permissions')->where('slug', BusinessFilamentActionPermissionRegistry::SUPERVISE_WHATSAPP_INBOX)->where('module', 'NEGOCIOS')->value('id');

    if ($permission === null) {
        $this->markTestSkipped('No existe el permiso de supervisión en la base.');
    }

    $user = new User;
    $user->forceFill(['name' => 'QA SUPERVISOR', 'email' => 'qa.sup.'.random_int(1000, 9999).'@tudrencasa.com', 'password' => bcrypt(str()->random(20)), 'status' => 'ACTIVO', 'departament' => ['NEGOCIOS']])->save();
    DB::table('user_permissions')->insert(['user_id' => $user->id, 'permission_id' => $permission, 'created_at' => now(), 'updated_at' => now()]);
    supFakeAll(metricsStatus: 404);

    auth()->login($user);
    Filament::setCurrentPanel(Filament::getPanel('business'));

    expect(SupervisionWhatsapp::canAccess())->toBeTrue()
        ->and(SupervisionWhatsapp::canSeeHealth())->toBeFalse()
        ->and(SupervisionWhatsapp::tabs())->not->toHaveKey('salud');

    $page = Livewire::withQueryParams(['vista' => 'salud'])->test(SupervisionWhatsapp::class);

    expect($page->get('tab'))->toBe('resumen')
        ->and($page->html())->not->toContain('WhatsApp (Evolution)')
        ->and($page->html())->toContain('Casos por día');
});

it('un SUPERADMIN ve la salud arriba y todas las vistas, con datos del bot', function (): void {
    $admin = User::query()->where('status', 'ACTIVO')->where('departament', 'like', '%SUPERADMIN%')->orderBy('id')->first();

    if (! $admin instanceof User) {
        $this->markTestSkipped('No hay SUPERADMIN activos.');
    }

    supFakeAll();
    auth()->login($admin);
    Filament::setCurrentPanel(Filament::getPanel('business'));

    foreach (['resumen' => 'Casos por día', 'bot' => 'Embudo del bot', 'analistas' => 'Efectividad', 'hallazgos' => 'El bot resolvió solo al 70 %', 'salud' => 'Última hora, minuto a minuto'] as $tab => $marker) {
        $html = Livewire::withQueryParams(['vista' => $tab])->test(SupervisionWhatsapp::class)->html();

        expect($html)->toContain($marker)
            ->and($html)->toContain('WhatsApp (Evolution)')
            ->and($html)->toContain('+584142510805');
    }
});

it('el vigilante del CRM mide la salud cada minuto', function (): void {
    $command = (string) file_get_contents(dirname(__DIR__, 2).'/app/Console/Commands/WatchCrmInboxCommand.php');

    expect($command)->toContain("'salud' =>")
        ->and($command)->toContain('CrmChannelHealth::probe()')
        ->and($command)->toContain('CrmChannelHeartbeat::record($probe)');
});
