<?php

declare(strict_types=1);

use App\Jobs\SendCartaBienvenidaAgenteAgenciaTwo;
use App\Jobs\SendChatAgencyGeneralRegistrationWhatsAppJob;
use App\Jobs\SendChatAgencyMasterRegistrationWhatsAppJob;
use App\Models\Agency;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\PublicAiAgent\AgentConversationStateMachine;
use App\Services\PublicAiAgent\AgentOrchestrator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('chat_messages');
    Schema::dropIfExists('chat_sessions');

    Schema::create('chat_sessions', function (Blueprint $table): void {
        $table->id();
        $table->string('public_token', 80)->unique();
        $table->string('status')->default('active');
        $table->string('current_state')->default('saludo');
        $table->string('detected_intent')->nullable();
        $table->boolean('handoff_requested')->default(false);
        $table->text('handoff_reason')->nullable();
        $table->text('context_summary')->nullable();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamp('last_message_at')->nullable();
        $table->timestamps();
    });

    Schema::create('chat_messages', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('chat_session_id');
        $table->string('role', 20);
        $table->longText('content')->nullable();
        $table->string('tool_name')->nullable();
        $table->string('tool_call_id')->nullable();
        $table->json('tool_arguments')->nullable();
        $table->json('tool_result')->nullable();
        $table->string('model')->nullable();
        $table->unsignedInteger('prompt_tokens')->nullable();
        $table->unsignedInteger('completion_tokens')->nullable();
        $table->unsignedInteger('total_tokens')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
    });
});

it('muestra bienvenida simplificada para agencia master y general', function (): void {
    $slotFiller = new \App\Services\PublicAiAgent\IntentSlotFiller;

    expect($slotFiller->agencyMasterWelcomeMessage(
        $slotFiller->registrationWelcomeHeadline(AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER),
    ))
        ->toContain('registro interactivo de tu Agencia Master')
        ->toContain('Agencia Master')
        ->toContain('RIF o número de cédula')
        ->and($slotFiller->agencyGeneralWelcomeMessage(
            $slotFiller->registrationWelcomeHeadline(AgentConversationStateMachine::ACTION_REGISTER_AGENCY_GENERAL),
        ))
        ->toContain('registro interactivo de tu Agencia General')
        ->toContain('Agencia General')
        ->toContain('Razón social de la agencia master')
        ->toContain('RIF o número de cédula')
        ->toContain('escribe TDG');
});

it('personaliza el titulo de bienvenida segun la accion seleccionada', function (): void {
    $slotFiller = new \App\Services\PublicAiAgent\IntentSlotFiller;

    expect($slotFiller->registrationWelcomeHeadline(AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER))
        ->toBe('¡Te damos la Bienvenida al registro interactivo de tu Agencia Master! 👋')
        ->and($slotFiller->registrationWelcomeHeadline(AgentConversationStateMachine::ACTION_REGISTER_AGENCY_GENERAL))
        ->toBe('¡Te damos la Bienvenida al registro interactivo de tu Agencia General! 👋')
        ->and($slotFiller->registrationWelcomeHeadline(AgentConversationStateMachine::ACTION_REGISTER_AGENT))
        ->toBe('¡Te damos la Bienvenida al registro interactivo de tu Agente o SubAgente! 👋')
        ->and($slotFiller->registrationWelcomeHeadline(AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL))
        ->toBe('¡Te damos la Bienvenida a la cotización interactiva de tu Plan Individual! 👋')
        ->and($slotFiller->registrationWelcomeHeadline(AgentConversationStateMachine::ACTION_QUOTE_CORPORATE))
        ->toBe('¡Te damos la Bienvenida a la cotización interactiva de tu Plan Corporativo! 👋');
});

it('valida registro de agencia general con cinco campos y tax id unico', function (): void {
    $service = new \App\Services\PublicAiAgent\PublicAgentRegistrationValidationService;

    $taxId = 'J'.random_int(100000000, 999999999);

    $result = $service->validateSimplifiedAgencyGeneralPayload([
        'email' => 'general-chat-'.uniqid().'@test.invalid',
        'phone_1' => '04141234567',
        'agency_corporate_name' => 'Agencia General Chat Test',
        'master_agency_name' => 'TDG',
        'tax_id' => $taxId,
    ]);

    expect($result['errors'])->toBe([]);
});

it('valida preregistro de agencia master con cuatro campos', function (): void {
    $service = new \App\Services\PublicAiAgent\PublicAgentRegistrationValidationService;

    $result = $service->validateSimplifiedAgencyMasterPayload([
        'email' => 'master-chat-'.uniqid().'@test.invalid',
        'phone_1' => '04141234567',
        'agency_corporate_name' => 'Agencia Master Chat Test',
        'tax_id' => 'J123456789',
    ]);

    expect($result['errors'])->toBe([]);
});

