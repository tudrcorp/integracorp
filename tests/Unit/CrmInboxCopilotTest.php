<?php

declare(strict_types=1);

use App\Filament\Business\Pages\AtencionWhatsapp;
use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
use App\Models\User;
use App\Support\CrmInbox\CrmInboxCopilot;
use App\Support\CrmInbox\CrmInboxDirectory;
use App\Support\CrmInbox\CrmInboxQueue;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'session.driver' => 'array',
        'cache.default' => 'array',
        'queue.default' => 'sync',
        'crm-inbox.sla.warn_minutes' => 5,
        'crm-inbox.sla.late_minutes' => 15,
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');

    if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
        throw new RuntimeException('El copiloto de la bandeja debe probarse en sqlite en memoria.');
    }

    Schema::dropIfExists('crm_handoff_envelopes');
    Schema::dropIfExists('crm_handoff_messages');
    Schema::dropIfExists('affiliates');
    Schema::dropIfExists('agents');
    Schema::dropIfExists('users');

    Schema::create('crm_handoff_envelopes', function (Blueprint $table): void {
        $table->id();
        $table->string('handoff_id', 32)->unique();
        $table->string('phone', 20);
        $table->string('area', 40)->nullable();
        $table->text('motivo')->nullable();
        $table->json('payload');
        $table->timestamp('accepted_at')->nullable();
        $table->timestamp('taken_at')->nullable();
        $table->unsignedBigInteger('taken_by')->nullable();
        $table->timestamp('released_at')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->unsignedBigInteger('closed_by')->nullable();
        $table->unsignedBigInteger('assigned_to')->nullable();
        $table->unsignedBigInteger('assigned_by')->nullable();
        $table->timestamp('assigned_at')->nullable();
        $table->unsignedBigInteger('moved_by')->nullable();
        $table->timestamp('moved_at')->nullable();
        $table->timestamp('last_customer_at')->nullable();
        $table->string('last_customer_text', 200)->nullable();
        $table->json('copilot_feedback')->nullable();
        $table->timestamps();
    });

    Schema::create('crm_handoff_messages', function (Blueprint $table): void {
        $table->id();
        $table->string('message_id', 191)->unique();
        $table->string('handoff_id', 32)->nullable();
        $table->string('phone', 20);
        $table->text('body');
        $table->timestamp('received_at')->nullable();
        $table->string('direction', 8)->default('in');
        $table->string('kind', 16)->default('message');
        $table->string('status', 16)->default('received');
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('provider_message_id', 191)->nullable();
        $table->json('meta')->nullable();
        $table->timestamps();
    });
});

it('lee edades, plan, cobertura y cédula sin confundirlos con otros números', function (): void {
    expect(CrmInboxCopilot::ages('Somos dos, yo tengo 34 y 31 años'))->toBe([34, 31])
        ->and(CrmInboxCopilot::ages('Hijos de 20 y 28 años. Interesado en viajes'))->toBe([20, 28])
        ->and(CrmInboxCopilot::ages('Plan ideal 10K para 30 años'))->toBe([30])
        ->and(CrmInboxCopilot::ages('edades: 40, 38, 9'))->toBe([40, 38, 9])
        ->and(CrmInboxCopilot::ages('somos 2 personas, 34 y 31 años'))->toBe([34, 31])
        ->and(CrmInboxCopilot::ages('tengo 2 hijos y quiero cotizar'))->toBeNull()
        ->and(CrmInboxCopilot::plan('Quiero una cotización de un plan ideal'))->toBe('ideal')
        ->and(CrmInboxCopilot::plan('Paquete Especial para mis hijos'))->toBe('especial')
        ->and(CrmInboxCopilot::plan('Inicial'))->toBe('inicial')
        ->and(CrmInboxCopilot::plan('sería ideal que me llamaran'))->toBeNull()
        ->and(CrmInboxCopilot::coverage('Plan ideal 10K para 30 años', 'ideal'))->toBe(10000)
        ->and(CrmInboxCopilot::coverage('con 20.000 de cobertura', 'especial'))->toBe(20000)
        ->and(CrmInboxCopilot::coverage('con 7000', 'ideal'))->toBeNull()
        ->and(CrmInboxCopilot::coverage('10K', 'inicial'))->toBeNull()
        ->and(CrmInboxCopilot::document('mi cédula es V-12.345.678'))->toBe(['label' => 'V-12.345.678', 'digits' => '12345678'])
        ->and(CrmInboxCopilot::document('cedula 9876543'))->toBe(['label' => '9.876.543', 'digits' => '9876543'])
        ->and(CrmInboxCopilot::document('llámame al 04121234567'))->toBeNull()
        ->and(CrmInboxCopilot::document('tengo 34 años'))->toBeNull();
});

