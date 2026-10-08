<?php

declare(strict_types=1);

use App\Jobs\NotifyCrmHandoffJob;
use App\Jobs\StoreCrmHandoffEnvelopeJob;
use App\Models\CrmHandoffEnvelope;
use App\Models\CrmPushSubscription;
use App\Support\CrmInbox\CrmInboxAlerts;
use App\Support\CrmInbox\CrmInboxNotices;
use App\Support\CrmInbox\CrmWebPush;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'session.driver' => 'array',
        'cache.default' => 'array',
        'cache.stores.crm-inbox' => ['driver' => 'array', 'serialize' => false],
        'crm-inbox.cache_store' => 'crm-inbox',
        'crm-inbox.queue_connection' => 'sync',
        'crm-inbox.queue' => 'inbox',
        'app.url' => 'https://www.integracorp.test',
        'queue.default' => 'sync',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');
    app('cache')->forgetDriver('crm-inbox');
    Cache::store('crm-inbox')->flush();

    if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
        throw new RuntimeException('Los avisos del CRM deben probarse en sqlite en memoria.');
    }

    Schema::dropIfExists('notifications');
    Schema::dropIfExists('crm_push_subscriptions');
    Schema::dropIfExists('crm_handoff_envelopes');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
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
        $table->timestamp('last_customer_at')->nullable();
        $table->string('last_customer_text', 200)->nullable();
        $table->timestamps();
    });

    Schema::create('crm_push_subscriptions', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('endpoint', 500)->unique();
        $table->string('public_key');
        $table->string('auth_token');
        $table->string('content_encoding', 32)->default('aes128gcm');
        $table->timestamps();
    });
});

