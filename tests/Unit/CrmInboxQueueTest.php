<?php

declare(strict_types=1);

use App\Filament\Administration\Pages\AtencionWhatsapp as AdministrationAtencionWhatsapp;
use App\Filament\Business\Pages\AtencionWhatsapp;
use App\Filament\Operations\Pages\AtencionWhatsapp as OperationsAtencionWhatsapp;
use App\Jobs\NotifyCrmHandoffJob;
use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
use App\Models\User;
use App\Support\CrmInbox\CrmAnalystName;
use App\Support\CrmInbox\CrmHandoffAssign;
use App\Support\CrmInbox\CrmHandoffMove;
use App\Support\CrmInbox\CrmInboxColleagues;
use App\Support\CrmInbox\CrmInboxDirectory;
use App\Support\CrmInbox\CrmInboxQueue;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');

    if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
        throw new RuntimeException('La bandeja de WhatsApp debe probarse en sqlite en memoria.');
    }

    Schema::dropIfExists('crm_handoff_envelopes');
    Schema::dropIfExists('affiliates');
    Schema::dropIfExists('agents');

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

    Schema::dropIfExists('crm_handoff_messages');
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

it('ordena por la espera más larga y cada panel ve solo su área', function (): void {
    Carbon::setTestNow('2026-10-06 19:00:00');

    crmInboxEnvelope('900002', 'comercial', '2026-10-06 16:00:00', 'María Peña', 'el precio me parece alto');
    crmInboxEnvelope('900003', 'corporativo', '2026-10-06 18:20:00', 'Empresa Río', 'somos 40 personas');
    crmInboxEnvelope('900004', 'operaciones', '2026-10-06 17:00:00', 'Luis Mora', 'necesito una cita');
    crmInboxEnvelope('900006', null, '2026-10-06 15:00:00', 'Sin área', 'no sé a quién escribir');

    $business = CrmInboxQueue::snapshot(['comercial', 'corporativo', 'aliados'], true, 25);
    $operations = CrmInboxQueue::snapshot(['operaciones', 'emergencias'], false, 25);

    expect($business['count'])->toBe(3)
        ->and(array_column($business['rows'], 'handoff_id'))->toBe(['900006', '900002', '900003'])
        ->and($business['rows'][1]['name'])->toBe('María Peña')
        ->and($business['rows'][1]['last_line'])->toBe('el precio me parece alto')
        ->and($business['rows'][1]['waiting'])->toBe('hace 3 horas')
        ->and($business['rows'][1]['area_label'])->toBe('Comercial')
        ->and(array_column($operations['rows'], 'handoff_id'))->toBe(['900004']);

    $case = CrmInboxQueue::find('900002', ['comercial', 'corporativo', 'aliados'], true);

    expect($case)->not->toBeNull()
        ->and($case['summary_lines'])->toBe([
            'Motivo: el precio',
            'Necesidad: plan familiar',
            'Propuesta: 0009018 · 186,00',
        ])
        ->and($case['messages'][1]['author'])->toBe('Cliente')
        ->and($case['phone_label'])->toBe('+58 412 000 0002')
        ->and(CrmInboxQueue::find('900004', ['comercial', 'corporativo', 'aliados'], true))->toBeNull();

    Carbon::setTestNow();
});

it('muestra las primeras 25 y deja el resto para ver más', function (): void {
    foreach (range(1, 26) as $index) {
        crmInboxEnvelope(
            (string) (810000 + $index),
            'comercial',
            Carbon::parse('2026-10-06 12:00:00')->addMinutes($index)->toDateTimeString(),
            'Persona '.$index,
            'frase '.$index,
        );
    }

    $board = CrmInboxQueue::snapshot(['comercial'], false, CrmInboxQueue::PAGE_SIZE);

    expect($board['count'])->toBe(26)
        ->and($board['rows'])->toHaveCount(25)
        ->and($board['rows'][0]['name'])->toBe('Persona 1')
        ->and(array_column($board['rows'], 'name'))->not->toContain('Persona 26');
});