it('acepta TDG como agencia master en agencia general', function (): void {
    $service = new \App\Services\PublicAiAgent\PublicAgentRegistrationValidationService;

    expect($service->isTdgMasterTerm('TDG'))->toBeTrue();

    $payload = $service->applyTdgMasterAgency([
        'master_agency_name' => 'TDG',
    ]);

    expect($payload['owner_code'])->toBe('TDG-100')
        ->and($payload['selected_master_agency_label'])->toBe('TDG-100');
});

it('busca solo agencias master para agencia general', function (): void {
    insertPublicAiAgentTestAgency([
        'id' => 99101,
        'name_corporative' => 'Master Chat Test 99101',
        'code' => 'MCT99101',
        'agency_type_id' => 1,
    ]);
    insertPublicAiAgentTestAgency([
        'id' => 99102,
        'name_corporative' => 'General Chat Test 99102',
        'code' => 'GCT99102',
        'agency_type_id' => 3,
    ]);

    $service = new \App\Services\PublicAiAgent\PublicAgentRegistrationValidationService;
    $masters = $service->findMasterAgenciesByTerm('Master Chat Test');

    expect(collect($masters)->pluck('id')->all())->toContain(99101)
        ->not->toContain(99102);

    DB::table('agencies')->whereIn('id', [99101, 99102])->delete();
});

it('encuentra agencia master en estatus por revision', function (): void {
    insertPublicAiAgentTestAgency([
        'id' => 99123,
        'name_corporative' => 'Nathaly Master',
        'code' => 'TDG-99123',
        'agency_type_id' => 1,
        'status' => 'POR REVISION',
    ]);

    $service = new \App\Services\PublicAiAgent\PublicAgentRegistrationValidationService;
    $masters = $service->findMasterAgenciesByTerm('Nathaly Master');

    expect(collect($masters)->pluck('name')->all())->toContain('Nathaly Master');

    DB::table('agencies')->where('id', 99123)->delete();
});

it('muestra lista cuando hay varias agencias master coincidentes', function (): void {
    insertPublicAiAgentTestAgency([
        'id' => 99121,
        'name_corporative' => 'Nathaly Master Norte',
        'code' => 'TDG-99121',
        'agency_type_id' => 1,
    ]);
    insertPublicAiAgentTestAgency([
        'id' => 99122,
        'name_corporative' => 'Nathaly Master Sur',
        'code' => 'TDG-99122',
        'agency_type_id' => 1,
    ]);

    $service = new \App\Services\PublicAiAgent\PublicAgentRegistrationValidationService;
    $masters = $service->findMasterAgenciesByTerm('Nathaly Master');
    $prompt = $service->masterAgencySelectionPrompt($masters);

    expect(count($masters))->toBeGreaterThanOrEqual(2)
        ->and($prompt)->toContain('Indica el número de tu agencia master')
        ->and($prompt)->toContain('Nathaly Master Norte')
        ->and($prompt)->toContain('Nathaly Master Sur');

    DB::table('agencies')->whereIn('id', [99121, 99122])->delete();
});

it('acepta correccion parcial del nombre de agencia master en registro general', function (): void {
    $slotFiller = new \App\Services\PublicAiAgent\IntentSlotFiller;

    $payload = $slotFiller->mergePartialAgencyGeneralCorrection([
        'agency_corporate_name' => 'Mi Agencia General',
        'email' => 'general@test.invalid',
        'phone_1' => '04141234567',
        'tax_id' => 'J123456789',
        'master_agency_name' => 'Nombre Incorrecto',
    ], 'Nathaly Master');

    expect($payload['master_agency_name'])->toBe('Nathaly Master')
        ->and($payload)->not->toHaveKey('selected_master_agency_id');
});

