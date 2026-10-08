<?php

declare(strict_types=1);

use App\Filament\Business\Pages\AtencionWhatsapp;
use App\Models\CrmPushSubscription;
use App\Models\User;
use App\Support\CrmInbox\CrmPushSubscriptions;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
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
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');

    if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
        throw new RuntimeException('Los avisos del CRM deben probarse en sqlite en memoria.');
    }

    Schema::dropIfExists('crm_push_subscriptions');
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

    Schema::create('crm_push_subscriptions', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('endpoint', 500)->unique();
        $table->string('public_key');
        $table->string('auth_token');
        $table->string('content_encoding', 32)->default('aes128gcm');
        $table->timestamps();
        $table->index('user_id');
    });
});

it('guarda una sola suscripción por navegador y la olvida al salir', function (): void {
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php');
    $worker = file_get_contents(dirname(__DIR__, 2).'/public/crm-inbox/sw.js');

    expect($provider)->toContain("Event::listen(Logout::class, [CrmPushSubscriptions::class, 'onLogout'])")
        ->and($worker)->not->toContain("addEventListener('fetch'")
        ->and($worker)->toContain('showNotification')
        ->and($worker)->toContain('postMessage')
        ->and($worker)->toContain('requireInteraction')
        ->and($worker)->toContain('crm-open')
        ->and($worker)->not->toContain('visibilityState');

    CrmPushSubscriptions::store(9, 'https://push.example.test/ana', 'clave', 'secreto', 'aes128gcm');
    CrmPushSubscriptions::store(9, 'https://push.example.test/ana', 'clave-nueva', 'secreto', 'aes128gcm');
    CrmPushSubscriptions::store(4, 'https://push.example.test/otro', 'clave', 'secreto', 'aes128gcm');

    expect(CrmPushSubscription::query()->count())->toBe(2)
        ->and(CrmPushSubscription::query()->where('endpoint', 'https://push.example.test/ana')->value('public_key'))->toBe('clave-nueva');

    CrmPushSubscriptions::forgetForUser(9);

    expect(CrmPushSubscription::query()->where('user_id', 9)->count())->toBe(0)
        ->and(CrmPushSubscription::query()->where('user_id', 4)->count())->toBe(1);
});

it('registra el navegador del analista y rechaza un destino inválido', function (): void {
    $this->actingAs(User::factory()->make([
        'id' => 9,
        'name' => 'Ana Rojas',
        'email' => 'analista@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS'],
    ]));
    Filament::setCurrentPanel('business');

    Livewire::test(AtencionWhatsapp::class)
        ->call('savePushSubscription', [
            'endpoint' => 'https://push.example.test/ana',
            'keys' => ['p256dh' => 'clave', 'auth' => 'secreto'],
        ])
        ->assertNotified('Avisos activados')
        ->call('savePushSubscription', [
            'endpoint' => 'javascript:alert(1)',
            'keys' => ['p256dh' => 'clave', 'auth' => 'secreto'],
        ])
        ->assertNotified('No se pudo activar los avisos')
        ->call('savePushSubscription', [
            'endpoint' => 'https://push.example.test/silencio',
            'keys' => ['p256dh' => 'clave', 'auth' => 'secreto'],
        ], true)
        ->assertNotNotified('Avisos activados');

    expect(CrmPushSubscription::query()->count())->toBe(2)
        ->and(CrmPushSubscription::query()->where('user_id', 9)->count())->toBe(2);
});
