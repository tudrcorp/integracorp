<?php

declare(strict_types=1);

use App\Enums\CrmCaseOutcome;
use App\Filament\Business\Pages\AtencionWhatsapp;
use App\Filament\Business\Pages\BibliotecaAtencion;
use App\Filament\Business\Pages\SupervisionWhatsapp;
use App\Jobs\NotifyCrmHandoffJob;
use App\Models\CrmDocument;
use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
use App\Models\CrmQuickReply;
use App\Models\User;
use App\Support\CrmInbox\CrmHandoffFollowUp;
use App\Support\CrmInbox\CrmHandoffRelay;
use App\Support\CrmInbox\CrmHandoffReply;
use App\Support\CrmInbox\CrmInboxCopilot;
use App\Support\CrmInbox\CrmInboxDashboard;
use App\Support\CrmInbox\CrmInboxHealth;
use App\Support\CrmInbox\CrmInboxHistory;
use App\Support\CrmInbox\CrmInboxLibrary;
use App\Support\CrmInbox\CrmInboxPresence;
use App\Support\CrmInbox\CrmInboxPulse;
use App\Support\CrmInbox\CrmInboxQueue;
use App\Support\CrmInbox\CrmInboxWatch;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'session.driver' => 'array',
        'cache.default' => 'array',
        'cache.stores.crm-inbox' => ['driver' => 'array', 'serialize' => false],
        'crm-inbox.cache_store' => 'crm-inbox',
        'crm-inbox.queue_connection' => 'redis',
        'crm-inbox.takeover_url' => 'http://n8n.test/webhook/handoff/takeover',
        'crm-inbox.takeover_key' => 'local-key',
        'crm-inbox.reply_url' => '',
        'crm-inbox.document_url' => '',
        'crm-inbox.sla.warn_minutes' => 5,
        'crm-inbox.sla.late_minutes' => 15,
        'crm-inbox.sla.abandon_minutes' => 30,
        'crm-inbox.max_active_per_analyst' => 2,
        'queue.default' => 'sync',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');
    app('cache')->forgetDriver('crm-inbox');
    Cache::store('crm-inbox')->flush();

    if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
        throw new RuntimeException('El CRM debe probarse en sqlite en memoria.');
    }

    foreach (['crm_handoff_messages', 'crm_handoff_envelopes', 'users'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email');
        $table->string('status')->nullable();
        $table->json('departament')->nullable();
        $table->timestamps();
    });

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

    crmInboxUpgradeTestSchema();

    foreach ([[9, 'Ana Rojas'], [10, 'Luis Pérez'], [11, 'Marta Díaz']] as [$id, $name]) {
        DB::table('users')->insert([
            'id' => $id,
            'name' => $name,
            'email' => 'u'.$id.'@tudrencasa.com',
            'status' => 'ACTIVO',
            'departament' => json_encode(['NEGOCIOS']),
        ]);
    }

    Storage::fake('local');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('el pulso sube con cada escritura del CRM y el sondeo no repinta si no cambió', function (): void {
    $before = CrmInboxPulse::current();
    volumeEnvelope('800001');
    $after = CrmInboxPulse::current();

    expect($before)->not->toBeNull()
        ->and((int) $after)->toBeGreaterThan((int) $before);

    volumeAnalyst();

    $page = Livewire::test(AtencionWhatsapp::class);
    $page->call('heartbeat')->assertSet('pulse', $after);

    CrmHandoffEnvelope::query()->where('handoff_id', '800001')->update(['area' => 'comercial']);
    expect(CrmInboxPulse::current())->toBe($after);

    CrmHandoffEnvelope::query()->where('handoff_id', '800001')->firstOrFail()->forceFill(['motivo' => 'otro'])->save();
    expect(CrmInboxPulse::current())->not->toBe($after);
});

it('tres fallos seguidos de WhatsApp encienden el aviso general y el primer envío lo apaga', function (): void {
    CrmInboxHealth::failed();
    CrmInboxHealth::failed();
    expect(CrmInboxHealth::status()['down'])->toBeFalse();

    CrmInboxHealth::failed();
    expect(CrmInboxHealth::status())->toMatchArray(['down' => true])
        ->and(CrmInboxHealth::status()['since'])->toBeInt();

    CrmInboxHealth::delivered();
    expect(CrmInboxHealth::status())->toBe(['down' => false, 'since' => null]);
});