it('encuentra la ficha por el teléfono sin adivinar coincidencias parciales', function (): void {
    Schema::create('affiliates', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_id')->nullable();
        $table->string('full_name')->nullable();
        $table->string('phone')->nullable();
    });
    Schema::create('agents', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('phone')->nullable();
    });

    DB::table('affiliates')->insert([
        'id' => 7,
        'affiliation_id' => 15,
        'full_name' => 'María Peña',
        'phone' => '04120000002',
    ]);
    DB::table('agents')->insert([
        'id' => 3,
        'name' => 'Otro',
        'phone' => '04120000099',
    ]);

    expect(CrmInboxDirectory::variants('584120000002'))->toContain('04120000002')
        ->and(CrmInboxDirectory::match('584120000002'))->toMatchArray([
            'kind' => 'afiliado',
            'id' => 7,
            'affiliation_id' => 15,
            'name' => 'María Peña',
        ])
        ->and(CrmInboxDirectory::match('584120000099'))->toMatchArray([
            'kind' => 'agente',
            'id' => 3,
        ])
        ->and(CrmInboxDirectory::match('584120000000'))->toBeNull();
});

it('la página solo se lee y cada panel autoriza a su departamento', function (): void {
    $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/crm/pages/atencion-whatsapp.blade.php');
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Pages/AtencionWhatsapp.php');

    expect($blade)->toContain('<textarea')
        ->and($blade)->toContain('Tomar')
        ->and($blade)->toContain('Enviar por WhatsApp')
        ->and($blade)->toContain('Nota interna')
        ->and($blade)->toContain('Reintentar')
        ->and($blade)->toContain('Devolver al bot')
        ->and($blade)->toContain('Cotizar y enviar el PDF')
        ->and($blade)->toContain('previewQuote')
        ->and($blade)->toContain('x-on:crm-ask="ask($event.detail)"')
        ->and($blade)->toContain("action === 'release'")
        ->and(substr_count($blade, "action: 'release'"))->toBe(2)
        ->and(substr_count($blade, "\$dispatch('crm-ask'"))->toBe(3)
        ->and($blade)->toContain('ask({')
        ->and($blade)->toContain('x-trap.noscroll')
        ->and($blade)->toContain('wire:poll.3s="heartbeat"')
        ->and($blade)->toContain('wire:poll.10s="heartbeat"')
        ->and($blade)->toContain('$wire.loadDirectory()')
        ->and($blade)->toContain('wire:offline')
        ->and($blade)->toContain('sessionStorage')
        ->and($blade)->not->toContain('wire:confirm')
        ->and($blade)->toContain('SolIA retomará la conversación')
        ->and($blade)->toContain('!$event.shiftKey')
        ->and($blade)->toContain('scrollHeight')
        ->and($blade)->toContain('Buscar en la cola')
        ->and($blade)->toContain('En atención')
        ->and($blade)->toContain('Activar avisos')
        ->and($blade)->toContain('/crm-inbox/sw.js')
        ->and($blade)->toContain('Cerrar')
        ->and($blade)->toContain('No puedo atender')
        ->and($blade)->toContain('Pasar a otro equipo')
        ->and($blade)->toContain('crm-open')
        ->and($blade)->toContain('incomingTitle')
        ->and($blade)->toContain('announce')
        ->and($blade)->toContain('white-space: pre-line')
        ->and($blade)->toContain('El cliente no recibe ningún mensaje')
        ->and($blade)->toContain('overflow: hidden')
        ->and($page)->toContain('Atención WhatsApp')
        ->and($page)->toContain('AuthorizesDepartmentNavigation')
        ->and($page)->toContain('Width::Full');

    expect(AtencionWhatsapp::canAccess())->toBeFalse();

    $this->actingAs(User::factory()->make([
        'id' => 9,
        'email' => 'analista@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS'],
    ]));

    expect(AtencionWhatsapp::canAccess())->toBeTrue()
        ->and(OperationsAtencionWhatsapp::canAccess())->toBeFalse()
        ->and(AdministrationAtencionWhatsapp::canAccess())->toBeFalse();

    $this->actingAs(User::factory()->make([
        'id' => 10,
        'email' => 'super@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN'],
    ]));

    expect(OperationsAtencionWhatsapp::canAccess())->toBeTrue()
        ->and(AdministrationAtencionWhatsapp::canAccess())->toBeTrue();
});