it('completa registro real de agencia general con TDG e informa envio por whatsapp y correo', function (): void {
    if (! Schema::hasTable('agencies') || ! Schema::hasTable('users')) {
        $this->markTestSkipped('Tablas agencies/users no disponibles.');
    }

    Bus::fake();

    $orchestrator = app(AgentOrchestrator::class);
    $session = ChatSession::startPublic('127.0.0.1', 'Pest');
    $email = 'general-chat-'.uniqid().'@test.invalid';
    $taxId = 'J'.random_int(100000000, 999999999);

    $orchestrator->processUserMessage(
        $session,
        'Seleccioné: Registro Agencia General',
        AgentConversationStateMachine::ACTION_REGISTER_AGENCY_GENERAL,
    );

    $summary = $orchestrator->processUserMessage(
        $session,
        "Mi Agencia General Chat, TDG, {$taxId}, 04141234567, {$email}",
        AgentConversationStateMachine::ACTION_REGISTER_AGENCY_GENERAL,
    );

    expect($summary['reply'])->toContain('Agencia General')
        ->and($summary['reply'])->toContain('Agencia master: TDG')
        ->and($summary['reply'])->toContain($taxId);

    $registered = $orchestrator->processUserMessage(
        $session,
        'si',
        AgentConversationStateMachine::ACTION_REGISTER_AGENCY_GENERAL,
    );

    expect($registered['reply'])
        ->toContain('Te acabamos de enviar un mensaje por WhatsApp')
        ->toContain($email)
        ->toContain('¿Recibiste la información por alguna de las dos vías?')
        ->toContain('ninguno de los dos canales');

    $agency = Agency::query()->where('email', $email)->first();
    $user = User::query()->where('email', $email)->first();

    expect($agency)->not->toBeNull()
        ->and($user)->not->toBeNull()
        ->and((int) $agency->agency_type_id)->toBe(3)
        ->and($agency->owner_code)->toBe('TDG-100')
        ->and((bool) $user->is_agency)->toBeTrue()
        ->and($user->agency_type)->toBe('GENERAL');

    Bus::assertDispatched(SendCartaBienvenidaAgenteAgenciaTwo::class);
    Bus::assertDispatched(SendChatAgencyGeneralRegistrationWhatsAppJob::class);

    if ($user !== null) {
        User::query()->where('id', $user->id)->delete();
    }

    if ($agency !== null) {
        Agency::query()->where('id', $agency->id)->delete();
    }
});

it('completa registro real de agencia master e informa envio por whatsapp y correo', function (): void {
    if (! Schema::hasTable('agencies') || ! Schema::hasTable('users')) {
        $this->markTestSkipped('Tablas agencies/users no disponibles.');
    }

    Bus::fake();

    $orchestrator = app(AgentOrchestrator::class);
    $session = ChatSession::startPublic('127.0.0.1', 'Pest');
    $email = 'master-chat-'.uniqid().'@test.invalid';

    $orchestrator->processUserMessage(
        $session,
        'Seleccioné: Registro Agencia Master',
        AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER,
    );

    $summary = $orchestrator->processUserMessage(
        $session,
        "Agencia Master Chat Test, J123456789, 04149876543, {$email}",
        AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER,
    );

    expect($summary['reply'])->toContain('Agencia Master')
        ->toContain('Razón social: Agencia Master Chat Test')
        ->toContain('J123456789');

    $registered = $orchestrator->processUserMessage(
        $session,
        'si',
        AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER,
    );

    expect($registered['reply'])
        ->toContain('Te acabamos de enviar un mensaje por WhatsApp')
        ->toContain($email)
        ->toContain('¿Recibiste la información por alguna de las dos vías?')
        ->toContain('ninguno de los dos canales')
        ->not->toContain('¿Quieres recibir por WhatsApp la información privada');

    $agency = Agency::query()->where('email', $email)->first();
    $user = User::query()->where('email', $email)->first();

    expect($agency)->not->toBeNull()
        ->and($user)->not->toBeNull()
        ->and((int) $agency->agency_type_id)->toBe(1)
        ->and($agency->owner_code)->toBe($agency->code)
        ->and((bool) $user->is_agency)->toBeTrue()
        ->and($user->agency_type)->toBe('MASTER');

    Bus::assertDispatched(SendCartaBienvenidaAgenteAgenciaTwo::class);
    Bus::assertDispatched(SendChatAgencyMasterRegistrationWhatsAppJob::class);

    if ($user !== null) {
        User::query()->where('id', $user->id)->delete();
    }

    if ($agency !== null) {
        Agency::query()->where('id', $agency->id)->delete();
    }
});

it('ofrece entrega por chat si agencia master no recibio mensajes', function (): void {
    $slotFiller = new \App\Services\PublicAiAgent\IntentSlotFiller;

    expect($slotFiller->agencyMasterRegistrationDeliveredMessage('master@test.invalid', '+584141234567'))
        ->toContain('WhatsApp')
        ->toContain('master@test.invalid')
        ->toContain('¿Recibiste la información por alguna de las dos vías?')
        ->toContain('Responde si si ya la recibiste correctamente')
        ->toContain('ninguno de los dos canales');

    expect($slotFiller->chatAgentRegistrationChatCredentialsOfferMessage())
        ->toContain('aquí en el chat')
        ->toContain('carta de bienvenida')
        ->toContain('Responde si');
});

