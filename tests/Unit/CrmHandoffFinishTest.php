<?php

declare(strict_types=1);

use App\Enums\CrmCaseOutcome;
use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
use App\Support\CrmInbox\CrmAnalystName;
use App\Support\CrmInbox\CrmHandoffFinish;
use App\Support\CrmInbox\CrmInboxDashboard;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

uses(Tests\TestCase::class);

/**
 * Finalizar conversación en Atención WhatsApp: mensaje de cierre, confirmación del bot y registro
 * para estadísticas, sin dejar nada a medias. n8n se simula; escribe dentro de una transacción que
 * siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    config([
        'crm-inbox.takeover_url' => 'http://n8n.test/webhook/handoff/takeover',
        'crm-inbox.takeover_key' => 'clave-de-prueba',
        'crm-inbox.finish_url' => '',
        'crm-inbox.reply_url' => '',
    ]);

    $this->userId = (int) App\Models\User::query()->where('status', 'ACTIVO')->orderBy('id')->value('id');
    $this->envelope = CrmHandoffEnvelope::query()->create([
        'handoff_id' => (string) random_int(800000000, 899999999),
        'phone' => '58414'.random_int(1000000, 9999999),
        'area' => 'comercial',
        'motivo' => 'Quiere hablar con una persona',
        'payload' => ['name' => 'GUSTAVO CAMACHO', 'lead' => ['nombre' => 'Gustavo']],
        'accepted_at' => now()->subMinutes(20),
        'taken_at' => now()->subMinutes(12),
        'taken_by' => $this->userId,
        'first_reply_at' => now()->subMinutes(11),
    ]);
});

afterEach(function (): void {
    DB::rollBack();

    try {
        Redis::connection('crm')->flushdb();
    } catch (Throwable) {
    }
});

function fakeBot(bool $replyOk = true, bool $finishOk = true): void
{
    /** Simulación nueva en cada llamada: un reintento ve la respuesta nueva del bot, no la anterior. */
    Http::swap(new Illuminate\Http\Client\Factory(app('events')));

    Http::fake([
        'n8n.test/webhook/handoff/reply' => Http::response($replyOk ? ['ok' => true, 'message_id' => 'WA-1'] : ['ok' => false], $replyOk ? 200 : 502),
        'n8n.test/webhook/handoff/finish' => Http::response($finishOk ? ['ok' => true, 'finished' => true] : ['ok' => false], $finishOk ? 200 : 500),
    ]);
}

it('el mensaje de cierre usa el nombre del cliente y sirve sin nombre', function (): void {
    expect(CrmAnalystName::finishing('GUSTAVO CAMACHO'))->toBe('Gracias por escribirnos, Gustavo. Damos por finalizada esta conversación; si necesitas algo más, escríbenos cuando quieras.')
        ->and(CrmAnalystName::finishing(''))->toStartWith('Gracias por escribirnos. Damos por finalizada')
        ->and(CrmAnalystName::finishing('Sin nombre'))->toStartWith('Gracias por escribirnos. Damos');
});

it('finaliza: envía el cierre, el bot confirma y el caso queda finalizado con su resultado', function (): void {
    fakeBot();

    $result = app(CrmHandoffFinish::class)->finish($this->envelope, $this->userId, CrmCaseOutcome::Sold, 'Afilió a su familia');
    $fresh = $this->envelope->fresh();
    $closing = CrmHandoffMessage::query()->where('handoff_id', $this->envelope->handoff_id)->where('kind', 'finish')->first();

    expect($result)->toBe(['ok' => true, 'already' => false])
        ->and($fresh->finished_at)->not->toBeNull()
        ->and((int) $fresh->finished_by)->toBe($this->userId)
        ->and($fresh->closed_at)->not->toBeNull()
        ->and($fresh->released_at)->toBeNull()
        ->and($fresh->outcome)->toBe('vendido')
        ->and($fresh->outcome_note)->toBe('Afilió a su familia')
        ->and($closing)->not->toBeNull()
        ->and($closing->status)->toBe('sent')
        ->and($closing->body)->toContain('Gracias por escribirnos, Gustavo');

    $sent = Http::recorded(fn (Request $request): bool => $request->url() === 'http://n8n.test/webhook/handoff/finish'
        && $request->header('X-Handoff-Key')[0] === 'clave-de-prueba'
        && $request['phone'] === $this->envelope->phone
        && $request['outcome'] === 'vendido'
        && $request['handoff_id'] === (string) $this->envelope->handoff_id);

    expect($sent)->toHaveCount(1);
});