it('reintenta solo lo reciente, hasta el tope de intentos, y marca la primera respuesta', function (): void {
    Carbon::setTestNow('2026-10-14 12:00:00');
    volumeEnvelope('800002', ['taken_at' => now()->subMinutes(20), 'taken_by' => 9]);
    volumeMessage('800002', 'reciente', 'out', 'message', 'failed', now()->subMinutes(3));
    volumeMessage('800002', 'viejo', 'out', 'message', 'failed', now()->subMinutes(40));
    volumeMessage('800002', 'agotado', 'out', 'message', 'failed', now()->subMinutes(2), 3);

    Http::fake(['http://n8n.test/*' => Http::response(['ok' => true, 'message_id' => 'wamid.R'], 200)]);

    expect(CrmInboxWatch::retryFailed())->toBe(1);

    $status = CrmHandoffMessage::query()->pluck('status', 'body')->all();

    expect($status)->toBe(['reciente' => 'sent', 'viejo' => 'failed', 'agotado' => 'failed'])
        ->and(CrmHandoffMessage::query()->where('body', 'reciente')->value('attempts'))->toBe(1)
        ->and(CrmHandoffEnvelope::query()->where('handoff_id', '800002')->first()->first_reply_at)->not->toBeNull();
});

it('avisa una sola vez por espera: sin tomar al área, sin respuesta al analista y abandono al supervisor', function (): void {
    Bus::fake([NotifyCrmHandoffJob::class]);
    Carbon::setTestNow('2026-10-14 12:00:00');

    volumeEnvelope('800003', ['accepted_at' => now()->subMinutes(20)]);
    volumeEnvelope('800004', ['taken_at' => now()->subHour(), 'taken_by' => 9, 'last_customer_at' => now()->subMinutes(40)]);
    volumeEnvelope('800005', ['taken_at' => now()->subHour(), 'taken_by' => 10, 'last_customer_at' => now()->subMinutes(40)]);
    volumeMessage('800005', 'ya le respondí', 'out', 'message', 'sent', now()->subMinutes(35));

    expect(CrmInboxWatch::alertLate())->toBe(1)
        ->and(CrmInboxWatch::alertLate())->toBe(0)
        ->and(CrmInboxWatch::alertUnanswered())->toBe(['late' => 1, 'stale' => 1])
        ->and(CrmInboxWatch::alertUnanswered())->toBe(['late' => 0, 'stale' => 0]);

    Bus::assertDispatched(NotifyCrmHandoffJob::class, fn (NotifyCrmHandoffJob $job): bool => $job->handoffId === '800003' && $job->notice === 'late');
    Bus::assertDispatched(NotifyCrmHandoffJob::class, fn (NotifyCrmHandoffJob $job): bool => $job->handoffId === '800004' && $job->notice === 'late_mine' && $job->onlyUserId === 9);
    Bus::assertDispatched(NotifyCrmHandoffJob::class, fn (NotifyCrmHandoffJob $job): bool => $job->handoffId === '800004' && $job->notice === 'stale');
    Bus::assertNotDispatched(NotifyCrmHandoffJob::class, fn (NotifyCrmHandoffJob $job): bool => $job->handoffId === '800005');

    Carbon::setTestNow('2026-10-14 12:30:00');
    CrmHandoffEnvelope::query()->where('handoff_id', '800003')->firstOrFail()->forceFill(['last_customer_at' => now()->subMinutes(20)])->save();
    expect(CrmInboxWatch::alertLate())->toBe(1);
});