it('arma los datos con su origen: lo que dijo el cliente gana sobre la propuesta de SolIA', function (): void {
    Carbon::setTestNow('2026-10-07 19:00:00');
    copilotEnvelope('700001', ['taken_at' => '2026-10-07 18:40:00', 'taken_by' => 9]);

    $case = CrmInboxQueue::find('700001', ['comercial'], false);
    $envelope = CrmHandoffEnvelope::query()->where('handoff_id', '700001')->firstOrFail();
    $brief = CrmInboxCopilot::brief($envelope, $case, 9, false, 300, now());
    $facts = collect($brief['facts'])->keyBy('key');

    expect($brief['summary'])->toBe('Pareja buscando el plan Ideal.')
        ->and($brief['need'])->toBe('Plan de salud familiar')
        ->and($brief['notes'])->toBe('Hijos de 20 y 28 años')
        ->and($facts['holder']['value'])->toBe('Gustavo Camacho')
        ->and($facts['holder']['source'])->toBe('Propuesta 0009004')
        ->and($facts['ages']['value'])->toBe('7')
        ->and($facts['ages']['anchor'])->toBeNull()
        ->and($facts['plan']['value'])->toBe('Ideal')
        ->and(array_column($brief['missing'], 'key'))->toBe(['document']);

    copilotMessage('700001', 'in-1', 'Somos dos, yo tengo 34 y mi esposa 31 años', '2026-10-07 18:50:00');

    $case = CrmInboxQueue::find('700001', ['comercial'], false);
    $brief = CrmInboxCopilot::brief($envelope->refresh(), $case, 9, true, 300, now());
    $facts = collect($brief['facts'])->keyBy('key');

    expect($facts['ages']['value'])->toBe('34, 31')
        ->and($facts['ages']['source'])->toBe('Cliente, 18:50')
        ->and($facts['ages']['anchor'])->toBe('crm-msg-in-1')
        ->and($brief['missing'])->toBe([])
        ->and($brief['quote'])->toBe(['holder' => 'Gustavo Camacho', 'ages' => '34, 31', 'plan' => 'ideal', 'coverage' => ''])
        ->and($brief['suggestion']['key'])->toBe('quote')
        ->and($brief['suggestion']['title'])->toBe('Cotiza el plan Ideal para 2 personas');

    Carbon::setTestNow();
});

it('solo sugiere a quien atiende el caso y no repite lo que ya se usó o no aplicaba', function (): void {
    Carbon::setTestNow('2026-10-07 19:00:00');
    copilotEnvelope('700002', ['taken_at' => '2026-10-07 18:40:00', 'taken_by' => 9, 'last_customer_at' => '2026-10-07 18:50:00']);
    copilotMessage('700002', 'in-2', 'hola, sigo esperando', '2026-10-07 18:50:00');

    $envelope = CrmHandoffEnvelope::query()->where('handoff_id', '700002')->firstOrFail();
    $case = CrmInboxQueue::find('700002', ['comercial'], false);

    expect(CrmInboxCopilot::brief($envelope, $case, 4, false, 300, now())['suggestion'])->toBeNull()
        ->and(CrmInboxCopilot::brief($envelope, $case, 9, false, 300, now())['suggestion']['key'])->toBe('quote');

    expect(CrmInboxCopilot::record($envelope, 'quote', 'dismissed', 9))->toBeTrue()
        ->and(CrmInboxCopilot::record($envelope, 'inventada', 'used', 9))->toBeFalse()
        ->and(CrmInboxCopilot::record($envelope, 'reply', 'borrado', 9))->toBeFalse();

    $next = CrmInboxCopilot::brief($envelope->refresh(), $case, 9, false, 300, now())['suggestion'];

    expect($next['key'])->toBe('reply')
        ->and($next['reason'])->toBe('Escribió hace 10 minutos y no tiene respuesta.');

    CrmInboxCopilot::record($envelope, 'reply', 'used', 9);

    expect(CrmInboxCopilot::brief($envelope->refresh(), $case, 9, false, 300, now())['suggestion']['key'])->toBe('reply');

    CrmInboxCopilot::record($envelope, 'reply', 'dismissed', 9);
    $after = CrmInboxCopilot::brief($envelope->refresh(), $case, 9, false, 300, now())['suggestion'];

    expect($after['key'])->toBe('ask_document')
        ->and($after['text'])->toBe('Gustavo, ¿me confirmas la cédula del titular para revisar tu ficha?')
        ->and(CrmInboxCopilot::brief($envelope->refresh(), $case, 9, null, 300, now())['suggestion'])->toBeNull()
        ->and(count($envelope->refresh()->copilot_feedback))->toBe(3);

    Carbon::setTestNow();
});

