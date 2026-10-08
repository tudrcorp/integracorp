<?php

declare(strict_types=1);

use App\Jobs\StoreCrmHandoffEnvelopeJob;
use App\Models\CrmHandoffEnvelope;
use App\Support\CrmInbox\CrmHandoffIngress;
use App\Support\CrmInbox\CrmHandoffSignature;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    app()['env'] = 'testing';

    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'session.driver' => 'array',
        'cache.default' => 'array',
        'cache.stores.crm-inbox' => ['driver' => 'array', 'serialize' => false],
        'crm-inbox.secret' => 'test-secret',
        'crm-inbox.tolerance' => 300,
        'crm-inbox.cache_store' => 'crm-inbox',
        'crm-inbox.queue_connection' => 'sync',
        'crm-inbox.queue' => 'inbox',
        'crm-inbox.rate_per_minute' => 600,
        'crm-inbox.max_bytes' => 65536,
        'queue.default' => 'sync',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');
    app('cache')->forgetDriver('crm-inbox');
    Cache::store('crm-inbox')->flush();

    if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
        throw new RuntimeException('La prueba del handoff debe usar sqlite en memoria.');
    }

    Schema::dropIfExists('crm_handoff_envelopes');
    Schema::create('crm_handoff_envelopes', function (Blueprint $table): void {
        $table->id();
        $table->string('handoff_id', 32)->unique();
        $table->string('phone', 20);
        $table->string('area', 40)->nullable();
        $table->text('motivo')->nullable();
        $table->json('payload');
        $table->timestamp('accepted_at')->nullable();
        $table->timestamps();
    });
});

it('guarda un handoff firmado y no duplica el mismo caso', function (): void {
    $body = crmHandoffBody();

    $first = crmHandoffPost($body);
    $second = crmHandoffPost($body);

    $first->assertAccepted();
    $first->assertJson(['ok' => true, 'accepted' => true]);
    $second->assertAccepted();
    $second->assertJson(['duplicate' => true]);

    expect(CrmHandoffEnvelope::query()->count())->toBe(1);

    $row = CrmHandoffEnvelope::query()->first();

    expect($row)->not->toBeNull()
        ->and($row->handoff_id)->toBe('41')
        ->and($row->phone)->toBe('584241112233')
        ->and($row->area)->toBe('comercial')
        ->and($row->motivo)->toBe('pide asesor')
        ->and($row->payload['mensajes'][0]['text'])->toBe('Quiero hablar con una persona')
        ->and($row->payload['cotizacion']['control'])->toBe('0009012');
});

it('rechaza la firma, el reloj vencido y un cuerpo incompleto', function (): void {
    $body = crmHandoffBody();

    crmHandoffPost($body, secret: 'otra-clave')->assertUnauthorized()
        ->assertJson(['reason' => 'bad_signature']);

    crmHandoffPost($body, timestamp: time() - 3600)->assertUnauthorized()
        ->assertJson(['reason' => 'stale_timestamp']);

    crmHandoffPost('{"phone":"584241112233"}')->assertStatus(422)
        ->assertJson(['reason' => 'missing_handoff_id']);

    crmHandoffPost('{"handoff_id":41}')->assertStatus(422)
        ->assertJson(['reason' => 'missing_phone']);

    crmHandoffPost('{')->assertStatus(422)
        ->assertJson(['reason' => 'invalid_json']);

    expect(CrmHandoffEnvelope::query()->count())->toBe(0);
});

it('no acepta un cuerpo enorme ni peticiones por encima del cupo', function (): void {
    crmHandoffPost(str_repeat('a', 70000))->assertStatus(413);

    config(['crm-inbox.rate_per_minute' => 1]);

    crmHandoffPost(crmHandoffBody(42))->assertAccepted();
    crmHandoffPost(crmHandoffBody(43))->assertStatus(429);

    expect(CrmHandoffEnvelope::query()->count())->toBe(1);
});

it('no acepta handoffs si la clave no está configurada', function (): void {
    config(['crm-inbox.secret' => '']);

    crmHandoffPost(crmHandoffBody())->assertStatus(503);

    expect(CrmHandoffEnvelope::query()->count())->toBe(0);
});

it('encola en redis y solo permite sync en local o en pruebas', function (): void {
    expect(StoreCrmHandoffEnvelopeJob::resolveConnection('redis', false))->toBe('redis')
        ->and(StoreCrmHandoffEnvelopeJob::resolveConnection('database', false))->toBe('redis')
        ->and(StoreCrmHandoffEnvelopeJob::resolveConnection('sync', false))->toBe('redis')
        ->and(StoreCrmHandoffEnvelopeJob::resolveConnection('sync', true))->toBe('sync')
        ->and(StoreCrmHandoffEnvelopeJob::resolveConnection(null, false))->toBe('redis')
        ->and(CrmHandoffIngress::resolveCacheStore('file', false))->toBe('crm-inbox')
        ->and(CrmHandoffIngress::resolveCacheStore('file', true))->toBe('file')
        ->and(CrmHandoffIngress::resolveCacheStore('database', true))->toBe('crm-inbox');
});

it('firma el cuerpo con el timestamp delante', function (): void {
    $signature = CrmHandoffSignature::sign('1700000000', '{"handoff_id":1}', 'secreto');

    expect(CrmHandoffSignature::matches('1700000000', '{"handoff_id":1}', 'secreto', $signature))->toBeTrue()
        ->and(CrmHandoffSignature::matches('1700000000', '{"handoff_id":2}', 'secreto', $signature))->toBeFalse()
        ->and(CrmHandoffSignature::matches('1700000000', '{"handoff_id":1}', 'secreto', 'sha256='.$signature))->toBeTrue();
});

function crmHandoffBody(int $handoffId = 41): string
{
    return json_encode([
        'handoff_id' => $handoffId,
        'phone' => '+58 424-111-2233',
        'phone_number_id' => '1099',
        'area' => 'Comercial',
        'motivo' => 'pide asesor',
        'name' => 'Ana Pérez',
        'cotizacion' => ['control' => '0009012', 'total' => 320],
        'mensajes' => [
            ['id' => 9, 'direction' => 'in', 'type' => 'text', 'text' => 'Quiero hablar con una persona', 'hora' => '18:40'],
        ],
    ], JSON_THROW_ON_ERROR);
}

function crmHandoffPost(string $body, ?int $timestamp = null, string $secret = 'test-secret'): TestResponse
{
    $timestamp ??= time();
    $signature = CrmHandoffSignature::sign((string) $timestamp, $body, $secret);

    return test()->call('POST', '/api/crm/handoff', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_CRM_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_CRM_SIGNATURE' => $signature,
    ], content: $body);
}
