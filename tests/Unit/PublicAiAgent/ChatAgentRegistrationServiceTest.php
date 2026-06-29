<?php

declare(strict_types=1);

use App\Jobs\SendCartaBienvenidaAgenteAgencia;
use App\Jobs\SendChatAgentRegistrationWhatsAppJob;
use App\Models\Agent;
use App\Models\User;
use App\Services\PublicAiAgent\ChatAgentRegistrationService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

it('registra agente y usuario y encola carta de bienvenida', function (): void {
    if (! Schema::hasTable('agents') || ! Schema::hasTable('users')) {
        $this->markTestSkipped('Tablas agents/users no disponibles.');
    }

    Bus::fake();

    $email = 'chat-agent-api-'.uniqid().'@test.invalid';
    $service = new ChatAgentRegistrationService;

    $result = $service->register([
        'name' => 'Agente Chat Test',
        'identity_document' => 'v-'.random_int(10000000, 99999999),
        'email' => $email,
        'phone' => '04127018390',
        'owner_code' => 'TDG-101',
        'classification' => 'agent',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['email'])->toBe($email)
        ->and($result['data']['password'])->not->toBeEmpty()
        ->and($result['data']['login_url'])->toBe((string) config('services.chat_agent_registration.portal_login_url'));

    $agent = Agent::query()->where('email', $email)->first();
    $user = User::query()->where('email', $email)->first();

    expect($agent)->not->toBeNull()
        ->and($user)->not->toBeNull()
        ->and((bool) $user->is_agent)->toBeTrue()
        ->and($agent->status)->toBe('ACTIVO')
        ->and($user->status)->toBe('ACTIVO');

    Bus::assertDispatched(SendCartaBienvenidaAgenteAgencia::class);
    Bus::assertDispatched(SendChatAgentRegistrationWhatsAppJob::class);

    expect($result['data']['whatsapp_registration_queued'] ?? false)->toBeTrue();

    if ($agent !== null) {
        DB::table('users')->where('agent_id', $agent->id)->delete();
        DB::table('agents')->where('id', $agent->id)->delete();
    }
});

it('rechaza correo duplicado con respuesta legible', function (): void {
    if (! Schema::hasTable('users')) {
        $this->markTestSkipped('Tabla users no disponible.');
    }

    $email = 'chat-agent-dup-'.uniqid().'@test.invalid';

    $userPayload = [
        'name' => 'Usuario Existente',
        'email' => $email,
        'password' => bcrypt('password'),
    ];

    if (Schema::hasColumn('users', 'phone')) {
        $userPayload['phone'] = '04149999999';
    }

    User::query()->create($userPayload);

    $service = new ChatAgentRegistrationService;
    $result = $service->register([
        'name' => 'Agente Duplicado',
        'identity_document' => 'v-'.random_int(10000000, 99999999),
        'email' => $email,
        'phone' => '04141234567',
        'owner_code' => 'TDG-101',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->not->toBeEmpty();

    User::query()->where('email', $email)->delete();
});

it('expone url de whatsapp de negocios', function (): void {
    $service = new ChatAgentRegistrationService;

    expect($service->whatsappBusinessUrl())->toBe('https://wa.me/584127018390')
        ->and($service->whatsappBusinessDisplayLabel())->toBe('+58 412 701 8390');
});

it('usa ruta dedicada para carta de bienvenida whatsapp y copia pdf del job de correo', function (): void {
    $service = new ChatAgentRegistrationService;
    $agentId = 88001;
    $legacyRelative = $service->welcomeLetterFilename($agentId);
    $whatsappRelative = $service->welcomeLetterRelativePath($agentId);
    $legacyFullPath = public_path('storage/'.$legacyRelative);
    $whatsappFullPath = public_path('storage/'.$whatsappRelative);

    @mkdir(dirname($legacyFullPath), 0755, true);
    file_put_contents($legacyFullPath, '%PDF-1.4 chat-agent-welcome-test');

    $resolvedPath = $service->ensureWelcomeLetterPdf($agentId, 'Agente Chat Welcome Test');

    expect($resolvedPath)->toBe($whatsappRelative)
        ->and(file_exists($whatsappFullPath))->toBeTrue()
        ->and(filesize($whatsappFullPath))->toBeGreaterThan(10);

    @unlink($legacyFullPath);
    @unlink($whatsappFullPath);
    @rmdir(dirname($whatsappFullPath));
});

it('envia whatsapp con imagen integracorp en el encabezado', function (): void {
    foreach ([
        'ChatAgentRegistrationService.php',
        'ChatAgencyMasterRegistrationService.php',
        'ChatAgencyGeneralRegistrationService.php',
    ] as $serviceFile) {
        $contents = file_get_contents(dirname(__DIR__, 3).'/app/Services/PublicAiAgent/'.$serviceFile);

        expect($contents)
            ->toContain('sendIntegracorpBrandWhatsAppCaption')
            ->toContain('afterResponse()');
    }
});