it('reparte la cola en carriles según quién debe mover el caso', function (): void {
    $row = fn (array $overrides): array => [
        'taken' => false,
        'taken_by' => null,
        'assigned_to' => null,
        'waiting_since' => 1000,
        'last_reply_at' => null,
        ...$overrides,
    ];

    expect(CrmInboxQueue::laneOf($row([]), 9))->toBe('open')
        ->and(CrmInboxQueue::laneOf($row(['assigned_to' => 9]), 9))->toBe('reply')
        ->and(CrmInboxQueue::laneOf($row(['assigned_to' => 4]), 9))->toBe('open')
        ->and(CrmInboxQueue::laneOf($row(['taken' => true, 'taken_by' => 4]), 9))->toBe('others')
        ->and(CrmInboxQueue::laneOf($row(['taken' => true, 'taken_by' => 9]), 9))->toBe('reply')
        ->and(CrmInboxQueue::laneOf($row(['taken' => true, 'taken_by' => 9, 'last_reply_at' => 1200]), 9))->toBe('waiting')
        ->and(CrmInboxQueue::laneOf($row(['taken' => true, 'taken_by' => 9, 'last_reply_at' => 900]), 9))->toBe('reply')
        ->and(CrmInboxQueue::laneOf($row(['taken' => true, 'taken_by' => 9]), null))->toBe('others');

    Carbon::setTestNow('2026-10-07 19:00:00');
    copilotEnvelope('700003', ['taken_at' => '2026-10-07 18:40:00', 'taken_by' => 9, 'last_customer_at' => '2026-10-07 18:30:00']);
    copilotEnvelope('700004', ['phone' => '584120000004']);
    copilotMessage('700003', 'out-1', 'Hola, soy Ana', '2026-10-07 18:41:00', 'out');
    copilotMessage('700003', 'note-1', 'nota que no cuenta', '2026-10-07 18:59:00', 'out', 'note');

    $snapshot = CrmInboxQueue::snapshot(['comercial'], false, 25);
    $lanes = collect(CrmInboxQueue::lanes($snapshot['rows'], 9))->mapWithKeys(fn (array $lane): array => [$lane['key'] => array_column($lane['rows'], 'handoff_id')]);

    expect($lanes->all())->toBe([
        'open' => ['700004'],
        'waiting' => ['700003'],
    ]);

    Carbon::setTestNow();
});