it('el recordatorio vence, sube el caso a «Te toca responder», avisa y se borra al responder', function (): void {
    Bus::fake([NotifyCrmHandoffJob::class]);
    Carbon::setTestNow('2026-10-14 12:00:00');
    volumeEnvelope('800006', ['taken_at' => now()->subHour(), 'taken_by' => 9, 'last_customer_at' => now()->subHour()]);
    volumeMessage('800006', 'te escribo luego', 'out', 'message', 'sent', now()->subMinutes(50));
    $envelope = CrmHandoffEnvelope::query()->where('handoff_id', '800006')->firstOrFail();

    expect(app(CrmHandoffFollowUp::class)->set($envelope, 10, '1h', ''))->toBe(['ok' => false, 'reason' => 'not_mine'])
        ->and(app(CrmHandoffFollowUp::class)->set($envelope, 9, '2026-01-01T09:00', ''))->toBe(['ok' => false, 'reason' => 'invalid'])
        ->and(app(CrmHandoffFollowUp::class)->set($envelope, 9, '1h', 'confirmar pago')['ok'])->toBeTrue();

    $lane = fn (): string => CrmInboxQueue::laneOf(collect(CrmInboxQueue::snapshot(['comercial'], false, 25)['rows'])->firstWhere('handoff_id', '800006'), 9);
    expect($lane())->toBe('waiting');

    Carbon::setTestNow('2026-10-14 13:01:00');
    expect($lane())->toBe('reply')
        ->and(CrmInboxWatch::dueFollowUps())->toBe(1)
        ->and(CrmInboxWatch::dueFollowUps())->toBe(0);

    Bus::assertDispatched(NotifyCrmHandoffJob::class, fn (NotifyCrmHandoffJob $job): bool => $job->notice === 'follow_up' && $job->onlyUserId === 9);

    Http::fake(['http://n8n.test/*' => Http::response(['ok' => true, 'message_id' => 'wamid.F'], 200)]);
    $reply = app(CrmHandoffReply::class);
    $id = '11111111-1111-4111-8111-111111111111';
    $reply->stage($envelope->refresh(), 9, $id, 'Hola, ¿pudiste pagar?');
    $reply->deliver($envelope, $id);

    expect($envelope->refresh()->follow_up_at)->toBeNull()
        ->and($lane())->toBe('waiting');
});

it('pide el motivo al cerrar o devolver y lo guarda', function (): void {
    volumeEnvelope('800007');
    volumeAnalyst();

    Livewire::test(AtencionWhatsapp::class)
        ->call('select', '800007')
        ->call('close', 'no-existe')
        ->assertNotified('Elige cómo terminó el caso')
        ->call('close', 'otro', '   ')
        ->assertNotified('Cuenta en una línea qué pasó')
        ->call('close', 'otro', 'Era un proveedor ofreciendo servicios')
        ->assertNotified('Conversación cerrada');

    $envelope = CrmHandoffEnvelope::query()->where('handoff_id', '800007')->firstOrFail();

    expect($envelope->outcome)->toBe(CrmCaseOutcome::Other->value)
        ->and($envelope->outcome_note)->toBe('Era un proveedor ofreciendo servicios')
        ->and($envelope->outcome_by)->toBe(9)
        ->and($envelope->outcome_at)->not->toBeNull();
});

it('respeta el tope de casos y «Tomar el siguiente» da primero lo marcado para ti y respeta lo de un compañero presente', function (): void {
    Carbon::setTestNow('2026-10-14 12:00:00');
    volumeAnalyst();
    CrmInboxPresence::set(10, CrmInboxPresence::AVAILABLE);

    volumeEnvelope('800010', ['accepted_at' => now()->subMinutes(50), 'assigned_to' => 10]);
    volumeEnvelope('800011', ['accepted_at' => now()->subMinutes(40)]);
    volumeEnvelope('800012', ['accepted_at' => now()->subMinutes(10), 'assigned_to' => 9]);

    $takeovers = 0;
    Http::fake(function ($request) use (&$takeovers) {
        if (str_ends_with($request->url(), '/takeover')) {
            $takeovers++;
        }

        return Http::response(['ok' => true, 'message_id' => 'wamid.'.$takeovers], 200);
    });

    $page = Livewire::test(AtencionWhatsapp::class)
        ->call('takeNext')
        ->assertNotified('Caso tomado')
        ->assertSet('selectedHandoffId', '800012')
        ->call('takeNext')
        ->assertSet('selectedHandoffId', '800011');

    expect(CrmHandoffEnvelope::query()->whereNotNull('taken_at')->pluck('handoff_id')->sort()->values()->all())->toBe(['800011', '800012']);

    $page->call('takeNext')->assertNotified('Tienes 2 casos en atención');

    expect($takeovers)->toBe(2)
        ->and(CrmHandoffEnvelope::query()->where('handoff_id', '800010')->value('taken_at'))->toBeNull();
});