it('elige al analista aunque otra suscripción tenga el departamento ilegible', function (): void {
    DB::table('users')->insert([
        ['id' => 3, 'email' => 'laura@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => json_encode(['ADMINISTRACION']), 'created_at' => now(), 'updated_at' => now()],
        ['id' => 5, 'email' => 'roto@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => 'no-es-json', 'created_at' => now(), 'updated_at' => now()],
    ]);

    CrmPushSubscription::query()->insert([
        ['id' => 1, 'user_id' => 3, 'endpoint' => 'https://push.example.test/laura', 'public_key' => 'k', 'auth_token' => 'a', 'content_encoding' => 'aes128gcm', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'user_id' => 5, 'endpoint' => 'https://push.example.test/roto', 'public_key' => 'k', 'auth_token' => 'a', 'content_encoding' => 'aes128gcm', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $envelope = CrmHandoffEnvelope::query()->create([
        'handoff_id' => '12',
        'phone' => '584120000012',
        'area' => 'viajes',
        'motivo' => 'quiere un analista',
        'payload' => ['name' => 'María Peña'],
        'accepted_at' => now(),
    ]);

    expect(CrmInboxAlerts::subscriptionsFor($envelope)->pluck('user_id')->all())->toBe([3]);
});

it('al pasar el caso el aviso nombra a esa persona y al equipo nuevo', function (): void {
    $envelope = CrmHandoffEnvelope::query()->create([
        'handoff_id' => '12',
        'phone' => '584120000012',
        'area' => 'viajes',
        'motivo' => 'quiere un analista',
        'payload' => ['name' => 'María Peña', 'ultimo_mensaje' => 'comunícame con un analista'],
        'accepted_at' => now(),
    ]);

    expect(CrmInboxAlerts::payload($envelope, 'assign', 3)['title'])->toBe('Te pasaron una conversación')
        ->and(CrmInboxAlerts::payload($envelope, 'assign', 3)['tag'])->toBe('crm-handoff-12-para-3')
        ->and(CrmInboxAlerts::payload($envelope, 'move')['title'])->toBe('Llegó una conversación')
        ->and(CrmInboxAlerts::payload($envelope, 'move')['url'])->toContain('/administration/atencion-whatsapp?caso=12');
});

it('guarda el aviso en el sistema para quien recibe el caso aunque no tenga el navegador abierto', function (): void {
    Schema::create('notifications', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('type');
        $table->morphs('notifiable');
        $table->text('data');
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
    });

    DB::table('users')->insert([
        ['id' => 3, 'email' => 'laura@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => json_encode(['ADMINISTRACION']), 'created_at' => now(), 'updated_at' => now()],
        ['id' => 4, 'email' => 'ana@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => json_encode(['NEGOCIOS']), 'created_at' => now(), 'updated_at' => now()],
    ]);

    $envelope = CrmHandoffEnvelope::query()->create([
        'handoff_id' => '16',
        'phone' => '584120000016',
        'area' => 'viajes',
        'motivo' => 'quiere un analista',
        'payload' => ['name' => 'María Peña', 'ultimo_mensaje' => 'necesito ayuda'],
        'accepted_at' => now(),
    ]);

    CrmInboxAlerts::remember($envelope, 'assign', 3);
    CrmInboxAlerts::remember($envelope, 'assign', 3);

    $rows = DB::table('notifications')->get();

    expect($rows)->toHaveCount(1)
        ->and((int) $rows[0]->notifiable_id)->toBe(3);

    $data = json_decode((string) $rows[0]->data, true);

    expect($data['title'])->toBe('Te pasaron una conversación')
        ->and($data['viewData']['handoff_id'])->toBe('16')
        ->and($data['body'])->toContain('María Peña');

    $this->actingAs(\App\Models\User::query()->findOrFail(3));
    Filament::setCurrentPanel('administration');
    CrmInboxNotices::flashUnread();

    Notification::assertNotified('Te pasaron una conversación');
});

it('cifra el aviso y el navegador puede abrirlo', function (): void {
    $ua = openssl_pkey_new([
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    $details = openssl_pkey_get_details($ua);
    $uaPublic = chr(4).str_pad(substr($details['ec']['x'], -32), 32, "\0", STR_PAD_LEFT).str_pad(substr($details['ec']['y'], -32), 32, "\0", STR_PAD_LEFT);
    openssl_pkey_export($ua, $uaPem);
    $auth = random_bytes(16);
    $json = '{"title":"Nueva conversación","body":"María · Viajes"}';

    $body = app(CrmWebPush::class)->seal($json, $uaPublic, $auth);

    $salt = substr($body, 0, 16);
    $idLength = ord($body[20]);
    $senderPublic = substr($body, 21, $idLength);
    $encrypted = substr($body, 21 + $idLength);
    $tag = substr($encrypted, -16);
    $cipher = substr($encrypted, 0, -16);
    $prefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
    $senderPem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($prefix.$senderPublic), 64, "\n")."-----END PUBLIC KEY-----\n";
    $secret = openssl_pkey_derive($senderPem, $uaPem, 32);
    $ikm = hash_hkdf('sha256', $secret, 32, "WebPush: info\0".$uaPublic.$senderPublic, $auth);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
    $plain = openssl_decrypt($cipher, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    $plain = rtrim((string) $plain, "\0");
    $plain = str_ends_with($plain, "\x02") ? substr($plain, 0, -1) : $plain;

    expect($plain)->toBe($json);
});

it('avisa una sola vez a los analistas del área y olvida un navegador vencido', function (): void {
    $vapid = openssl_pkey_new([
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    $details = openssl_pkey_get_details($vapid);
    $public = rtrim(strtr(base64_encode(chr(4).str_pad(substr($details['ec']['x'], -32), 32, "\0", STR_PAD_LEFT).str_pad(substr($details['ec']['y'], -32), 32, "\0", STR_PAD_LEFT)), '+/', '-_'), '=');
    openssl_pkey_export($vapid, $pem);
    $private = rtrim(strtr(base64_encode($pem), '+/', '-_'), '=');
    config([
        'crm-inbox.push_public_key' => $public,
        'crm-inbox.push_private_key' => $private,
    ]);

    DB::table('users')->insert([
        ['id' => 3, 'email' => 'laura@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => json_encode(['ADMINISTRACION']), 'created_at' => now(), 'updated_at' => now()],
        ['id' => 4, 'email' => 'ana@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => json_encode(['NEGOCIOS']), 'created_at' => now(), 'updated_at' => now()],
    ]);

    $ua = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $uaDetails = openssl_pkey_get_details($ua);
    $uaPublic = rtrim(strtr(base64_encode(chr(4).str_pad(substr($uaDetails['ec']['x'], -32), 32, "\0", STR_PAD_LEFT).str_pad(substr($uaDetails['ec']['y'], -32), 32, "\0", STR_PAD_LEFT)), '+/', '-_'), '=');
    $auth = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');

    CrmPushSubscription::query()->insert([
        ['id' => 1, 'user_id' => 3, 'endpoint' => 'https://push.example.test/laura', 'public_key' => $uaPublic, 'auth_token' => $auth, 'content_encoding' => 'aes128gcm', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'user_id' => 4, 'endpoint' => 'https://push.example.test/ana', 'public_key' => $uaPublic, 'auth_token' => $auth, 'content_encoding' => 'aes128gcm', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $envelope = CrmHandoffEnvelope::query()->create([
        'handoff_id' => '11',
        'phone' => '584120000011',
        'area' => 'viajes',
        'motivo' => 'quiere un analista',
        'payload' => ['name' => 'María Peña', 'ultimo_mensaje' => 'comunícame con un analista'],
        'accepted_at' => now(),
    ]);

    Http::fake([
        'https://push.example.test/*' => Http::sequence()
            ->push('', 201)
            ->push('', 410),
    ]);

    $job = new NotifyCrmHandoffJob('11');
    $job->handle(app(CrmWebPush::class));
    $job->handle(app(CrmWebPush::class));

    Http::assertSentCount(1);
    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://push.example.test/laura'
            && str_starts_with($request->header('Authorization')[0] ?? '', 'vapid t=')
            && ($request->header('Content-Encoding')[0] ?? '') === 'aes128gcm';
    });

    expect(CrmInboxAlerts::payload($envelope)['url'])->toBe('https://www.integracorp.test/administration/atencion-whatsapp?caso=11')
        ->and(CrmInboxAlerts::payload($envelope)['body'])->toContain('María Peña')
        ->and(CrmInboxAlerts::payload($envelope)['body'])->toContain('Viajes');

    Cache::store('crm-inbox')->flush();
    $job->handle(app(CrmWebPush::class));

    expect(CrmPushSubscription::query()->count())->toBe(1)
        ->and((int) CrmPushSubscription::query()->value('user_id'))->toBe(4);
});

it('encola el aviso al guardar el caso y no lo manda dentro del webhook', function (): void {
    Queue::fake();

    $job = new StoreCrmHandoffEnvelopeJob([
        'handoff_id' => '11',
        'phone' => '584120000011',
        'area' => 'viajes',
        'motivo' => 'quiere un analista',
        'name' => 'María Peña',
    ]);
    $job->handle();
    app()->terminate();

    Queue::assertPushed(NotifyCrmHandoffJob::class, function (NotifyCrmHandoffJob $pushed): bool {
        return $pushed->handoffId === '11';
    });
    expect(CrmHandoffEnvelope::query()->where('handoff_id', '11')->exists())->toBeTrue();
});