it('busca la ficha por la cédula que el cliente escribió y la vuelve a buscar si cambia', function (): void {
    Schema::create('affiliates', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_id')->nullable();
        $table->string('full_name')->nullable();
        $table->string('phone')->nullable();
        $table->string('nro_identificacion')->nullable();
    });
    Schema::create('agents', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('phone')->nullable();
        $table->string('ci')->nullable();
    });
    DB::table('affiliates')->insert(['id' => 7, 'affiliation_id' => 15, 'full_name' => 'Gustavo Camacho', 'phone' => '04129999999', 'nro_identificacion' => '12345678']);

    expect(CrmInboxDirectory::documentVariants('V-12.345.678'))->toContain('12345678', 'V12345678', 'V-12345678')
        ->and(CrmInboxDirectory::documentVariants('123'))->toBe([])
        ->and(CrmInboxDirectory::matchDocument('12345678'))->toMatchArray(['kind' => 'afiliado', 'id' => 7])
        ->and(CrmInboxDirectory::matchDocument('11111111'))->toBeNull();

    Carbon::setTestNow('2026-10-07 19:00:00');
    copilotEnvelope('700005', ['taken_at' => '2026-10-07 18:40:00', 'taken_by' => 9]);
    copilotMessage('700005', 'in-5', 'mi cédula es 11.111.111', '2026-10-07 18:45:00');
    actingAsCopilotAnalyst();

    $page = Livewire::test(AtencionWhatsapp::class)
        ->call('select', '700005')
        ->call('loadDirectory')
        ->assertSet('directory', ['status' => 'none', 'document' => '11111111'])
        ->assertSee('11.111.111');

    copilotMessage('700005', 'in-6', 'perdón, es V-12.345.678', '2026-10-07 18:46:00');

    $page->call('heartbeat')
        ->assertSet('directory', null)
        ->call('loadDirectory')
        ->assertSet('directory.status', 'match')
        ->assertSet('directory.via', 'document')
        ->assertSee('por cédula');

    Carbon::setTestNow();
});

it('la página muestra carriles y copiloto, y la sugerencia llena el cotizador sin confiar en el navegador', function (): void {
    Carbon::setTestNow('2026-10-07 19:00:00');
    copilotEnvelope('700006', ['taken_at' => '2026-10-07 18:40:00', 'taken_by' => 9, 'last_customer_at' => '2026-10-07 18:50:00']);
    copilotEnvelope('700007', ['phone' => '584120000007']);
    copilotMessage('700006', 'in-7', 'Plan ideal 10K, somos de 34 y 31 años', '2026-10-07 18:50:00');
    actingAsCopilotAnalyst();

    $page = Livewire::test(AtencionWhatsapp::class)
        ->assertSee('Te toca responder')
        ->assertSee('Sin tomar')
        ->call('select', '700006')
        ->assertSee('Lo que busca')
        ->assertSee('Pareja buscando el plan Ideal.')
        ->assertSee('Plan de salud familiar')
        ->assertSee('Datos para cotizar')
        ->assertSee('Cliente, 18:50')
        ->assertSee('Cotiza el plan Ideal para 2 personas')
        ->call('useSuggestion', 'ask_document')
        ->assertSet('quoteOpen', false);

    expect(CrmHandoffEnvelope::query()->where('handoff_id', '700006')->value('copilot_feedback'))->toBeNull();

    $page->call('useSuggestion', 'quote')
        ->assertSet('quoteOpen', true)
        ->assertSet('quoteHolder', 'Gustavo Camacho')
        ->assertSet('quoteAges', '34, 31')
        ->assertSet('quotePlan', 'ideal')
        ->assertSet('quoteCoverage', '10000');

    $feedback = CrmHandoffEnvelope::query()->where('handoff_id', '700006')->firstOrFail()->copilot_feedback;

    expect($feedback)->toHaveCount(1)
        ->and($feedback[0])->toMatchArray(['key' => 'quote', 'action' => 'used', 'user_id' => 9]);

    $page->call('discardQuote')
        ->assertDontSee('Cotiza el plan Ideal para 2 personas')
        ->call('select', '700007')
        ->call('prefillQuote')
        ->assertSet('quoteOpen', false);

    Carbon::setTestNow();
});

it('avisa quién pasó el caso y lo resume antes de tomarlo', function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
    DB::table('users')->insert(['id' => 4, 'name' => 'Luis Mora']);

    Carbon::setTestNow('2026-10-07 19:00:00');
    copilotEnvelope('700008', ['assigned_to' => 9, 'assigned_by' => 4, 'assigned_at' => '2026-10-07 18:55:00']);

    $envelope = CrmHandoffEnvelope::query()->where('handoff_id', '700008')->firstOrFail();
    $case = CrmInboxQueue::find('700008', ['comercial'], false);

    expect(CrmInboxCopilot::brief($envelope, $case, 9, null, 300, now())['handover'])->toBe(['label' => 'Luis te lo pasó', 'time' => '07/10 18:55'])
        ->and(CrmInboxCopilot::brief($envelope, $case, 4, null, 300, now())['handover'])->toBeNull()
        ->and(CrmInboxCopilot::brief($envelope, $case, 5, null, 300, now())['handover']['label'])->toBe('Luis lo marcó para un compañero');

    CrmHandoffEnvelope::query()->whereKey($envelope->id)->update(['assigned_to' => null, 'assigned_by' => null, 'assigned_at' => null, 'moved_by' => 4, 'moved_at' => '2026-10-07 18:58:00']);

    expect(CrmInboxCopilot::brief($envelope->refresh(), $case, 9, null, 300, now())['handover']['label'])->toBe('Luis lo pasó a Comercial');

    Carbon::setTestNow();
});

