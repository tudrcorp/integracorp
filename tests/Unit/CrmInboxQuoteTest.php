<?php

declare(strict_types=1);

use App\Filament\Business\Pages\AtencionWhatsapp;
use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
use App\Models\User;
use App\Support\CrmInbox\CrmInboxQuote;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
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
        'queue.default' => 'sync',
        'services.tudr_quote.url' => 'http://quote.test',
        'services.tudr_quote.key' => 'quote-key',
        'services.tudr_quote.enabled' => true,
        'crm-inbox.takeover_url' => 'http://n8n.test/webhook/handoff/takeover',
        'crm-inbox.takeover_key' => 'local-key',
        'crm-inbox.reply_url' => '',
        'crm-inbox.document_url' => '',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');

    if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
        throw new RuntimeException('La cotización del CRM debe probarse en sqlite en memoria.');
    }

    Schema::dropIfExists('crm_handoff_messages');
    Schema::dropIfExists('crm_handoff_envelopes');

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

    Storage::fake('local');

    crmInboxUpgradeTestSchema();
});

it('no cotiza sin el caso tomado ni con edades inválidas', function (): void {
    quoteInboxEnvelope();
    quoteInboxAnalyst();

    Http::fake([
        'http://quote.test/*' => Http::response(['ok' => false], 500),
        'http://n8n.test/*' => Http::response(['ok' => true, 'message_id' => 'wamid.INTRO'], 200),
    ]);

    Livewire::test(AtencionWhatsapp::class)
        ->call('select', '900002')
        ->set('quoteHolder', 'María Peña')
        ->set('quoteAges', '25, 40')
        ->call('previewQuote')
        ->assertNotified('No se pudo cotizar')
        ->call('take')
        ->set('quoteAges', '25, 100')
        ->call('previewQuote')
        ->assertNotified('No se pudo cotizar')
        ->assertSet('quoteDraft', null);

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/api/cotizar'));
});

it('muestra el motivo cuando no hay tarifa', function (): void {
    quoteInboxEnvelope();
    quoteInboxAnalyst();

    Http::fake(function ($request) {
        if (str_contains($request->url(), '/api/cotizar')) {
            return Http::response([
                'ok' => false,
                'error' => 'sin tarifa',
                'faltantes' => [['plan' => 'especial', 'motivo' => 'edad 80 sin tarifa']],
            ], 422);
        }

        return Http::response(['ok' => true, 'message_id' => 'wamid.INTRO'], 200);
    });

    Livewire::test(AtencionWhatsapp::class)
        ->call('select', '900002')
        ->call('take')
        ->set('quoteHolder', 'María Peña')
        ->set('quoteAges', '80')
        ->set('quotePlan', 'especial')
        ->set('quoteCoverages', ['5000'])
        ->call('previewQuote')
        ->assertSet('quoteDraft', null);

    expect(collect(session('filament.notifications') ?? [])->contains(
        fn (array $notification): bool => ($notification['title'] ?? null) === 'No se pudo cotizar'
            && ($notification['body'] ?? null) === 'ESPECIAL: edad 80 sin tarifa',
    ))->toBeTrue();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/api/cotizar'));
});

it('explica cuando el cotizador rechaza la clave', function (): void {
    quoteInboxEnvelope();
    quoteInboxAnalyst();

    Http::fake([
        'http://quote.test/*' => Http::response(['ok' => false, 'error' => 'no autorizado'], 401),
        'http://n8n.test/*' => Http::response(['ok' => true, 'message_id' => 'wamid.INTRO'], 200),
    ]);

    Livewire::test(AtencionWhatsapp::class)
        ->call('select', '900002')
        ->call('take')
        ->set('quoteHolder', 'María Peña')
        ->set('quoteAges', '23, 56, 67')
        ->set('quotePlan', 'ideal')
        ->set('quoteCoverages', ['10000'])
        ->call('previewQuote')
        ->assertSet('quoteDraft', null);

    expect(collect(session('filament.notifications') ?? [])->contains(
        fn (array $notification): bool => ($notification['body'] ?? null) === 'El cotizador rechazó la clave. Avísale a sistemas.',
    ))->toBeTrue();
});