it('muestra la cola en el panel y no abre un caso de otra área', function (): void {
    Carbon::setTestNow('2026-10-06 19:00:00');
    crmInboxEnvelope('900002', 'comercial', '2026-10-06 16:00:00', 'María Peña', 'el precio me parece alto');
    crmInboxEnvelope('900004', 'operaciones', '2026-10-06 17:00:00', 'Luis Mora', 'necesito una cita');

    $this->actingAs(User::factory()->make([
        'id' => 9,
        'name' => 'Ana Rojas',
        'email' => 'analista@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS', 'SUPERADMIN'],
    ]));
    Filament::setCurrentPanel('business');
    config([
        'crm-inbox.takeover_url' => '',
        'crm-inbox.takeover_key' => '',
        'crm-inbox.reply_url' => '',
    ]);

    Livewire::test(AtencionWhatsapp::class)
        ->assertSee('María Peña')
        ->assertSee('el precio me parece alto')
        ->assertDontSee('necesito una cita')
        ->assertDontSee('Tomar')
        ->call('select', '900004')
        ->assertSet('selectedHandoffId', null)
        ->call('select', '900002')
        ->assertSet('selectedHandoffId', '900002')
        ->assertSee('0009018')
        ->assertSee('USD 186,00')
        ->assertSet('directory', null)
        ->call('loadDirectory')
        ->assertSet('directory', ['status' => 'none'])
        ->assertSee('Sin ficha en IntegraCorp')
        ->call('toggleSummary')
        ->assertSet('summaryOpen', true)
        ->assertSee('Cliente')
        ->assertSee('Tomar')
        ->call('take')
        ->assertNotified('No se pudo tomar el caso');

    config(['crm-inbox.takeover_url' => 'http://n8n.test/webhook/handoff/takeover', 'crm-inbox.takeover_key' => 'local-key']);
    Http::fake([
        'http://n8n.test/*' => Http::response(['ok' => true, 'sessions_updated' => 1], 200),
    ]);

    Livewire::test(AtencionWhatsapp::class)
        ->call('select', '900002')
        ->call('take')
        ->assertNotified('Caso tomado')
        ->assertSee('El bot ya no responde')
        ->assertSee('Hola, soy Ana. Ya estoy aquí para ayudarte.')
        ->call('take')
        ->assertNotified('El caso ya estaba tomado');

    expect(CrmHandoffEnvelope::query()->where('handoff_id', '900002')->value('taken_at'))->not->toBeNull()
        ->and(CrmHandoffMessage::query()->where('kind', 'intro')->count())->toBe(1);

    Carbon::setTestNow();
});

it('envía la respuesta una sola vez y la nota no sale de IntegraCorp', function (): void {
    crmInboxEnvelope('900002', 'comercial', '2026-10-06 16:00:00', 'María Peña', 'el precio me parece alto');

    $this->actingAs(User::factory()->make([
        'id' => 9,
        'name' => 'Ana Rojas',
        'email' => 'analista@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS', 'SUPERADMIN'],
    ]));
    Filament::setCurrentPanel('business');

    $replyId = '11111111-1111-4111-8111-111111111111';

    Livewire::test(AtencionWhatsapp::class)
        ->call('select', '900002')
        ->call('stageReply', $replyId, 'hola desde el panel')
        ->assertNotified('Toma el caso antes de escribir');

    expect(CrmHandoffMessage::query()->count())->toBe(0);

    $replyCalls = 0;
    config([
        'crm-inbox.takeover_url' => 'http://n8n.test/webhook/handoff/takeover',
        'crm-inbox.takeover_key' => 'local-key',
    ]);
    Http::fake(function ($request) use (&$replyCalls) {
        if (str_ends_with($request->url(), '/handoff/takeover')) {
            return Http::response(['ok' => true, 'sessions_updated' => 1], 200);
        }

        if (str_ends_with($request->url(), '/handoff/reply')) {
            if (str_contains((string) $request['text'], 'Ya estoy aquí para ayudarte')) {
                return Http::response(['ok' => true, 'message_id' => 'wamid.INTRO'], 200);
            }

            $replyCalls++;

            if ($replyCalls === 1) {
                return Http::response(['ok' => false], 502);
            }

            return Http::response(['ok' => true, 'message_id' => 'wamid.REPLY1'], 200);
        }

        return Http::response(['ok' => false], 500);
    });

    Livewire::test(AtencionWhatsapp::class)
        ->call('select', '900002')
        ->call('take')
        ->assertSee('Enviar por WhatsApp')
        ->call('stageReply', $replyId, 'hola desde el panel')
        ->assertSee('Enviando…')
        ->call('deliverReply', $replyId)
        ->assertNotified('No se pudo enviar por WhatsApp')
        ->assertSee('Reintentar')
        ->call('deliverReply', $replyId)
        ->assertDontSee('Reintentar')
        ->call('deliverReply', $replyId)
        ->call('saveNote', 'solo el equipo')
        ->assertSee('Nota interna')
        ->assertSee('solo el equipo')
        ->call('saveNote', '   ');

    expect($replyCalls)->toBe(2)
        ->and(CrmHandoffMessage::query()->where('message_id', $replyId)->value('status'))->toBe('sent')
        ->and(CrmHandoffMessage::query()->where('message_id', $replyId)->value('provider_message_id'))->toBe('wamid.REPLY1')
        ->and(CrmHandoffMessage::query()->where('kind', 'intro')->value('body'))->toBe('Hola, soy Ana. Ya estoy aquí para ayudarte.')
        ->and(CrmHandoffMessage::query()->where('kind', 'note')->count())->toBe(1)
        ->and(CrmHandoffMessage::query()->count())->toBe(3);

    Http::assertSent(function ($request) use ($replyId): bool {
        return str_ends_with($request->url(), '/handoff/reply')
            && $request['reply_id'] === $replyId
            && $request['text'] === 'hola desde el panel'
            && $request->hasHeader('X-Handoff-Key');
    });
});