it('un compañero solo releva un caso abandonado; el supervisor lo pasa a quien quiera', function (): void {
    Carbon::setTestNow('2026-10-14 12:00:00');
    Bus::fake([NotifyCrmHandoffJob::class]);
    Http::fake(['http://n8n.test/*' => Http::response(['ok' => true, 'message_id' => 'wamid.X'], 200)]);
    CrmInboxPresence::set(10, CrmInboxPresence::AVAILABLE);
    volumeEnvelope('800020', ['taken_at' => now()->subHour(), 'taken_by' => 10, 'last_customer_at' => now()->subMinutes(10)]);
    $envelope = CrmHandoffEnvelope::query()->where('handoff_id', '800020')->firstOrFail();
    $relay = app(CrmHandoffRelay::class);

    expect($relay->relay($envelope, 9, 9, false))->toBe(['ok' => false, 'reason' => 'forbidden']);

    Carbon::setTestNow('2026-10-14 12:25:00');
    expect(CrmHandoffRelay::isStale($envelope))->toBeTrue()
        ->and($relay->relay($envelope, 9, 9, false))->toBe(['ok' => true, 'name' => 'Ana Rojas'])
        ->and($envelope->refresh()->taken_by)->toBe(9)
        ->and(CrmHandoffMessage::query()->where('kind', 'note')->value('body'))->toBe('Relevo: Ana Rojas sigue con el caso (lo atendía Luis Pérez).')
        ->and(CrmHandoffMessage::query()->where('kind', 'message')->value('body'))->toContain('desde ahora te atiende Ana')
        ->and($relay->relay($envelope, 11, 10, true))->toBe(['ok' => true, 'name' => 'Marta Díaz'])
        ->and($envelope->refresh()->taken_by)->toBe(11);

    Bus::assertDispatched(NotifyCrmHandoffJob::class, fn (NotifyCrmHandoffJob $job): bool => $job->notice === 'relay' && $job->onlyUserId === 11);
});

it('un compañero puede relevar a quien está ausente aunque el cliente no espere tanto', function (): void {
    Carbon::setTestNow('2026-10-14 12:00:00');
    Http::fake(['http://n8n.test/*' => Http::response(['ok' => true, 'message_id' => 'wamid.Y'], 200)]);
    CrmInboxPresence::set(10, CrmInboxPresence::AWAY);
    volumeEnvelope('800021', ['taken_at' => now()->subHour(), 'taken_by' => 10, 'last_customer_at' => now()->subMinutes(2)]);

    expect(app(CrmHandoffRelay::class)->relay(CrmHandoffEnvelope::query()->where('handoff_id', '800021')->firstOrFail(), 9, 9, false)['ok'])->toBeTrue();
});

it('envía un documento aprobado del área y no uno de otra área ni uno sin PDF válido', function (): void {
    volumeEnvelope('800030', ['taken_at' => now(), 'taken_by' => 9]);
    $envelope = CrmHandoffEnvelope::query()->where('handoff_id', '800030')->firstOrFail();
    Storage::disk('local')->put('crm-documents/beneficios.pdf', "%PDF-1.4\n%beneficios\n");
    Storage::disk('local')->put('crm-documents/falso.pdf', 'no soy un pdf');

    $ok = CrmDocument::query()->create(['title' => 'Cuadro de beneficios', 'path' => 'crm-documents/beneficios.pdf', 'filename' => 'Beneficios TDEC.pdf', 'size' => 20]);
    $other = CrmDocument::query()->create(['title' => 'Viajes', 'area' => 'viajes', 'path' => 'crm-documents/beneficios.pdf', 'filename' => 'v.pdf', 'size' => 20]);
    $fake = CrmDocument::query()->create(['title' => 'Falso', 'path' => 'crm-documents/falso.pdf', 'filename' => 'f.pdf', 'size' => 20]);
    $escape = CrmDocument::query()->create(['title' => 'Escape', 'path' => 'crm-documents/../.env', 'filename' => 'e.pdf', 'size' => 20]);

    Http::fake(['http://n8n.test/*' => Http::response(['ok' => true, 'message_id' => 'wamid.D'], 200)]);
    $reply = app(CrmHandoffReply::class);

    foreach ([$other, $fake, $escape] as $index => $document) {
        expect($reply->stageDocument($envelope, 9, sprintf('22222222-2222-4222-8222-00000000000%d', $index), $document->id))->toBe(['ok' => false, 'reason' => 'missing']);
    }

    $id = '33333333-3333-4333-8333-333333333333';
    expect($reply->stageDocument($envelope, 9, $id, $ok->id))->toBe(['ok' => true, 'already' => false])
        ->and($reply->deliver($envelope, $id)['ok'])->toBeTrue()
        ->and($ok->refresh()->uses)->toBe(1)
        ->and(CrmHandoffMessage::query()->where('message_id', $id)->value('status'))->toBe('sent')
        ->and(Storage::disk('local')->exists('crm-documents/beneficios.pdf'))->toBeTrue();

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/handoff/document')
        && $request['filename'] === 'Beneficios-TDEC.pdf'
        && $request['caption'] === 'Cuadro de beneficios'
        && base64_decode((string) $request['pdf_base64']) === "%PDF-1.4\n%beneficios\n");
});