it('la cola muestra los tres carriles propios aunque estén vacíos y la última respuesta de cada caso', function (): void {
    Carbon::setTestNow('2026-10-07 19:00:00');
    copilotEnvelope('700010', ['taken_at' => '2026-10-07 18:40:00', 'taken_by' => 9, 'last_customer_at' => '2026-10-07 18:45:00']);
    copilotMessage('700010', 'out-10', 'Te envío la propuesta', '2026-10-07 18:50:00', 'out', 'quote');
    copilotMessage('700010', 'out-11', 'Gustavo, ¿pudiste revisarla?', '2026-10-07 18:55:00', 'out');
    copilotMessage('700010', 'note-10', 'nota interna que no es respuesta', '2026-10-07 18:58:00', 'out', 'note');
    CrmHandoffMessage::query()->where('message_id', 'out-11')->update(['status' => 'sent']);

    $rows = CrmInboxQueue::snapshot(['comercial'], false, 25)['rows'];
    $lanes = collect(CrmInboxQueue::lanes($rows, 9, true));

    expect($lanes->pluck('key')->all())->toBe(['reply', 'open', 'waiting'])
        ->and($lanes->firstWhere('key', 'reply')['empty'])->toBe('Al día')
        ->and($lanes->firstWhere('key', 'waiting')['rows'][0]['last_reply_text'])->toBe('Gustavo, ¿pudiste revisarla?')
        ->and($lanes->firstWhere('key', 'waiting')['rows'][0]['last_quote'])->toBe('Te envío la propuesta')
        ->and(collect(CrmInboxQueue::lanes($rows, 4, true))->pluck('key')->all())->toBe(['reply', 'open', 'waiting', 'others']);

    actingAsCopilotAnalyst();

    Livewire::test(AtencionWhatsapp::class)
        ->assertSee('Al día')
        ->assertSee('Nadie esperando')
        ->assertSee('Esperando al cliente')
        ->assertSee('Tú: Gustavo, ¿pudiste revisarla?')
        ->assertSee('Sin respuesta');

    Carbon::setTestNow();
});

it('el botón Cotizar abre el formulario lleno con lo que ya se sabe y no pisa lo que el analista escribió', function (): void {
    Carbon::setTestNow('2026-10-07 19:00:00');
    copilotEnvelope('700011', ['taken_at' => '2026-10-07 18:40:00', 'taken_by' => 9]);
    copilotMessage('700011', 'in-11', 'Plan ideal 10K para 34 y 31 años', '2026-10-07 18:50:00');
    actingAsCopilotAnalyst();

    Livewire::test(AtencionWhatsapp::class)
        ->call('select', '700011')
        ->assertSee('Escribe a Gustavo…')
        ->call('openQuote')
        ->assertSet('quoteOpen', true)
        ->assertSet('quoteAges', '34, 31')
        ->assertSet('quotePlan', 'ideal')
        ->assertSet('quoteCoverage', '10000')
        ->set('quoteAges', '40')
        ->set('quoteOpen', false)
        ->call('openQuote')
        ->assertSet('quoteAges', '40');

    Carbon::setTestNow();
});