it('devuelve el caso al bot solo cuando n8n confirma', function (): void {
    crmInboxEnvelope('900002', 'comercial', '2026-10-06 16:00:00', 'María Peña', 'el precio me parece alto');

    $this->actingAs(User::factory()->make([
        'id' => 9,
        'name' => 'María López',
        'email' => 'analista@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS', 'SUPERADMIN'],
    ]));
    Filament::setCurrentPanel('business');
    config([
        'crm-inbox.takeover_url' => 'http://n8n.test/webhook/handoff/takeover',
        'crm-inbox.takeover_key' => 'local-key',
        'crm-inbox.release_url' => '',
    ]);

    $releaseCalls = 0;
    $closingCalls = 0;
    Http::fake(function ($request) use (&$releaseCalls, &$closingCalls) {
        if (str_ends_with($request->url(), '/handoff/takeover')) {
            return Http::response(['ok' => true, 'sessions_updated' => 1], 200);
        }

        if (str_ends_with($request->url(), '/handoff/reply')) {
            if (str_contains((string) $request['text'], 'Soy SolIA')) {
                $closingCalls++;
            }

            return Http::response(['ok' => true, 'message_id' => 'wamid.BYE'], 200);
        }

        if (str_ends_with($request->url(), '/handoff/release')) {
            $releaseCalls++;

            if ($releaseCalls === 1) {
                return Http::response(['ok' => false], 502);
            }

            return Http::response(['ok' => true, 'sessions_updated' => 1, 'handoffs_closed' => 1], 200);
        }

        return Http::response(['ok' => false], 500);
    });

    Livewire::test(AtencionWhatsapp::class)
        ->call('select', '900002')
        ->call('release')
        ->assertNotified('Toma el caso antes de devolverlo')
        ->call('take')
        ->assertSee('Devolver al bot')
        ->call('release')
        ->assertNotified('No se pudo devolver el caso')
        ->assertSee('Enviar por WhatsApp')
        ->assertSee('María Peña')
        ->call('release')
        ->assertNotified('El bot volvió a responder')
        ->assertSet('selectedHandoffId', null)
        ->assertSee('Nadie está esperando.')
        ->assertDontSee('María Peña');

    expect($releaseCalls)->toBe(2)
        ->and($closingCalls)->toBe(1)
        ->and(CrmHandoffMessage::query()->where('kind', 'closing')->value('body'))->toBe('María ya cerró esta parte. Soy SolIA y retomo la conversación. Escríbeme cuando quieras y seguimos con lo que necesitas.')
        ->and(CrmHandoffEnvelope::query()->where('handoff_id', '900002')->value('released_at'))->not->toBeNull()
        ->and(CrmInboxQueue::snapshot(['comercial'], false, 25)['count'])->toBe(0);
});

