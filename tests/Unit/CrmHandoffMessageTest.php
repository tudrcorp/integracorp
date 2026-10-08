<?php

declare(strict_types=1);

use App\Models\CrmHandoffEnvelope;
use App\Models\CrmHandoffMessage;
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
        throw new RuntimeException('El mensaje del handoff debe probarse en sqlite en memoria.');
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
        $table->timestamp('last_customer_at')->nullable();
        $table->string('last_customer_text', 200)->nullable();
        $table->timestamps();
    });

    Schema::create('crm_handoff_messages', function (Blueprint $table): void {
        $table->id();
        $table->string('message_id', 191)->unique();
        $table->string('handoff_id', 32)->nullable();
        $table->string('phone', 20);
        $table->text('body');
        $table->timestamp('received_at')->nullable();
        $table->timestamps();
    });
});

it('guarda el mensaje del cliente una sola vez y lo une al caso de ese teléfono', function (): void {
    CrmHandoffEnvelope::factory()->create([
        'handoff_id' => '900002',
        'phone' => '584120000002',
        'area' => 'comercial',
    ]);

    $body = json_encode([
        'phone' => '+58 412-000-0002',
        'message_id' => 'wamid.HBgLNTQ',
        'text' => 'sigo aquí, quiero al asesor',
    ], JSON_THROW_ON_ERROR);

    $first = crmMessagePost($body);
    $second = crmMessagePost($body);

    $first->assertAccepted();
    $second->assertAccepted();
    $second->assertJson(['duplicate' => true]);

    expect(CrmHandoffMessage::query()->count())->toBe(1);

    $row = CrmHandoffMessage::query()->first();
    $envelope = CrmHandoffEnvelope::query()->where('handoff_id', '900002')->first();

    expect($row)->not->toBeNull()
        ->and($row->handoff_id)->toBe('900002')
        ->and($row->body)->toBe('sigo aquí, quiero al asesor')
        ->and($envelope->last_customer_text)->toBe('sigo aquí, quiero al asesor');
});

it('rechaza un mensaje sin firma y no lo guarda', function (): void {
    $body = json_encode([
        'phone' => '584120000002',
        'message_id' => 'wamid.sin-firma',
        'text' => 'hola',
    ], JSON_THROW_ON_ERROR);

    crmMessagePost($body, signature: '000')->assertUnauthorized();

    expect(CrmHandoffMessage::query()->count())->toBe(0);
});

function crmMessagePost(string $body, ?string $signature = null): TestResponse
{
    $timestamp = (string) time();
    $signature ??= CrmHandoffSignature::sign($timestamp, $body, 'test-secret');

    return test()->call('POST', '/api/crm/handoff/message', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_CRM_TIMESTAMP' => $timestamp,
        'HTTP_X_CRM_SIGNATURE' => $signature,
    ], content: $body);
}