it('guarda el PDF oficial y lo envía por el mismo WhatsApp', function (): void {
    quoteInboxEnvelope();
    quoteInboxAnalyst();

    $pdf = "%PDF-1.4\n%propuesta\n";
    $documentCalls = 0;

    Http::fake(function ($request) use (&$documentCalls, $pdf) {
        if (str_contains($request->url(), '/api/cotizar')) {
            return Http::response([
                'ok' => true,
                'control' => '0009100',
                'planes' => [[
                    'plan' => 'inicial',
                    'total_anual' => 320,
                ]],
                'pdf_base64' => base64_encode($pdf),
            ], 200);
        }

        if (str_ends_with($request->url(), '/handoff/document')) {
            $documentCalls++;

            return Http::response(['ok' => true, 'message_id' => 'wamid.1'], 200);
        }

        return Http::response(['ok' => true, 'message_id' => 'wamid.INTRO'], 200);
    });

    $page = Livewire::test(AtencionWhatsapp::class)
        ->call('select', '900002')
        ->call('take')
        ->call('openQuote')
        ->assertSet('quoteOpen', true)
        ->assertSet('quoteHolder', 'María Peña')
        ->set('quoteAges', '25, 40')
        ->set('quotePlan', 'inicial')
        ->call('previewQuote')
        ->assertSet('quoteDraft.control', '0009100')
        ->assertSet('quoteDraft.total', '320')
        ->assertSet('quoteDraft.plan', 'Inicial')
        ->assertSet('quoteDraft.people', 2)
        ->assertSet('quoteDraft.caption', 'Propuesta 0009100 · USD 320 al año');

    $draftId = $page->get('quoteDraft')['id'];

    expect(Storage::disk('local')->get(CrmInboxQuote::path($draftId)))->toBe($pdf)
        ->and(fn () => $page->set('quoteDraft.caption', 'otro texto'))
        ->toThrow(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

    $page->call('sendQuote')
        ->assertNotified('Propuesta enviada')
        ->assertSet('quoteDraft', null)
        ->assertSee('PDF')
        ->assertSee('Propuesta 0009100 · USD 320 al año')
        ->assertSee('Personas')
        ->assertSee('25, 40 años')
        ->assertSee('Ajustar y reenviar');

    expect(CrmHandoffMessage::query()->where('message_id', $draftId)->first()->meta)->toBe([
        'control' => '0009100',
        'total' => '320',
        'plan' => 'Inicial',
        'people' => 2,
        'ages' => '25, 40',
        'coverage' => null,
        'coverages' => [],
    ]);

    expect($documentCalls)->toBe(1)
        ->and(CrmHandoffMessage::query()->where('message_id', $draftId)->value('kind'))->toBe('quote')
        ->and(CrmHandoffMessage::query()->where('message_id', $draftId)->value('status'))->toBe('sent')
        ->and(CrmHandoffMessage::query()->where('message_id', $draftId)->value('provider_message_id'))->toBe('wamid.1')
        ->and(Storage::disk('local')->exists(CrmInboxQuote::path($draftId)))->toBeFalse();

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), '/api/cotizar')) {
            return false;
        }

        return ($request['planes'] ?? null) === ['inicial']
            && ! array_key_exists('cobertura', $request->data())
            && ($request['edades'] ?? null) === [25, 40]
            && ($request['formato'] ?? null) === 'json'
            && ($request['agente'] ?? null) === 'Ana Rojas'
            && ($request['titular'] ?? null) === 'María Peña';
    });

    Http::assertSent(function ($request) use ($pdf, $draftId): bool {
        if (! str_ends_with($request->url(), '/handoff/document')) {
            return false;
        }

        $decoded = base64_decode((string) ($request['pdf_base64'] ?? ''), true);

        return $request['reply_id'] === $draftId
            && $request['filename'] === 'Propuesta-0009100.pdf'
            && $request['caption'] === 'Propuesta 0009100 · USD 320 al año'
            && $request['phone'] === '584120000002'
            && $request->hasHeader('X-Handoff-Key')
            && $decoded === $pdf;
    });
});