it('no devuelve el caso si el cliente no recibió el cierre', function (): void {
    crmInboxEnvelope('900002', 'comercial', '2026-10-06 16:00:00', 'María Peña', 'el precio me parece alto');

    $this->actingAs(User::factory()->make([
        'id' => 9,
        'name' => 'María López',
        'email' => 'analista@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS', 'SUPERADMIN'],
    ]));
    Filament::setCurrentPanel('business');
    config([
        'crm-inbox.takeover_url' => 'http://n8n.test/webhook/handoff/takeover',
        'crm-inbox.takeover_key' => 'local-key',
    ]);

    $releaseCalls = 0;
    Http::fake(function ($request) use (&$releaseCalls) {
        if (str_ends_with($request->url(), '/handoff/takeover')) {
            return Http::response(['ok' => true], 200);
        }

        if (str_ends_with($request->url(), '/handoff/reply')) {
            if (str_contains((string) $request['text'], 'Soy SolIA')) {
                return Http::response(['ok' => false], 502);
            }

            return Http::response(['ok' => true, 'message_id' => 'wamid.INTRO'], 200);
        }

        if (str_ends_with($request->url(), '/handoff/release')) {
            $releaseCalls++;

            return Http::response(['ok' => true], 200);
        }

        return Http::response(['ok' => false], 500);
    });

    Livewire::test(AtencionWhatsapp::class)
        ->call('select', '900002')
        ->call('take')
        ->call('release')
        ->assertNotified('No se pudo avisar al cliente')
        ->assertSee('Reintentar')
        ->assertSee('Enviar por WhatsApp');

    expect($releaseCalls)->toBe(0)
        ->and(CrmHandoffEnvelope::query()->where('handoff_id', '900002')->value('released_at'))->toBeNull()
        ->and(CrmHandoffMessage::query()->where('kind', 'closing')->value('status'))->toBe('failed');
});

it('firma el mensaje cuando escribe alguien distinto de quien tomó el caso', function (): void {
    crmInboxEnvelope('900002', 'comercial', '2026-10-06 16:00:00', 'María Peña', 'el precio me parece alto');
    CrmHandoffEnvelope::query()->where('handoff_id', '900002')->update([
        'taken_at' => '2026-10-06 18:00:00',
        'taken_by' => 9,
    ]);

    $this->actingAs(User::factory()->make([
        'id' => 4,
        'name' => 'Carlos Méndez',
        'email' => 'carlos@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS', 'SUPERADMIN'],
    ]));
    Filament::setCurrentPanel('business');

    $replyId = '22222222-2222-4222-8222-222222222222';

    Livewire::test(AtencionWhatsapp::class)
        ->call('select', '900002')
        ->call('stageReply', $replyId, 'sigo con tu caso');

    expect(CrmHandoffMessage::query()->where('message_id', $replyId)->value('body'))->toBe('Carlos: sigo con tu caso');
});

it('usa el primer nombre y no un correo', function (): void {
    expect(CrmAnalystName::given('María López'))->toBe('María')
        ->and(CrmAnalystName::given('  Ana   Rojas  '))->toBe('Ana')
        ->and(CrmAnalystName::given('bot@tudrencasa.com'))->toBe('')
        ->and(CrmAnalystName::given(''))->toBe('')
        ->and(CrmAnalystName::introduction('María'))->toBe('Hola, soy María. Ya estoy aquí para ayudarte.')
        ->and(CrmAnalystName::introduction(''))->toBe('Hola, ya estoy aquí para ayudarte.')
        ->and(CrmAnalystName::closing('María'))->toBe('María ya cerró esta parte. Soy SolIA y retomo la conversación. Escríbeme cuando quieras y seguimos con lo que necesitas.')
        ->and(CrmAnalystName::closing(''))->toContain('Quien te estaba atendiendo ya cerró esta parte.');
});

it('el cierre nombra a quien tomó el caso aunque lo devuelva otra persona', function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
    DB::table('users')->insert([
        'id' => 3,
        'name' => 'Laura Díaz',
    ]);

    crmInboxEnvelope('900002', 'comercial', '2026-10-06 16:00:00', 'María Peña', 'el precio me parece alto');
    CrmHandoffEnvelope::query()->where('handoff_id', '900002')->update([
        'taken_at' => '2026-10-06 18:00:00',
        'taken_by' => 3,
    ]);

    $this->actingAs(User::factory()->make([
        'id' => 9,
        'name' => 'María López',
        'email' => 'analista@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS', 'SUPERADMIN'],
    ]));
    Filament::setCurrentPanel('business');
    config([
        'crm-inbox.takeover_url' => 'http://n8n.test/webhook/handoff/takeover',
        'crm-inbox.takeover_key' => 'local-key',
    ]);
    Http::fake([
        'http://n8n.test/*' => Http::response(['ok' => true, 'message_id' => 'wamid.BYE'], 200),
    ]);

    Livewire::test(AtencionWhatsapp::class)
        ->call('select', '900002')
        ->call('release')
        ->assertNotified('El bot volvió a responder');

    expect(CrmHandoffMessage::query()->where('kind', 'closing')->value('body'))->toBe('Laura ya cerró esta parte. Soy SolIA y retomo la conversación. Escríbeme cuando quieras y seguimos con lo que necesitas.');
});