it('las respuestas rápidas salen de la biblioteca con variables y, si no hay, de configuración', function (): void {
    config(['crm-inbox.quick_replies' => [['label' => 'Respaldo', 'text' => 'Hola {cliente}']]]);
    $values = ['cliente' => 'María', 'analista' => 'Ana', 'propuesta' => ''];

    expect(CrmInboxLibrary::quickReplies('comercial', $values))->toBe([['id' => null, 'label' => 'Respaldo', 'text' => 'Hola María']]);

    $reply = CrmQuickReply::query()->create(['label' => 'Saludo', 'body' => 'Hola {cliente}, soy {analista}.', 'sort' => 1]);
    CrmQuickReply::query()->create(['label' => 'Propuesta', 'body' => 'Tu propuesta {propuesta}', 'sort' => 2]);
    CrmQuickReply::query()->create(['label' => 'Viajes', 'body' => 'Solo viajes', 'area' => 'viajes']);
    CrmQuickReply::query()->create(['label' => 'Apagada', 'body' => 'No sale', 'active' => false]);

    expect(CrmInboxLibrary::quickReplies('comercial', $values))->toBe([['id' => $reply->id, 'label' => 'Saludo', 'text' => 'Hola María, soy Ana.']])
        ->and(array_column(CrmInboxLibrary::quickReplies('comercial', [...$values, 'propuesta' => '0009 · USD 10']), 'text'))->toBe(['Hola María, soy Ana.', 'Tu propuesta 0009 · USD 10']);

    CrmInboxLibrary::countReply($reply->id);
    expect($reply->refresh()->uses)->toBe(1);
});

it('lee fecha de nacimiento y correo del cliente y arma el borrador según el punto del caso', function (): void {
    $case = [
        'name' => 'María Peña',
        'taken' => true,
        'taken_by' => 9,
        'attention' => [
            ['id' => 'a', 'role' => 'in', 'text' => 'nací el 31/02/1990, perdón, el 14/05/1990', 'time' => '10:00', 'document' => false, 'status' => 'received'],
            ['id' => 'b', 'role' => 'in', 'text' => 'mi correo es Maria.Pena@Mail.com.', 'time' => '10:01', 'document' => false, 'status' => 'received'],
        ],
        'messages' => [],
    ];
    $envelope = new CrmHandoffEnvelope(['handoff_id' => '1', 'phone' => '584120000002', 'payload' => ['name' => 'María Peña']]);
    $brief = CrmInboxCopilot::brief($envelope, $case, 9, false, 300, now());
    $enroll = collect($brief['enroll'])->keyBy('key');

    expect($enroll['birth']['value'])->toBe('14/05/1990')
        ->and($enroll['email']['value'])->toBe('maria.pena@mail.com')
        ->and($enroll['document']['value'])->toBeNull()
        ->and($brief['draft'])->toBe('María, para prepararte la propuesta necesito las edades de las personas que quieres incluir y el plan que te interesa (Inicial, Ideal o Especial).')
        ->and(CrmInboxCopilot::draft($case, 'María', true, true, $brief['enroll']))->toBe('Gracias, María. Ya tengo lo necesario; en un momento te envío la propuesta.');

    $case['attention'][] = ['id' => 'c', 'role' => 'out', 'text' => 'Propuesta 1', 'time' => '10:05', 'document' => true, 'status' => 'sent'];

    expect(CrmInboxCopilot::draft($case, 'María', true, true, $brief['enroll']))->toBe('María, si quieres avanzar con la afiliación, envíame cédula del titular.')
        ->and(CrmInboxCopilot::brief($envelope, [...$case, 'taken_by' => 10], 9, false, 300, now())['draft'])->toBeNull();
});