it('conserva el PDF si WhatsApp falla y el reintento lo envía', function (): void {
    quoteInboxEnvelope();
    quoteInboxAnalyst();

    $pdf = "%PDF-1.4\n%reintento\n";
    $documentCalls = 0;

    Http::fake(function ($request) use (&$documentCalls, $pdf) {
        if (str_contains($request->url(), '/api/cotizar')) {
            return Http::response([
                'ok' => true,
                'control' => '0009100',
                'planes' => [['plan' => 'inicial', 'total_anual' => 320]],
                'pdf_base64' => base64_encode($pdf),
            ], 200);
        }

        if (str_ends_with($request->url(), '/handoff/document')) {
            $documentCalls++;

            if ($documentCalls === 1) {
                return Http::response(['ok' => false], 502);
            }

            return Http::response(['ok' => true, 'message_id' => 'wamid.1'], 200);
        }

        return Http::response(['ok' => true, 'message_id' => 'wamid.INTRO'], 200);
    });

    $page = Livewire::test(AtencionWhatsapp::class)
        ->call('select', '900002')
        ->call('take')
        ->set('quoteHolder', 'María Peña')
        ->set('quoteAges', '25')
        ->call('previewQuote');

    $draftId = $page->get('quoteDraft')['id'];

    $page->call('sendQuote')
        ->assertNotified('No se pudo enviar por WhatsApp')
        ->assertSee('Reintentar');

    expect(CrmHandoffMessage::query()->where('message_id', $draftId)->value('status'))->toBe('failed')
        ->and(Storage::disk('local')->get(CrmInboxQuote::path($draftId)))->toBe($pdf)
        ->and(CrmHandoffEnvelope::query()->where('handoff_id', '900002')->value('taken_at'))->not->toBeNull();

    $page->call('deliverReply', $draftId)
        ->assertDontSee('Reintentar');

    expect($documentCalls)->toBe(2)
        ->and(CrmHandoffMessage::query()->where('message_id', $draftId)->value('status'))->toBe('sent')
        ->and(Storage::disk('local')->exists(CrmInboxQuote::path($draftId)))->toBeFalse();
});

it('con una sola cobertura de Ideal el PDF trae solo esa columna', function (): void {
    takenQuoteEnvelope();
    fakeIdealQuote();

    $result = app(CrmInboxQuote::class)->preview(CrmHandoffEnvelope::query()->firstOrFail(), 'María Peña', '30', 'ideal', ['3000'], 'Ana Rojas');

    expect($result['ok'])->toBeTrue()
        ->and($result['draft']['total'])->toBe('231')
        ->and($result['draft']['coverages'])->toBe([3000])
        ->and($result['draft']['caption'])->toBe('Propuesta 0009101 · USD 231 al año')
        ->and(Storage::disk('local')->get(CrmInboxQuote::path($result['draft']['id'])))->toBe("%PDF-1.4\n%recortado\n");

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/api/cotizar')
        && ($request['cobertura'] ?? null) === 3000
        && ($request['formato'] ?? null) === 'json_sin_pdf'
        && ($request['planes'] ?? null) === ['ideal']);

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/render')
        && $request['control'] === '0009101'
        && $request['agente'] === 'Ana Rojas'
        && $request['planes'][0]['plan'] === 'ideal'
        && $request['planes'][0]['coberturas'] === ['3K']
        && $request['planes'][0]['filas'] === [['rango' => '0 a 45 años', 'poblacion' => 1, 'tarifas' => [231]]]
        && $request['planes'][0]['grupal_anual'] === [231]
        && $request['planes'][0]['grupal_trimestral'] === [57.75]);
});