it('cierra un caso en espera y no cierra uno que ya está en atención', function (): void {
    crmInboxEnvelope('900002', 'comercial', '2026-10-06 16:00:00', 'María Peña', 'el precio me parece alto');
    crmInboxEnvelope('900006', 'comercial', '2026-10-06 17:00:00', 'Otra persona', 'sigo esperando');
    CrmHandoffEnvelope::query()->where('handoff_id', '900006')->update([
        'taken_at' => '2026-10-06 17:10:00',
        'taken_by' => 9,
    ]);

    $this->actingAs(User::factory()->make([
        'id' => 9,
        'name' => 'Ana Rojas',
        'email' => 'analista@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS'],
    ]));
    Filament::setCurrentPanel('business');

    Livewire::test(AtencionWhatsapp::class)
        ->call('select', '900002')
        ->call('close')
        ->assertNotified('Conversación cerrada')
        ->assertSet('selectedHandoffId', null)
        ->call('select', '900006')
        ->call('close')
        ->assertNotified('Esta conversación está en atención');

    expect(CrmHandoffEnvelope::query()->where('handoff_id', '900002')->value('closed_by'))->toBe(9)
        ->and(CrmInboxQueue::snapshot(['comercial'], true, 25)['count'])->toBe(1)
        ->and(CrmHandoffEnvelope::query()->where('handoff_id', '900006')->value('closed_at'))->toBeNull();
});

it('pasa un caso a un compañero o a otro equipo y no toca uno que ya está en atención', function (): void {
    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->string('status')->nullable();
        $table->json('departament')->nullable();
        $table->timestamps();
    });

    DB::table('users')->insert([
        ['id' => 3, 'name' => 'Ana Rojas', 'email' => 'ana@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => json_encode(['NEGOCIOS']), 'created_at' => now(), 'updated_at' => now()],
        ['id' => 4, 'name' => 'Laura Méndez', 'email' => 'laura@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => json_encode(['NEGOCIOS']), 'created_at' => now(), 'updated_at' => now()],
        ['id' => 5, 'name' => 'Roto', 'email' => 'roto@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => 'no-es-json', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 6, 'name' => 'Omar Operaciones', 'email' => 'omar@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => json_encode(['OPERACIONES']), 'created_at' => now(), 'updated_at' => now()],
    ]);

    config(['crm-inbox.queue_connection' => 'sync']);
    Queue::fake();
    Http::fake();

    crmInboxEnvelope('900002', 'comercial', '2026-10-06 16:00:00', 'María Peña', 'el precio me parece alto');
    crmInboxEnvelope('900006', 'comercial', '2026-10-06 17:00:00', 'Otra persona', 'sigo esperando');
    CrmHandoffEnvelope::query()->where('handoff_id', '900006')->update([
        'taken_at' => '2026-10-06 17:10:00',
        'taken_by' => 3,
    ]);

    $waiting = CrmHandoffEnvelope::query()->where('handoff_id', '900002')->first();
    $taken = CrmHandoffEnvelope::query()->where('handoff_id', '900006')->first();

    expect(CrmInboxColleagues::forArea('comercial', 3))->toBe([
        ['id' => 4, 'name' => 'Laura Méndez'],
    ]);

    expect(app(CrmHandoffAssign::class)->assign($waiting, 3, 3)['reason'])->toBe('self')
        ->and(app(CrmHandoffAssign::class)->assign($taken, 4, 3)['reason'])->toBe('taken')
        ->and(app(CrmHandoffMove::class)->move($waiting, 'comercial', 3)['reason'])->toBe('invalid')
        ->and(app(CrmHandoffMove::class)->move($taken, 'operaciones', 3)['reason'])->toBe('taken');

    $assigned = app(CrmHandoffAssign::class)->assign($waiting, 4, 3);
    app()->terminate();

    $row = collect(CrmInboxQueue::snapshot(['comercial'], true, 25)['rows'])->firstWhere('handoff_id', '900002');

    expect($assigned)->toMatchArray(['ok' => true, 'already' => false, 'name' => 'Laura Méndez'])
        ->and($row['assignee'])->toBe('Laura')
        ->and(CrmHandoffEnvelope::query()->where('handoff_id', '900002')->value('assigned_by'))->toBe(3);

    Queue::assertPushed(NotifyCrmHandoffJob::class, function (NotifyCrmHandoffJob $job): bool {
        return $job->handoffId === '900002' && $job->notice === 'assign' && $job->onlyUserId === 4;
    });

    $moved = app(CrmHandoffMove::class)->move($waiting->fresh(), 'operaciones', 3);
    app()->terminate();

    expect($moved)->toMatchArray(['ok' => true, 'already' => false, 'label' => 'Operaciones'])
        ->and(CrmHandoffEnvelope::query()->where('handoff_id', '900002')->value('area'))->toBe('operaciones')
        ->and(CrmHandoffEnvelope::query()->where('handoff_id', '900002')->value('assigned_to'))->toBeNull()
        ->and(CrmHandoffEnvelope::query()->where('handoff_id', '900002')->value('moved_by'))->toBe(3)
        ->and(collect(CrmInboxQueue::snapshot(['comercial'], false, 25)['rows'])->pluck('handoff_id')->all())->not->toContain('900002')
        ->and(collect(CrmInboxQueue::snapshot(['operaciones'], false, 25)['rows'])->pluck('handoff_id')->all())->toContain('900002');

    Queue::assertPushed(NotifyCrmHandoffJob::class, function (NotifyCrmHandoffJob $job): bool {
        return $job->handoffId === '900002' && $job->notice === 'move' && $job->onlyUserId === null;
    });

    Http::assertNothingSent();
});

