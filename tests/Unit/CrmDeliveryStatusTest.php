<?php

declare(strict_types=1);

use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
use App\Support\CrmInbox\CrmHandoffDeliveryIngress;
use App\Support\CrmInbox\CrmHandoffSignature;
use App\Support\CrmInbox\CrmInboxCache;
use App\Support\CrmInbox\CrmInboxPulse;
use App\Support\CrmInbox\CrmInboxQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * Doble check en Atención WhatsApp: entregado (✓✓ gris) y leído (✓✓ azul) de las respuestas del
 * analista. Escribe dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    CrmInboxCache::reset();
    config(['crm-inbox.secret' => 'secreto-qa', 'crm-inbox.tolerance' => 300]);

    $this->handoffId = (string) random_int(500000000, 599999999);
    $this->phone = '58414'.random_int(1000000, 9999999);
    CrmHandoffEnvelope::query()->create(['handoff_id' => $this->handoffId, 'phone' => $this->phone, 'area' => 'comercial', 'payload' => [], 'accepted_at' => now(), 'taken_at' => now()]);

    $this->out = fn (string $providerId, string $status = 'sent', string $kind = 'message'): CrmHandoffMessage => CrmHandoffMessage::query()->create([
        'message_id' => (string) Str::uuid(), 'handoff_id' => $this->handoffId, 'phone' => $this->phone, 'body' => 'Hola', 'received_at' => now(),
        'direction' => 'out', 'kind' => $kind, 'status' => $status, 'provider_message_id' => $providerId,
    ]);
});

afterEach(function (): void {
    DB::rollBack();
});

function deliveryBody(array $statuses): string
{
    return (string) json_encode(['statuses' => $statuses]);
}

it('valida y ordena los estados: leído gana a entregado y se descarta lo raro', function (): void {
    $parsed = CrmHandoffDeliveryIngress::parse(deliveryBody([
        ['message_id' => '3EB0A', 'status' => 'delivered'],
        ['message_id' => '3EB0A', 'status' => 'read'],
        ['message_id' => '3EB0B', 'status' => 'delivered'],
        ['message_id' => "x'; DROP", 'status' => 'read'],
        ['message_id' => '3EB0C', 'status' => 'played'],
        'basura',
    ]));

    expect($parsed)->toBe(['delivered' => ['3EB0B'], 'read' => ['3EB0A']])
        ->and(CrmHandoffDeliveryIngress::parse('{"statuses": {"a": 1}}'))->toBeNull()
        ->and(CrmHandoffDeliveryIngress::parse('no es json'))->toBeNull();
});

it('marca entregado y luego leído, sin retroceder ni cambiar la primera hora', function (): void {
    $message = ($this->out)('3EB0QA'.random_int(1000, 9999));
    $id = $message->provider_message_id;
    $t1 = Carbon::parse('2026-11-26 10:00:00');
    $t2 = Carbon::parse('2026-11-26 10:05:00');

    CrmHandoffDeliveryIngress::apply(['delivered' => [$id], 'read' => []], $t1);
    expect($message->fresh()->delivered_at->equalTo($t1))->toBeTrue()->and($message->fresh()->read_at)->toBeNull();

    CrmHandoffDeliveryIngress::apply(['delivered' => [], 'read' => [$id]], $t2);
    CrmHandoffDeliveryIngress::apply(['delivered' => [$id], 'read' => []], $t2->copy()->addMinute());
    CrmHandoffDeliveryIngress::apply(['delivered' => [], 'read' => [$id]], $t2->copy()->addMinutes(2));

    $fresh = $message->fresh();
    expect($fresh->delivered_at->equalTo($t1))->toBeTrue()
        ->and($fresh->read_at->equalTo($t2))->toBeTrue();
});

it('leído sin entregado previo también deja entregado con la misma hora', function (): void {
    $message = ($this->out)('3EB0RD'.random_int(1000, 9999));
    $at = Carbon::parse('2026-11-26 11:00:00');

    CrmHandoffDeliveryIngress::apply(['delivered' => [], 'read' => [$message->provider_message_id]], $at);

    expect($message->fresh()->read_at->equalTo($at))->toBeTrue()
        ->and($message->fresh()->delivered_at->equalTo($at))->toBeTrue();
});