it('con varias coberturas el PDF trae solo esas, en el orden del plan, y el texto da el rango', function (): void {
    takenQuoteEnvelope();
    fakeIdealQuote();

    $result = app(CrmInboxQuote::class)->preview(CrmHandoffEnvelope::query()->firstOrFail(), 'María Peña', '30', 'ideal', ['10000', '1000', '3000', '3000'], 'Ana Rojas');

    expect($result['ok'])->toBeTrue()
        ->and($result['draft']['coverages'])->toBe([1000, 3000, 10000])
        ->and($result['draft']['total'])->toBe('189 – 284')
        ->and($result['draft']['caption'])->toBe('Propuesta 0009101 · 3 coberturas · desde USD 189 hasta USD 284 al año');

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/render')
        && $request['planes'][0]['coberturas'] === ['1K', '3K', '10K']
        && $request['planes'][0]['filas'][0]['tarifas'] === [189, 231, 284]
        && $request['planes'][0]['grupal_anual'] === [189, 231, 284]
        && $request['planes'][0]['grupal_semestral'] === [94.5, 115.5, 142]);
});

it('con todas las coberturas usa el PDF del cotizador en una sola llamada', function (): void {
    takenQuoteEnvelope();
    fakeIdealQuote();

    $result = app(CrmInboxQuote::class)->preview(CrmHandoffEnvelope::query()->firstOrFail(), 'María Peña', '30', 'ideal', ['1000', '2000', '3000', '5000', '10000'], 'Ana Rojas');

    expect($result['ok'])->toBeTrue()
        ->and($result['draft']['caption'])->toBe('Propuesta 0009101 · 5 coberturas · desde USD 189 hasta USD 284 al año')
        ->and(Storage::disk('local')->get(CrmInboxQuote::path($result['draft']['id'])))->toBe("%PDF-1.4\n%completo\n");

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/api/cotizar') && ($request['formato'] ?? null) === 'json');
    Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/render'));
});

it('no cotiza sin cobertura elegida ni con un monto que el plan no ofrece', function (array $coverages): void {
    takenQuoteEnvelope();
    fakeIdealQuote();

    $result = app(CrmInboxQuote::class)->preview(CrmHandoffEnvelope::query()->firstOrFail(), 'María Peña', '30', 'ideal', $coverages, 'Ana Rojas');

    expect($result)->toBe(['ok' => false, 'reason' => 'invalid', 'detail' => 'Elige al menos una cobertura.']);

    Http::assertNothingSent();
})->with([
    'ninguna' => [[]],
    'monto ajeno' => [['3000', '7000']],
    'monto de otro plan' => [['50000']],
    'texto' => [['3000; DROP']],
]);

it('no envía un PDF con columnas de más si el cotizador no trae la cobertura pedida', function (): void {
    takenQuoteEnvelope();

    Http::fake([
        'http://quote.test/api/cotizar' => Http::response([
            'ok' => true,
            'control' => '0009102',
            'planes' => [[
                'plan' => 'ideal',
                'coberturas_usd' => [1000, 2000],
                'coberturas' => ['1K', '2K'],
                'filas' => [['rango' => '0 a 45 años', 'poblacion' => 1, 'tarifas' => [189, 205]]],
                'grupal_anual' => [189, 205],
                'grupal_semestral' => [94.5, 102.5],
                'grupal_trimestral' => [47.25, 51.25],
            ]],
        ], 200),
        'http://quote.test/render' => Http::response("%PDF-1.4\n", 200),
    ]);

    $result = app(CrmInboxQuote::class)->preview(CrmHandoffEnvelope::query()->firstOrFail(), 'María Peña', '30', 'ideal', ['10000'], 'Ana Rojas');

    expect($result['ok'])->toBeFalse()
        ->and($result['reason'])->toBe('unavailable');

    Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/render'));
});