it('muestra las conversaciones anteriores del mismo teléfono y solo abre hilos de ese teléfono', function (): void {
    volumeEnvelope('800040', ['closed_at' => now()->subDay(), 'outcome' => 'precio', 'outcome_note' => 'Busca algo más barato', 'taken_by' => 9]);
    volumeEnvelope('800041');
    volumeEnvelope('800042', ['phone' => '584129999999']);
    volumeMessage('800040', 'cuánto cuesta', 'in', 'message', 'received', now()->subDay());

    $history = CrmInboxHistory::forPhone('584120000002', '800041');

    expect($history)->toHaveCount(1)
        ->and($history[0])->toMatchArray(['handoff_id' => '800040', 'outcome' => 'Le pareció caro', 'note' => 'Busca algo más barato', 'by' => 'Ana', 'open' => false])
        ->and(CrmInboxHistory::thread('584120000002', '800040'))->toHaveCount(1)
        ->and(CrmInboxHistory::thread('584120000002', '800042'))->toBeNull();
});

it('el tablero cuenta entradas, primera respuesta, demoras, motivos y carga por analista', function (): void {
    Carbon::setTestNow('2026-10-14 16:00:00');
    CrmInboxPresence::set(9, CrmInboxPresence::AVAILABLE);
    volumeEnvelope('800050', ['accepted_at' => now()->subMinutes(30)]);
    volumeEnvelope('800051', ['accepted_at' => now()->subMinutes(60), 'taken_at' => now()->subMinutes(55), 'taken_by' => 9, 'first_reply_at' => now()->subMinutes(53), 'last_customer_at' => now()->subMinutes(20)]);
    volumeEnvelope('800052', ['accepted_at' => now()->subMinutes(90), 'taken_at' => now()->subMinutes(85), 'taken_by' => 9, 'first_reply_at' => now()->subMinutes(81), 'released_at' => now()->subMinutes(10), 'outcome' => 'vendido', 'outcome_by' => 9, 'outcome_at' => now()->subMinutes(10)]);

    $data = CrmInboxDashboard::build('business', ['comercial'], true, 'hoy', now());

    expect($data['kpis'])->toMatchArray([
        'incoming' => 3,
        'attended' => 2,
        'waiting_now' => 1,
        'late_now' => 1,
        'unanswered_now' => 1,
        'closed' => 1,
        'won' => 1,
        'conversion' => 100.0,
    ])
        ->and($data['kpis']['first_reply_median'])->toBe(480)
        ->and($data['outcomes'])->toBe([['key' => 'vendido', 'label' => 'Vendido o afiliado', 'total' => 1]])
        ->and(collect($data['analysts'])->firstWhere('id', 9))->toMatchArray(['active' => 1, 'waiting' => 1, 'attended' => 2, 'closed' => 1, 'won' => 1, 'reply_median' => 180, 'state' => 'available'])
        ->and(array_column($data['risk'], 'handoff_id'))->toBe(['800050', '800051'])
        ->and(CrmInboxDashboard::duration(480))->toBe('8 min');
});

it('el tablero y la biblioteca son solo para supervisores', function (): void {
    volumeAnalyst();

    expect(SupervisionWhatsapp::canAccess())->toBeFalse()
        ->and(BibliotecaAtencion::canAccess())->toBeFalse();

    volumeAnalyst(['NEGOCIOS', 'SUPERADMIN']);

    expect(SupervisionWhatsapp::canAccess())->toBeTrue()
        ->and(BibliotecaAtencion::canAccess())->toBeTrue();

    Livewire::test(SupervisionWhatsapp::class)
        ->assertSee('Casos que entraron')
        ->assertSee('En riesgo ahora')
        ->call('setRange', '7d')
        ->assertSet('range', '7d')
        ->call('setRange', 'otro')
        ->assertSet('range', 'hoy');

    Livewire::test(BibliotecaAtencion::class)
        ->assertSee('Respuestas rápidas')
        ->mountAction(TestAction::make('createReply')->table())
        ->callMountedAction()
        ->assertHasActionErrors(['label' => 'required', 'body' => 'required'])
        ->set('mountedActions.0.data.label', 'Saludo')
        ->set('mountedActions.0.data.body', 'Hola {cliente}')
        ->set('mountedActions.0.data.area', 'corporativo')
        ->set('mountedActions.0.data.sort', 3)
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(CrmQuickReply::query()->count())->toBe(1)
        ->and(CrmQuickReply::query()->first())->toMatchArray(['label' => 'Saludo', 'area' => 'corporativo', 'sort' => 3, 'created_by' => 9]);
});

