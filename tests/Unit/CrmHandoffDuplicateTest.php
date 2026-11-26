<?php

declare(strict_types=1);

use App\Jobs\NotifyCrmHandoffJob;
use App\Jobs\StoreCrmHandoffEnvelopeJob;
use App\Jobs\StoreCrmHandoffMessageJob;
use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
use App\Support\CrmInbox\CrmHandoffDuplicate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(Tests\TestCase::class);

/**
 * Una persona, un caso abierto: una segunda derivación del mismo teléfono se une al caso que ya
 * está en la bandeja. Escribe dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Queue::fake();
    $this->phone = '58424'.random_int(1000000, 9999999);
    $this->first = (string) random_int(700000000, 709999999);
    $this->second = (string) random_int(710000000, 719999999);
});

afterEach(function (): void {
    DB::rollBack();
});

function storeEnvelope(string $handoffId, string $phone, array $extra = []): void
{
    (new StoreCrmHandoffEnvelopeJob([
        'handoff_id' => $handoffId,
        'phone' => $phone,
        'area' => 'emergencias',
        'motivo' => 'Esposo con mareo en Boleíta',
        'name' => 'Luce',
        ...$extra,
    ]))->handle();
}

it('la segunda derivación del mismo cliente no abre otro caso: queda como nota en el abierto', function (): void {
    storeEnvelope($this->first, $this->phone);
    storeEnvelope($this->second, $this->phone, ['area' => 'operaciones', 'motivo' => 'Datos del paciente para atención domiciliaria', 'ultimo_mensaje' => '04242010306 el paciente se llama Giancarlo Esquivel']);

    $open = CrmHandoffEnvelope::query()->where('phone', $this->phone)->get();
    $note = CrmHandoffMessage::query()->where('message_id', 'merge:'.$this->second)->first();

    expect($open)->toHaveCount(1)
        ->and($open->first()->handoff_id)->toBe($this->first)
        ->and($open->first()->last_customer_text)->toContain('Giancarlo Esquivel')
        ->and($note)->not->toBeNull()
        ->and($note->handoff_id)->toBe($this->first)
        ->and($note->kind)->toBe('note')
        ->and($note->body)->toContain('Sol volvió a derivar esta conversación al área')
        ->and($note->body)->toContain('Giancarlo Esquivel')
        ->and($note->body)->toContain('No se abrió otro caso; si corresponde, muévelo a');

    $notified = Queue::pushed(NotifyCrmHandoffJob::class)->map(fn (NotifyCrmHandoffJob $job): string => $job->handoffId)->values()->all();

    expect($notified)->toBe([$this->first]);
});

it('un reintento de la misma derivación repetida no duplica la nota', function (): void {
    storeEnvelope($this->first, $this->phone);
    storeEnvelope($this->second, $this->phone);
    storeEnvelope($this->second, $this->phone);

    expect(CrmHandoffMessage::query()->where('message_id', 'merge:'.$this->second)->count())->toBe(1)
        ->and(CrmHandoffEnvelope::query()->where('phone', $this->phone)->count())->toBe(1);
});

it('después de finalizar o devolver al bot, una nueva derivación sí abre un caso nuevo', function (string $column): void {
    storeEnvelope($this->first, $this->phone);
    CrmHandoffEnvelope::query()->where('handoff_id', $this->first)->update([$column => now()]);

    storeEnvelope($this->second, $this->phone);

    expect(CrmHandoffEnvelope::query()->where('phone', $this->phone)->pluck('handoff_id')->all())->toEqual([$this->first, $this->second]);
})->with(['finalizado o cerrado' => 'closed_at', 'devuelto al bot' => 'released_at']);

it('otro cliente no se une: cada teléfono tiene su caso', function (): void {
    storeEnvelope($this->first, $this->phone);
    storeEnvelope($this->second, '58414'.random_int(1000000, 9999999));

    expect(CrmHandoffEnvelope::query()->whereIn('handoff_id', [$this->first, $this->second])->count())->toBe(2);
});

it('el mismo número de derivación repetido sigue siendo el mismo caso', function (): void {
    storeEnvelope($this->first, $this->phone);
    storeEnvelope($this->first, $this->phone);

    expect(CrmHandoffEnvelope::query()->where('phone', $this->phone)->count())->toBe(1)
        ->and(CrmHandoffMessage::query()->where('message_id', 'like', 'merge:%')->where('phone', $this->phone)->count())->toBe(0);
});

it('un mensaje con el número de la derivación unida queda en el hilo del caso abierto', function (): void {
    storeEnvelope($this->first, $this->phone);
    storeEnvelope($this->second, $this->phone);

    (new StoreCrmHandoffMessageJob([
        'message_id' => 'WA-'.random_int(100000, 999999),
        'handoff_id' => $this->second,
        'phone' => $this->phone,
        'text' => 'Ya lo llamaron?',
    ]))->handle();

    expect(CrmHandoffMessage::query()->where('phone', $this->phone)->where('body', 'Ya lo llamaron?')->value('handoff_id'))->toBe($this->first);
});

it('la nota sin cambio de área ni mensaje nuevo es corta y clara', function (): void {
    $open = new CrmHandoffEnvelope(['area' => 'comercial', 'phone' => $this->phone]);

    expect(CrmHandoffDuplicate::note($open, ['area' => 'comercial', 'motivo' => 'Pide un analista.']))
        ->toBe('Sol volvió a derivar esta conversación: Pide un analista. No se abrió otro caso.');
});