it('la huella de la bandeja cambia solo cuando cambia lo que se ve', function (): void {
    Carbon::setTestNow('2026-10-06 19:00:00');
    crmInboxEnvelope('900002', 'comercial', '2026-10-06 16:00:00', 'María Peña', 'el precio me parece alto');

    $areas = ['comercial'];
    $first = CrmInboxQueue::fingerprint($areas, false, '900002');
    $queueOnly = CrmInboxQueue::fingerprint($areas, false, null);

    expect(CrmInboxQueue::fingerprint($areas, false, '900002'))->toBe($first)
        ->and($queueOnly)->not->toBe($first);

    crmInboxEnvelope('900004', 'operaciones', '2026-10-06 17:00:00', 'Luis Mora', 'necesito una cita');

    expect(CrmInboxQueue::fingerprint($areas, false, '900002'))->toBe($first);

    CrmHandoffEnvelope::query()->where('handoff_id', '900002')->update(['taken_at' => now(), 'taken_by' => 9]);
    $taken = CrmInboxQueue::fingerprint($areas, false, '900002');

    expect($taken)->not->toBe($first);

    $message = CrmHandoffMessage::query()->forceCreate([
        'message_id' => 'reply-1',
        'handoff_id' => '900002',
        'phone' => '584120000002',
        'body' => 'hola',
        'received_at' => now(),
        'direction' => 'out',
        'status' => 'pending',
    ]);
    $withMessage = CrmInboxQueue::fingerprint($areas, false, '900002');

    expect($withMessage)->not->toBe($taken)
        ->and(CrmInboxQueue::fingerprint($areas, false, null))->toBe(CrmInboxQueue::fingerprint($areas, false, null));

    $message->forceFill(['status' => 'sent'])->save();

    expect(CrmInboxQueue::fingerprint($areas, false, '900002'))->not->toBe($withMessage);

    Carbon::setTestNow();
});

it('el sondeo solo repinta cuando la huella cambia y la ficha se busca aparte', function (): void {
    Carbon::setTestNow('2026-10-06 19:00:00');
    crmInboxEnvelope('900002', 'comercial', '2026-10-06 16:00:00', 'María Peña', 'el precio me parece alto');

    $this->actingAs(User::factory()->make([
        'id' => 9,
        'name' => 'Ana Rojas',
        'email' => 'analista@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS'],
    ]));
    Filament::setCurrentPanel('business');

    $page = Livewire::test(AtencionWhatsapp::class)->assertSee('María Peña');
    $initial = $page->get('fingerprint');

    expect($initial)->not->toBeNull();

    $page->call('heartbeat')->assertSet('fingerprint', $initial);

    crmInboxEnvelope('900003', 'comercial', '2026-10-06 18:00:00', 'Pedro Gil', 'quiero cotizar');

    $page->call('heartbeat')->assertSee('Pedro Gil');

    expect($page->get('fingerprint'))->not->toBe($initial);

    $page->call('select', '900002')
        ->assertSet('directory', null)
        ->call('loadDirectory')
        ->assertSet('directory', ['status' => 'none'])
        ->call('select', '900003')
        ->assertSet('directory', null);

    Carbon::setTestNow();
});