it('ofrece otra accion cuando agencia master confirma haber recibido la informacion y finaliza si responde no', function (): void {
    if (! Schema::hasTable('agencies') || ! Schema::hasTable('users')) {
        $this->markTestSkipped('Tablas agencies/users no disponibles.');
    }

    Bus::fake();

    $orchestrator = app(AgentOrchestrator::class);
    $session = ChatSession::startPublic('127.0.0.1', 'Pest');
    $email = 'master-received-'.uniqid().'@test.invalid';

    $orchestrator->processUserMessage(
        $session,
        'Seleccioné: Registro Agencia Master',
        AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER,
    );

    $orchestrator->processUserMessage(
        $session,
        "Agencia Master Recibida, J123456789, 04149876543, {$email}",
        AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER,
    );

    $orchestrator->processUserMessage(
        $session,
        'si',
        AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER,
    );

    $anotherActionOffer = $orchestrator->processUserMessage(
        $session,
        'si',
        AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER,
    );

    expect($anotherActionOffer['reply'])
        ->toContain('Nos alegra que hayas recibido la información correctamente')
        ->toContain('¿Deseas realizar alguna otra acción')
        ->toContain('Responde si para elegir otra acción, o no para finalizar');

    $farewell = $orchestrator->processUserMessage(
        $session,
        'no',
        AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER,
    );

    expect($farewell['reply'])
        ->toContain('¡Gracias por usar Integracorp!')
        ->toContain('¡Hasta pronto!');

    $agency = Agency::query()->where('email', $email)->first();

    if ($agency !== null) {
        Agency::query()->where('id', $agency->id)->delete();
    }

    User::query()->where('email', $email)->delete();
});

it('ofrece entrega por chat cuando agencia master no recibio la informacion', function (): void {
    if (! Schema::hasTable('agencies') || ! Schema::hasTable('users')) {
        $this->markTestSkipped('Tablas agencies/users no disponibles.');
    }

    Bus::fake();

    $orchestrator = app(AgentOrchestrator::class);
    $session = ChatSession::startPublic('127.0.0.1', 'Pest');
    $email = 'master-not-received-'.uniqid().'@test.invalid';

    $orchestrator->processUserMessage(
        $session,
        'Seleccioné: Registro Agencia Master',
        AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER,
    );

    $orchestrator->processUserMessage(
        $session,
        "Agencia Master No Recibida, J123456789, 04149876543, {$email}",
        AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER,
    );

    $orchestrator->processUserMessage(
        $session,
        'si',
        AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER,
    );

    $chatOffer = $orchestrator->processUserMessage(
        $session,
        'no',
        AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER,
    );

    expect($chatOffer['reply'])
        ->toContain('aquí en el chat')
        ->toContain('carta de bienvenida')
        ->toContain('Responde si');

    $agency = Agency::query()->where('email', $email)->first();

    if ($agency !== null) {
        Agency::query()->where('id', $agency->id)->delete();
    }

    User::query()->where('email', $email)->delete();
});

it('continua registro de agencia master cuando faltan campos y pide solo el telefono', function (): void {
    if (! Schema::hasTable('agencies') || ! Schema::hasTable('users')) {
        $this->markTestSkipped('Tablas agencies/users no disponibles.');
    }

    Schema::dropIfExists('cities');
    Schema::dropIfExists('states');
    Schema::dropIfExists('countries');

    Schema::create('countries', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
    Schema::create('states', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('country_id');
        $table->string('definition')->nullable();
    });
    Schema::create('cities', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('country_id');
        $table->unsignedBigInteger('state_id');
        $table->string('definition')->nullable();
    });

    DB::table('countries')->insert(['id' => 1, 'name' => 'Venezuela']);
    DB::table('states')->insert(['id' => 10, 'country_id' => 1, 'definition' => 'Miranda']);
    DB::table('cities')->insert(['id' => 100, 'country_id' => 1, 'state_id' => 10, 'definition' => 'Caracas']);

    $orchestrator = app(AgentOrchestrator::class);
    $session = ChatSession::startPublic('127.0.0.1', 'Pest');
    $email = 'master-partial-'.uniqid().'@test.invalid';

    $orchestrator->processUserMessage(
        $session,
        'Seleccioné: Registro Agencia Master',
        AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER,
    );

    $phonePrompt = $orchestrator->processUserMessage(
        $session,
        "Gus Agencia, J12345678, {$email}",
        AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER,
    );

    expect($phonePrompt['reply'])->toContain('teléfono')
        ->not->toContain('Indícame la razón social');

    $summary = $orchestrator->processUserMessage(
        $session,
        '04141234567',
        AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER,
    );

    expect($summary['reply'])->toContain('Razón social: Gus Agencia')
        ->toContain($email);
});