it('no toca mensajes del cliente ni ids desconocidos', function (): void {
    $inbound = CrmHandoffMessage::query()->create(['message_id' => 'WA-IN-'.random_int(1000, 9999), 'handoff_id' => $this->handoffId, 'phone' => $this->phone, 'body' => 'Hola', 'received_at' => now(), 'direction' => 'in', 'provider_message_id' => '3EB0IN'.random_int(1000, 9999)]);

    $changed = CrmHandoffDeliveryIngress::apply(['delivered' => [], 'read' => [$inbound->provider_message_id, '3EB0NOEXISTE']]);

    expect($changed)->toBe(0)->and($inbound->fresh()->read_at)->toBeNull();
});

it('solo despierta el chat del caso: sube el pulso de ese caso, no el de toda la bandeja', function (): void {
    $message = ($this->out)('3EB0PL'.random_int(1000, 9999));
    $global = CrmInboxPulse::current();
    $thread = CrmInboxPulse::currentThread($this->handoffId);
    $before = CrmInboxQueue::fingerprint(['comercial'], false, $this->handoffId);

    CrmHandoffDeliveryIngress::apply(['delivered' => [], 'read' => [$message->provider_message_id]]);

    expect(CrmInboxPulse::current())->toBe($global)
        ->and(CrmInboxPulse::currentThread($this->handoffId))->not->toBe($thread)
        ->and(CrmInboxQueue::fingerprint(['comercial'], false, $this->handoffId))->not->toBe($before);
});

it('la puerta exige firma y reloj vigentes, y con firma buena aplica', function (): void {
    $message = ($this->out)('3EB0HT'.random_int(1000, 9999));
    $body = deliveryBody([['message_id' => $message->provider_message_id, 'status' => 'read', 'at' => now()->toIso8601String()]]);
    $ts = (string) time();
    $ingress = new CrmHandoffDeliveryIngress;

    expect($ingress->accept($body, $ts, 'firma-mala', '127.0.0.1')->httpStatus())->toBe(401)
        ->and($ingress->accept($body, (string) (time() - 3600), CrmHandoffSignature::sign((string) (time() - 3600), $body, 'secreto-qa'), '127.0.0.1')->httpStatus())->toBe(401)
        ->and($message->fresh()->read_at)->toBeNull();

    $ok = $ingress->accept($body, $ts, CrmHandoffSignature::sign($ts, $body, 'secreto-qa'), '127.0.0.1');

    expect($ok->httpStatus())->toBe(202)
        ->and($ok->body()['ok'])->toBeTrue()
        ->and($message->fresh()->read_at)->not->toBeNull();
});

it('la ruta pública responde por la misma puerta (sin sesión ni CSRF)', function (): void {
    $body = deliveryBody([['message_id' => '3EB0RUTA', 'status' => 'delivered']]);
    $ts = (string) time();

    $response = app()->handle(Illuminate\Http\Request::create('/api/crm/handoff/status', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_CRM_TIMESTAMP' => $ts,
        'HTTP_X_CRM_SIGNATURE' => CrmHandoffSignature::sign($ts, $body, 'secreto-qa'),
    ], $body));

    expect($response->getStatusCode())->toBe(202);
});

it('el chat dibuja ✓, ✓✓ gris y ✓✓ azul con la hora de lectura', function (): void {
    $render = fn (string $delivery, bool $label = false): string => view('filament.crm.partials.delivery-ticks', [
        'message' => ['delivery' => $delivery, 'read_time' => '10:45', 'delivered_time' => '10:41'],
        'showLabel' => $label,
    ])->render();

    expect($render('sent'))->toContain('crm-ticks is-sent')->toContain('aún no llega')
        ->and($render('delivered'))->toContain('crm-ticks is-delivered')->toContain('Entregado a las 10:41')
        ->and($render('read', true))->toContain('crm-ticks is-read')->toContain('Leído a las 10:45')->toContain('crm-ticks-label is-read')
        ->and(trim($render('')))->toBe('');
});