it('la bandeja muestra presencia, siguiente caso, tope, aviso de caída, ficha anterior y motivo de cierre', function (): void {
    Carbon::setTestNow('2026-10-14 12:00:00');
    volumeAnalyst();
    volumeEnvelope('800060', ['taken_at' => now()->subMinutes(5), 'taken_by' => 9, 'last_customer_at' => now()->subMinutes(5)]);
    volumeEnvelope('800061', ['closed_at' => now()->subDay(), 'outcome' => 'ya_tiene']);
    CrmInboxHealth::failed();
    CrmInboxHealth::failed();
    CrmInboxHealth::failed();

    Livewire::test(AtencionWhatsapp::class)
        ->assertSee('Disponible')
        ->assertSee('Tomar el siguiente')
        ->assertSee('1/2 en atención')
        ->assertSee('WhatsApp no responde')
        ->call('select', '800060')
        ->call('loadDirectory')
        ->assertSee('Conversaciones anteriores')
        ->assertSee('Ya tiene un plan')
        ->assertSee('Para afiliar')
        ->assertSee('Borrador')
        ->assertSee('¿Cómo terminó?')
        ->call('setPresence', 'away')
        ->assertNotified('Ahora estás: Ausente')
        ->call('setFollowUp', 'tomorrow', 'llamar')
        ->assertNotified('Te lo recuerdo mañana 09:00');
});

it('borra los PDF de propuestas que nunca salieron y conserva los fallidos recientes', function (): void {
    Carbon::setTestNow('2026-10-14 12:00:00');
    $disk = Storage::disk('local');
    $old = '44444444-4444-4444-8444-444444444444';
    $failed = '55555555-5555-4555-8555-555555555555';
    $fresh = '66666666-6666-4666-8666-666666666666';

    foreach ([$old, $failed, $fresh] as $id) {
        $disk->put('crm-quotes/'.$id.'.pdf', '%PDF');
    }

    touch($disk->path('crm-quotes/'.$old.'.pdf'), now()->subHours(30)->getTimestamp());
    touch($disk->path('crm-quotes/'.$failed.'.pdf'), now()->subHours(30)->getTimestamp());
    volumeEnvelope('800070');
    volumeMessage('800070', 'Propuesta', 'out', 'quote', 'failed', now()->subHours(30), 3, $failed);

    expect(CrmInboxWatch::pruneDrafts())->toBe(1)
        ->and($disk->exists('crm-quotes/'.$old.'.pdf'))->toBeFalse()
        ->and($disk->exists('crm-quotes/'.$failed.'.pdf'))->toBeTrue()
        ->and($disk->exists('crm-quotes/'.$fresh.'.pdf'))->toBeTrue();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function volumeEnvelope(string $handoffId, array $attributes = []): void
{
    CrmHandoffEnvelope::factory()->create([
        'handoff_id' => $handoffId,
        'phone' => '584120000002',
        'area' => 'comercial',
        'payload' => ['name' => 'María Peña', 'mensajes' => []],
        'accepted_at' => now()->subMinutes(5),
        ...$attributes,
    ]);
}

function volumeMessage(string $handoffId, string $body, string $direction, string $kind, string $status, Carbon $at, int $attempts = 0, ?string $messageId = null): void
{
    CrmHandoffMessage::query()->create([
        'message_id' => $messageId ?? (string) Illuminate\Support\Str::uuid(),
        'handoff_id' => $handoffId,
        'phone' => '584120000002',
        'body' => $body,
        'received_at' => $at,
        'direction' => $direction,
        'kind' => $kind,
        'status' => $status,
        'user_id' => $direction === 'out' ? 9 : null,
        'attempts' => $attempts,
    ]);
}

/**
 * @param  list<string>  $departments
 */
function volumeAnalyst(array $departments = ['NEGOCIOS']): void
{
    test()->actingAs(User::factory()->make([
        'id' => 9,
        'name' => 'Ana Rojas',
        'email' => 'u9@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => $departments,
    ]));

    Filament::setCurrentPanel('business');
}