it('pulsar dos veces no finaliza dos veces ni repite el mensaje', function (): void {
    fakeBot();
    $finish = app(CrmHandoffFinish::class);

    $finish->finish($this->envelope, $this->userId, CrmCaseOutcome::Solved);
    $second = $finish->finish($this->envelope, $this->userId, CrmCaseOutcome::Solved);

    expect($second)->toBe(['ok' => true, 'already' => true])
        ->and(CrmHandoffMessage::query()->where('handoff_id', $this->envelope->handoff_id)->where('kind', 'finish')->count())->toBe(1);
});

it('si el bot no confirma, el caso sigue en atención y al reintentar no se repite el mensaje', function (): void {
    fakeBot(finishOk: false);

    $failed = app(CrmHandoffFinish::class)->finish($this->envelope, $this->userId, CrmCaseOutcome::Quoted);

    expect($failed)->toBe(['ok' => false, 'reason' => 'rejected'])
        ->and($this->envelope->fresh()->finished_at)->toBeNull()
        ->and($this->envelope->fresh()->closed_at)->toBeNull()
        ->and($this->envelope->fresh()->outcome)->toBeNull();

    fakeBot();
    $retry = app(CrmHandoffFinish::class)->finish($this->envelope, $this->userId, CrmCaseOutcome::Quoted);

    expect($retry['ok'])->toBeTrue()
        ->and($this->envelope->fresh()->finished_at)->not->toBeNull()
        ->and(CrmHandoffMessage::query()->where('handoff_id', $this->envelope->handoff_id)->where('kind', 'finish')->count())->toBe(1);
});

it('si el mensaje de cierre no sale, no se avisa al bot ni se finaliza', function (): void {
    fakeBot(replyOk: false);

    $result = app(CrmHandoffFinish::class)->finish($this->envelope, $this->userId, CrmCaseOutcome::Solved);

    expect($result)->toBe(['ok' => false, 'reason' => 'farewell'])
        ->and($this->envelope->fresh()->finished_at)->toBeNull();

    expect(Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/handoff/finish')))->toHaveCount(0);
});

it('no finaliza un caso sin tomar, devuelto al bot o sin configuración', function (): void {
    fakeBot();
    $finish = app(CrmHandoffFinish::class);

    $untaken = $this->envelope->replicate()->fill(['handoff_id' => (string) random_int(900000000, 909999999), 'taken_at' => null, 'taken_by' => null]);
    $untaken->save();
    $released = $this->envelope->replicate()->fill(['handoff_id' => (string) random_int(910000000, 919999999), 'released_at' => now()]);
    $released->save();

    expect($finish->finish($untaken, $this->userId, CrmCaseOutcome::Solved))->toBe(['ok' => false, 'reason' => 'not_taken'])
        ->and($finish->finish($released, $this->userId, CrmCaseOutcome::Solved))->toBe(['ok' => false, 'reason' => 'ended']);

    config(['crm-inbox.takeover_url' => '', 'crm-inbox.finish_url' => '']);

    expect($finish->finish($this->envelope, $this->userId, CrmCaseOutcome::Solved))->toBe(['ok' => false, 'reason' => 'unconfigured']);
    expect(Http::recorded())->toHaveCount(0);
});

it('las estadísticas cuentan las finalizadas aparte de las devueltas al bot, con el tiempo de atención', function (): void {
    fakeBot();
    app(CrmHandoffFinish::class)->finish($this->envelope, $this->userId, CrmCaseOutcome::Sold);

    $released = $this->envelope->replicate()->fill(['handoff_id' => (string) random_int(920000000, 929999999), 'released_at' => now(), 'finished_at' => null, 'finished_by' => null, 'closed_at' => null]);
    $released->save();

    $data = CrmInboxDashboard::build('business', ['comercial'], false, 'hoy');

    expect($data['kpis']['finished'])->toBeGreaterThanOrEqual(1)
        ->and($data['kpis']['released'])->toBeGreaterThanOrEqual(1)
        ->and($data['kpis']['handle_median'])->toBeGreaterThan(0);
});

it('la bandeja tiene el botón, la tecla F y la acción en los tres paneles', function (): void {
    $root = dirname(__DIR__, 2);
    $view = (string) file_get_contents($root.'/resources/views/filament/crm/pages/atencion-whatsapp.blade.php');
    $page = (string) file_get_contents($root.'/app/Filament/Business/Pages/AtencionWhatsapp.php');

    expect($view)->toContain('data-finish')
        ->and($view)->toContain("if (action === 'finish') this.\$wire.finish(picked, note);")
        ->and($view)->toContain("key === 'f'")
        ->and($view)->toContain('Finalizar conversación…')
        ->and($page)->toContain('public function finish(string $outcome')
        ->and($page)->toContain('app(CrmHandoffFinish::class)->finish(');
});