it('arma el contexto en piezas, la espera y el recorrido del caso', function (): void {
    Carbon::setTestNow('2026-10-06 19:00:00');
    crmInboxEnvelope('900002', 'comercial', '2026-10-06 16:00:00', 'María Peña', 'el precio me parece alto');
    crmInboxEnvelope('900003', 'comercial', '2026-10-06 17:00:00', 'Pedro Gil', 'quiero cotizar');
    CrmHandoffEnvelope::query()->where('handoff_id', '900002')->update(['taken_at' => '2026-10-06 18:30:00', 'taken_by' => 4]);
    CrmHandoffEnvelope::query()->where('handoff_id', '900003')->update(['assigned_to' => 7, 'assigned_at' => '2026-10-06 17:05:00']);

    $case = CrmInboxQueue::find('900002', ['comercial'], false);
    $rows = collect(CrmInboxQueue::snapshot(['comercial'], false, 25)['rows'])->keyBy('handoff_id');

    expect($case['context'])->toBe([
        'motivo' => 'el precio',
        'necesidad' => 'plan familiar',
        'quote_control' => '0009018',
        'quote_total' => '186,00',
    ])
        ->and($case['waiting_since'])->toBe(Carbon::parse('2026-10-06 16:00:00')->getTimestamp())
        ->and($case['taken_by'])->toBe(4)
        ->and($case['assigned_to'])->toBeNull()
        ->and(array_column($case['timeline'], 'label'))->toBe(['SolIA lo pasó a Comercial', 'Tomado por un analista'])
        ->and($rows['900003']['assigned_to'])->toBe(7)
        ->and($rows['900003']['taken_by'])->toBeNull()
        ->and($rows['900002']['assigned_to'])->toBeNull();

    Carbon::setTestNow();
});

it('las respuestas rápidas llevan el nombre del cliente y omiten la propuesta si no hay', function (): void {
    config([
        'crm-inbox.quote_currency' => 'USD',
        'crm-inbox.quick_replies' => [
            ['label' => 'Pedir cédula', 'text' => '{cliente}, ¿me confirmas la cédula?'],
            ['label' => 'Revisar propuesta', 'text' => 'Tu propuesta {propuesta}.'],
            ['label' => '', 'text' => 'sin etiqueta'],
        ],
    ]);

    $withQuote = AtencionWhatsapp::quickReplies([
        'name' => 'María Peña',
        'context' => ['quote_control' => '0009018', 'quote_total' => '186,00'],
    ]);
    $withoutQuote = AtencionWhatsapp::quickReplies([
        'name' => 'Sin nombre',
        'context' => ['quote_control' => null, 'quote_total' => null],
    ]);

    expect($withQuote)->toBe([
        ['label' => 'Pedir cédula', 'text' => 'María, ¿me confirmas la cédula?'],
        ['label' => 'Revisar propuesta', 'text' => 'Tu propuesta 0009018 · USD 186,00.'],
    ])
        ->and($withoutQuote)->toBe([
            ['label' => 'Pedir cédula', 'text' => 'Hola, ¿me confirmas la cédula?'],
        ]);
});

function crmInboxEnvelope(string $handoffId, ?string $area, string $acceptedAt, string $name, string $lastLine): void
{
    CrmHandoffEnvelope::factory()->create([
        'handoff_id' => $handoffId,
        'phone' => '584120000002',
        'area' => $area,
        'motivo' => 'el precio',
        'payload' => [
            'name' => $name,
            'motivo' => 'el precio',
            'necesidad' => 'plan familiar',
            'ultimo_mensaje' => $lastLine,
            'cotizacion' => [
                'control' => '0009018',
                'total' => 186,
            ],
            'mensajes' => [
                ['direction' => 'out', 'text' => 'te preparo la propuesta', 'hora' => '18:00'],
                ['direction' => 'in', 'text' => $lastLine, 'hora' => '18:10'],
            ],
        ],
        'accepted_at' => $acceptedAt,
    ]);
}