it('el formulario marca varias coberturas, las limpia al cambiar de plan y la tarjeta las lista', function (): void {
    quoteInboxEnvelope();
    quoteInboxAnalyst();
    fakeIdealQuote(['http://n8n.test/*' => Http::response(['ok' => true, 'message_id' => 'wamid.2'], 200)]);

    $page = Livewire::test(AtencionWhatsapp::class)
        ->call('select', '900002')
        ->call('take')
        ->call('openQuote')
        ->set('quoteAges', '30')
        ->set('quotePlan', 'ideal')
        ->assertSee('Coberturas')
        ->assertSee('USD 10.000')
        ->assertDontSee('Elige el monto')
        ->set('quoteCoverages', ['2000', '10000'])
        ->set('quotePlan', 'especial')
        ->assertSet('quoteCoverages', ['10000'])
        ->set('quotePlan', 'ideal')
        ->set('quoteCoverages', ['1000', '3000'])
        ->call('previewQuote')
        ->assertSet('quoteDraft.coverages', [1000, 3000])
        ->assertSet('quoteDraft.total', '189 – 231');

    $draftId = $page->get('quoteDraft')['id'];

    $page->call('sendQuote')
        ->assertNotified('Propuesta enviada')
        ->assertSee('· USD 1.000 · 3.000')
        ->assertSee('USD 189 – 231');

    expect(CrmHandoffMessage::query()->where('message_id', $draftId)->first()->meta)->toMatchArray([
        'coverage' => null,
        'coverages' => [1000, 3000],
        'total' => '189 – 231',
    ]);
});

function takenQuoteEnvelope(): void
{
    quoteInboxEnvelope()->forceFill([
        'taken_at' => now(),
        'taken_by' => 9,
    ])->save();
}

/**
 * @param  array<string, mixed>  $extra
 */
function fakeIdealQuote(array $extra = []): void
{
    Http::fake([
        'http://quote.test/api/cotizar' => function ($request) {
            $full = ($request['formato'] ?? null) === 'json';

            return Http::response([
                'ok' => true,
                'control' => '0009101',
                'titular' => 'María Peña',
                'agente' => 'Ana Rojas',
                'fecha' => '14/10/2026',
                'planes' => [[
                    'plan' => 'ideal',
                    'nombre' => 'Plan Ideal',
                    'coberturas_usd' => [1000, 2000, 3000, 5000, 10000],
                    'coberturas' => ['1K', '2K', '3K', '5K', '10K'],
                    'filas' => [['rango' => '0 a 45 años', 'poblacion' => 1, 'tarifas' => [189, 205, 231, 259, 284], 'personas' => ['Afiliado 1']]],
                    'grupal_anual' => [189, 205, 231, 259, 284],
                    'grupal_semestral' => [94.5, 102.5, 115.5, 129.5, 142],
                    'grupal_trimestral' => [47.25, 51.25, 57.75, 64.75, 71],
                ]],
                'pdf_base64' => $full ? base64_encode("%PDF-1.4\n%completo\n") : null,
            ], 200);
        },
        'http://quote.test/render' => Http::response("%PDF-1.4\n%recortado\n", 200),
        ...$extra,
    ]);
}

function quoteInboxEnvelope(): CrmHandoffEnvelope
{
    return CrmHandoffEnvelope::factory()->create([
        'handoff_id' => '900002',
        'phone' => '584120000002',
        'area' => 'comercial',
        'motivo' => 'el precio',
        'payload' => [
            'name' => 'María Peña',
            'motivo' => 'el precio',
            'necesidad' => 'plan familiar',
            'ultimo_mensaje' => 'quiero una cotización',
            'mensajes' => [],
        ],
        'accepted_at' => now(),
    ]);
}

function quoteInboxAnalyst(): void
{
    test()->actingAs(User::factory()->make([
        'id' => 9,
        'name' => 'Ana Rojas',
        'email' => 'analista@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS', 'SUPERADMIN'],
    ]));

    Filament::setCurrentPanel('business');
}