it('el panel del cliente dice desde cuándo lo conocemos, qué falta y qué propuestas hubo', function (): void {
    Carbon::setTestNow('2026-10-07 19:00:00');
    copilotEnvelope('700012', [
        'taken_at' => '2026-10-07 18:40:00',
        'taken_by' => 9,
        'payload' => [
            'name' => 'Gustavo Camacho',
            'lead' => ['necesidad' => 'Plan de salud familiar', 'created_at' => '2026-09-17T11:56:44+00:00'],
            'cotizacion' => ['control' => '0009004', 'total' => 189, 'quote_json' => ['titular' => 'Gustavo Camacho', 'personas' => [['edad' => 7]], 'planes_solicitados' => ['ideal']]],
        ],
    ]);
    copilotEnvelope('700013', ['closed_at' => '2026-10-01 10:00:00']);
    CrmHandoffMessage::query()->create([
        'message_id' => 'quote-12',
        'handoff_id' => '700012',
        'phone' => '584120000002',
        'body' => 'Propuesta 0009009 · USD 2.192,00 al año',
        'received_at' => '2026-10-07 18:55:00',
        'direction' => 'out',
        'kind' => 'quote',
        'status' => 'sent',
        'meta' => ['control' => '0009009', 'total' => '2.192,00', 'plan' => 'Ideal', 'people' => 2, 'ages' => '34, 31', 'coverage' => 10000],
    ]);

    $envelope = CrmHandoffEnvelope::query()->where('handoff_id', '700012')->firstOrFail();
    $brief = CrmInboxCopilot::brief($envelope, CrmInboxQueue::find('700012', ['comercial'], false), 9, false, 300, now());

    expect($brief['client'])->toBe(['since' => '17/09/2026', 'conversations' => 2])
        ->and($brief['ready'])->toBe(3)
        ->and($brief['asks']['document'])->toBe('Gustavo, ¿me confirmas la cédula del titular para revisar tu ficha?')
        ->and($brief['proposals'])->toBe([
            ['by' => 'SolIA', 'mine' => false, 'control' => '0009004', 'detail' => 'Ideal · 1 persona', 'total' => 'USD 189,00'],
            ['by' => 'Equipo', 'mine' => true, 'control' => '0009009', 'detail' => 'Ideal · 2 personas', 'total' => 'USD 2.192,00'],
        ]);

    actingAsCopilotAnalyst();

    Livewire::test(AtencionWhatsapp::class)
        ->call('select', '700012')
        ->assertSee('Cliente desde 17/09/2026')
        ->assertSee('2.ª conversación')
        ->assertSee('3 de 4')
        ->assertSee('Pedirlo')
        ->assertSee('Personas')
        ->assertSee('34, 31 años')
        ->assertSee('Ajustar y reenviar');

    Carbon::setTestNow();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function copilotEnvelope(string $handoffId, array $overrides = []): void
{
    CrmHandoffEnvelope::factory()->create([
        'handoff_id' => $handoffId,
        'phone' => '584120000002',
        'area' => 'comercial',
        'motivo' => 'pide una persona',
        'payload' => [
            'name' => 'Gustavo Camacho',
            'motivo' => 'pide una persona',
            'necesidad' => 'Quiere hablar con un asesor',
            'lead' => ['necesidad' => 'Plan de salud familiar', 'notas' => 'Hijos de 20 y 28 años'],
            'sesion' => ['last_summary' => 'Pareja buscando el plan Ideal.'],
            'cotizacion' => [
                'control' => '0009004',
                'total' => 189,
                'quote_json' => [
                    'titular' => 'Gustavo Camacho',
                    'personas' => [['edad' => 7, 'nombre' => 'Afiliado 1']],
                    'planes_solicitados' => ['ideal'],
                ],
            ],
            'mensajes' => [
                ['direction' => 'in', 'text' => 'Quiero hablar con un humano', 'hora' => '18:16'],
            ],
        ],
        'accepted_at' => '2026-10-07 18:30:00',
        ...$overrides,
    ]);
}

function copilotMessage(string $handoffId, string $messageId, string $body, string $at, string $direction = 'in', string $kind = 'message'): void
{
    CrmHandoffMessage::query()->create([
        'message_id' => $messageId,
        'handoff_id' => $handoffId,
        'phone' => '584120000002',
        'body' => $body,
        'received_at' => $at,
        'direction' => $direction,
        'kind' => $kind,
        'status' => $direction === 'out' ? 'sent' : 'received',
    ]);
}

function actingAsCopilotAnalyst(): void
{
    test()->actingAs(User::factory()->make([
        'id' => 9,
        'name' => 'Ana Rojas',
        'email' => 'analista@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS'],
    ]));
    Filament::setCurrentPanel('business');
}
