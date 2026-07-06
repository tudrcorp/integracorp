<?php

declare(strict_types=1);

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\PublicAiAgent\AgentConversationStateMachine;
use App\Services\PublicAiAgent\AgentOrchestrator;
use App\Services\PublicAiAgent\ChatAgencyMasterRegistrationService;
use App\Services\PublicAiAgent\ChatAgentRegistrationService;
use App\Services\PublicAiAgent\IntentSlotFiller;
use App\Services\PublicAiAgent\ProspectAgentRegistrationService;
use App\Services\PublicAiAgent\PublicAgentRegistrationValidationService;
use App\Services\PublicAiAgent\PublicPlanCatalogService;
use App\Services\PublicAiAgent\PublicQuoteSimulationService;
use App\Support\AffiliationAffiliateFeeCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    ensureSqliteInMemoryDatabaseOrSkip();

    Schema::dropIfExists('chat_messages');
    Schema::dropIfExists('chat_sessions');
    Schema::dropIfExists('fees');
    Schema::dropIfExists('age_ranges');
    Schema::dropIfExists('coverages');
    Schema::dropIfExists('plans');

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

    Schema::create('plans', function (Blueprint $table): void {
        $table->id();
        $table->string('description')->nullable();
        $table->string('type')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });

    Schema::create('coverages', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('plan_id')->nullable();
        $table->decimal('price', 12, 2)->default(0);
        $table->timestamps();
    });

    Schema::create('age_ranges', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('plan_id')->nullable();
        $table->unsignedBigInteger('coverage_id')->nullable();
        $table->string('range')->nullable();
        $table->integer('age_init')->nullable();
        $table->integer('age_end')->nullable();
        $table->timestamps();
    });

    Schema::create('fees', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('age_range_id')->nullable();
        $table->unsignedBigInteger('coverage_id')->nullable();
        $table->decimal('price', 12, 2)->default(0);
        $table->string('range')->nullable();
        $table->timestamps();
    });

    DB::table('plans')->insert([
        ['id' => 1, 'description' => 'Plan Inicial', 'type' => 'BASICO', 'status' => 'ACTIVO'],
        ['id' => 2, 'description' => 'Plan Ideal', 'type' => 'BASICO', 'status' => 'ACTIVO'],
    ]);
    DB::table('age_ranges')->insert([
        ['id' => 1, 'plan_id' => 1, 'coverage_id' => null, 'range' => '0 a 120', 'age_init' => 0, 'age_end' => 120],
    ]);
    DB::table('coverages')->insert([
        ['id' => 22, 'plan_id' => 2, 'price' => 5000],
    ]);
    DB::table('age_ranges')->insert([
        ['id' => 2, 'plan_id' => 2, 'coverage_id' => 22, 'range' => '18 a 59', 'age_init' => 18, 'age_end' => 59],
    ]);
    DB::table('fees')->insert([
        ['id' => 102, 'age_range_id' => 2, 'coverage_id' => 22, 'price' => 240, 'range' => '18 a 59'],
    ]);
});

function makeAgentOrchestrator(): AgentOrchestrator
{
    return new AgentOrchestrator(
        stateMachine: new AgentConversationStateMachine,
        intentSlotFiller: new IntentSlotFiller,
        prospectAgentRegistrationService: new ProspectAgentRegistrationService,
        publicQuoteSimulationService: new PublicQuoteSimulationService(new AffiliationAffiliateFeeCalculator),
        registrationValidationService: new PublicAgentRegistrationValidationService,
        chatAgentRegistrationService: new ChatAgentRegistrationService,
        chatAgencyMasterRegistrationService: new ChatAgencyMasterRegistrationService,
        chatAgencyGeneralRegistrationService: new \App\Services\PublicAiAgent\ChatAgencyGeneralRegistrationService,
        publicPlanCatalogService: new PublicPlanCatalogService,
        publicPlanBenefitsService: new \App\Services\PublicAiAgent\PublicPlanBenefitsService,
        chatIndividualQuoteService: new \App\Services\PublicAiAgent\ChatIndividualQuoteService,
    );
}

it('aplica slot filling y confirmación antes de calcular cotización', function (): void {
    $orchestrator = makeAgentOrchestrator();

    $session = ChatSession::startPublic('127.0.0.1', 'Pest');
    $result1 = $orchestrator->processUserMessage($session, 'Quiero cotizar un plan de salud.');
    $result2 = $orchestrator->processUserMessage($session, 'Plan ideal, cobertura 22, 45 años.');
    $result3 = $orchestrator->processUserMessage($session, 'si');
    $session->refresh();

    expect($result1['reply'])->toContain('plan')
        ->and($result2['reply'])->toContain('Voy a simular la cotización')
        ->and($result3['reply'])->toContain('Cotización calculada')
        ->and($result3['intent'])->toBe(AgentConversationStateMachine::INTENT_COTIZACION)
        ->and($session->current_state)->toBe(AgentConversationStateMachine::STATE_CONFIRMACION)
        ->and(ChatMessage::query()->count('*'))->toBe(7);
});

it('usa la accion seleccionada para enrutar el flujo guiado', function (): void {
    $orchestrator = makeAgentOrchestrator();

    $session = ChatSession::startPublic('127.0.0.1', 'Pest');
    $result = $orchestrator->processUserMessage(
        $session,
        'Hola',
        AgentConversationStateMachine::ACTION_QUOTE_CORPORATE,
    );

    expect($result['intent'])->toBe(AgentConversationStateMachine::INTENT_COTIZACION)
        ->and($result['reply'])->toContain('plan corporativo');
});

it('muestra bienvenida al seleccionar registro de agente o subagente', function (): void {
    $orchestrator = makeAgentOrchestrator();

    $session = ChatSession::startPublic('127.0.0.1', 'Pest');
    $result = $orchestrator->processUserMessage(
        $session,
        'Seleccioné: Registro de Agente',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($result['intent'])->toBe(AgentConversationStateMachine::INTENT_PREREGISTRO)
        ->and($result['reply'])->toContain('¡Te damos la Bienvenida al registro interactivo de tu Agente o SubAgente!')
        ->and($result['reply'])->toContain('María Pérez, v-16007868, 05/01/1984, 04141234567, maria@correo.com, 1, TDG');
});

it('muestra resumen de validacion legible tras enviar datos de agente', function (): void {
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
        $table->string('definition');
    });
    Schema::create('cities', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('country_id');
        $table->unsignedBigInteger('state_id');
        $table->string('definition');
    });

    DB::table('countries')->insert(['id' => 1, 'name' => 'Venezuela']);
    DB::table('states')->insert(['id' => 10, 'country_id' => 1, 'definition' => 'Miranda']);
    DB::table('cities')->insert(['id' => 100, 'country_id' => 1, 'state_id' => 10, 'definition' => 'Caracas']);

    if (! Schema::hasTable('agencies')) {
        Schema::create('agencies', function (Blueprint $table): void {
            $table->id();
            $table->string('name_corporative')->nullable();
            $table->string('code')->nullable();
            $table->string('rif')->nullable();
        });
    }

    insertPublicAiAgentTestAgency([
        'id' => 99003,
        'name_corporative' => 'Agencia Chat Single 99003',
        'code' => 'ACS99003',
    ]);

    $orchestrator = makeAgentOrchestrator();

    $session = ChatSession::startPublic('127.0.0.1', 'Pest');

    $orchestrator->processUserMessage(
        $session,
        'Seleccioné: Registro de Agente',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    $validation = $orchestrator->processUserMessage(
        $session,
        'María Pérez, v-16007868, 05/01/1984, 04141234567, maria-chat-single@correo.com, 1, Agencia Chat Single 99003',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    DB::table('agencies')->where('id', 99003)->delete();

    expect($validation['reply'])->toContain('revisa que tus datos sean correctos')
        ->and($validation['reply'])->toContain('Nombre y apellido: María Pérez')
        ->and($validation['reply'])->toContain('Cédula o RIF: V-16007868')
        ->and($validation['reply'])->toContain('Fecha de nacimiento: 05/01/1984')
        ->and($validation['reply'])->toContain('Teléfono: 04141234567')
        ->and($validation['reply'])->toContain('Correo electrónico: maria-chat-single@correo.com')
        ->and($validation['reply'])->toContain('Tipo de perfil: Agente (1)')
        ->and($validation['reply'])->toContain('Agencia Chat Single 99003')
        ->and($validation['reply'])->toContain('responde si');
});

it('asigna TuDrGroup TDG-100 cuando el agente escribe TDG exacto', function (): void {
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
        $table->string('definition');
    });
    Schema::create('cities', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('country_id');
        $table->unsignedBigInteger('state_id');
        $table->string('definition');
    });

    DB::table('countries')->insert(['id' => 1, 'name' => 'Venezuela']);
    DB::table('states')->insert(['id' => 10, 'country_id' => 1, 'definition' => 'Miranda']);
    DB::table('cities')->insert(['id' => 100, 'country_id' => 1, 'state_id' => 10, 'definition' => 'Caracas']);

    $orchestrator = makeAgentOrchestrator();
    $session = ChatSession::startPublic('127.0.0.1', 'Pest');
    $email = 'agente-tdg-'.uniqid().'@test.invalid';

    $orchestrator->processUserMessage(
        $session,
        'Seleccioné: Registro de Agente',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    $validation = $orchestrator->processUserMessage(
        $session,
        "María Pérez, v-16007868, 05/01/1984, 04141234567, {$email}, 1, TDG",
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($validation['reply'])->toContain('revisa que tus datos sean correctos')
        ->and($validation['reply'])->toContain('TuDrGroup — TDG-100')
        ->and($validation['reply'])->toContain('estructura comercial de TuDrGroup')
        ->not->toContain('Indica el número de tu agencia');
});

it('pide seleccionar agencia cuando hay varias coincidencias y luego confirma', function (): void {
    if (! Schema::hasTable('agencies')) {
        Schema::create('agencies', function (Blueprint $table): void {
            $table->id();
            $table->string('name_corporative')->nullable();
            $table->string('code')->nullable();
            $table->string('rif')->nullable();
        });
    }

    insertPublicAiAgentTestAgency([
        'id' => 99004,
        'name_corporative' => 'Agencia Chat Multi A 99004',
        'code' => 'ACM99004',
    ]);
    insertPublicAiAgentTestAgency([
        'id' => 99005,
        'name_corporative' => 'Agencia Chat Multi B 99005',
        'code' => 'ACM99005',
    ]);

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
        $table->string('definition');
    });
    Schema::create('cities', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('country_id');
        $table->unsignedBigInteger('state_id');
        $table->string('definition');
    });

    DB::table('countries')->insert(['id' => 1, 'name' => 'Venezuela']);
    DB::table('states')->insert(['id' => 10, 'country_id' => 1, 'definition' => 'Miranda']);
    DB::table('cities')->insert(['id' => 100, 'country_id' => 1, 'state_id' => 10, 'definition' => 'Caracas']);

    $orchestrator = makeAgentOrchestrator();
    $session = ChatSession::startPublic('127.0.0.1', 'Pest');

    $orchestrator->processUserMessage(
        $session,
        'Seleccioné: Registro de Agente',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    $selection = $orchestrator->processUserMessage(
        $session,
        'María Pérez, v-16007868, 05/01/1984, 04141234567, maria-chat@correo.com, 1, Agencia Chat Multi',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($selection['reply'])->toContain('Encontré varias agencias');

    $confirmation = $orchestrator->processUserMessage(
        $session,
        '2',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($confirmation['reply'])->toContain('revisa que tus datos sean correctos')
        ->and($confirmation['reply'])->toContain('Agencia Chat Multi B 99005');

    DB::table('agencies')->whereIn('id', [99004, 99005])->delete();
});

it('muestra sugerencias de agencia cuando el usuario escribe coincidencia parcial', function (): void {
    if (! Schema::hasTable('agencies')) {
        Schema::create('agencies', function (Blueprint $table): void {
            $table->id();
            $table->string('name_corporative')->nullable();
            $table->string('code')->nullable();
            $table->string('rif')->nullable();
        });
    }

    insertPublicAiAgentTestAgency([
        'id' => 99012,
        'name_corporative' => 'Corporación ABP Chat Test',
        'code' => 'ABP-99012',
    ]);
    insertPublicAiAgentTestAgency([
        'id' => 99013,
        'name_corporative' => 'Grupo ABP Chat Sur Test',
        'code' => 'ABP-99013',
    ]);

    $orchestrator = makeAgentOrchestrator();
    $session = ChatSession::startPublic('127.0.0.1', 'Pest');

    $orchestrator->processUserMessage(
        $session,
        'Seleccioné: Registro de Agente',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    $selection = $orchestrator->processUserMessage(
        $session,
        'María Pérez, v-16007868, 05/01/1984, 04141234567, maria-abp-chat@correo.com, 1, ABp',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($selection['reply'])->toContain('Encontré varias agencias')
        ->and($selection['reply'])->toContain('Corporación ABP Chat Test')
        ->and($selection['reply'])->toContain('Grupo ABP Chat Sur Test');

    DB::table('agencies')->whereIn('id', [99012, 99013])->delete();
});

it('permite corregir la agencia enviando solo el nuevo termino tras no encontrar resultados', function (): void {
    if (! Schema::hasTable('agencies')) {
        Schema::create('agencies', function (Blueprint $table): void {
            $table->id();
            $table->string('name_corporative')->nullable();
            $table->string('code')->nullable();
            $table->string('rif')->nullable();
        });
    }

    insertPublicAiAgentTestAgency([
        'id' => 99014,
        'name_corporative' => 'Agencia VMG Chat Test',
        'code' => 'VMG-99014',
    ]);

    $orchestrator = makeAgentOrchestrator();
    $session = ChatSession::startPublic('127.0.0.1', 'Pest');

    $orchestrator->processUserMessage(
        $session,
        'Seleccioné: Registro de Agente',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    $notFound = $orchestrator->processUserMessage(
        $session,
        'María Pérez, v-16007868, 05/01/1984, 04141234567, maria-vg-retry@correo.com, 1, vg',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($notFound['reply'])->toContain('No encontramos agencias')
        ->and($notFound['reply'])->toContain('vg');

    $selection = $orchestrator->processUserMessage(
        $session,
        'VMG',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($selection['reply'])->toContain('Agencia VMG Chat Test')
        ->and($selection['reply'])->not->toContain('No encontramos agencias que contengan "vg"');

    DB::table('agencies')->where('id', 99014)->delete();
});

it('permite corregir solo el correo tras error de duplicado', function (): void {
    if (! Schema::hasTable('users')) {
        $this->markTestSkipped('Tabla users no disponible.');
    }

    if (! Schema::hasTable('agencies')) {
        Schema::create('agencies', function (Blueprint $table): void {
            $table->id();
            $table->string('name_corporative')->nullable();
            $table->string('code')->nullable();
            $table->string('rif')->nullable();
        });
    }

    insertPublicAiAgentTestAgency([
        'id' => 99015,
        'name_corporative' => 'Agencia TDG Email Retry Test',
        'code' => 'TDG-99015',
    ]);

    $duplicateEmail = 'chat-dup-email-'.uniqid().'@test.invalid';
    $newEmail = 'chat-new-email-'.uniqid().'@test.invalid';

    User::query()->create([
        'name' => 'Usuario Existente',
        'email' => $duplicateEmail,
        'password' => bcrypt('password'),
        'phone' => '04149999999',
    ]);

    $orchestrator = makeAgentOrchestrator();
    $session = ChatSession::startPublic('127.0.0.1', 'Pest');

    $orchestrator->processUserMessage(
        $session,
        'Seleccioné: Registro de Agente',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    $duplicateError = $orchestrator->processUserMessage(
        $session,
        'María Pérez, v-16007868, 05/01/1984, 04141234567, '.$duplicateEmail.', 1, TDG-99015',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($duplicateError['reply'])->toContain('correo electrónico ya está registrado');

    $confirmation = $orchestrator->processUserMessage(
        $session,
        $newEmail,
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($confirmation['reply'])->toContain('revisa que tus datos sean correctos')
        ->and($confirmation['reply'])->toContain($newEmail);

    User::query()->where('email', $duplicateEmail)->delete();
    DB::table('agencies')->where('id', 99015)->delete();
});

it('guia cotizacion individual con beneficios y palabra cotizar', function (): void {
    $quoteService = Mockery::mock(\App\Services\PublicAiAgent\ChatIndividualQuoteService::class);
    $quoteService->shouldReceive('resolveSingleAgeRangeIdForPlan')->with(1)->andReturn(1);
    $quoteService->shouldReceive('planLabel')->with(1)->andReturn('Plan Inicial');
    $quoteService->shouldReceive('register')->once()->andReturn([
        'success' => true,
        'message' => 'Cotización individual generada exitosamente.',
        'data' => ['code' => 'COT-IND-00099'],
    ]);

    $orchestrator = new AgentOrchestrator(
        stateMachine: new AgentConversationStateMachine,
        intentSlotFiller: new IntentSlotFiller,
        prospectAgentRegistrationService: new ProspectAgentRegistrationService,
        publicQuoteSimulationService: new PublicQuoteSimulationService(new AffiliationAffiliateFeeCalculator),
        registrationValidationService: new PublicAgentRegistrationValidationService,
        chatAgentRegistrationService: new ChatAgentRegistrationService,
        chatAgencyMasterRegistrationService: new ChatAgencyMasterRegistrationService,
        chatAgencyGeneralRegistrationService: new \App\Services\PublicAiAgent\ChatAgencyGeneralRegistrationService,
        publicPlanCatalogService: new PublicPlanCatalogService,
        publicPlanBenefitsService: new \App\Services\PublicAiAgent\PublicPlanBenefitsService,
        chatIndividualQuoteService: $quoteService,
    );

    $session = ChatSession::startPublic('127.0.0.1', 'Pest');

    $catalog = $orchestrator->processUserMessage(
        $session,
        'Seleccioné: Cotización plan individual',
        AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL,
    );

    expect($catalog['reply'])
        ->toContain('Plan Individual')
        ->toContain('¿Deseas cotizar o necesitas conocer los beneficios');

    $benefits = $orchestrator->processUserMessage($session, '1 beneficios', AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL);

    expect($benefits['reply'])
        ->toContain('Plan Inicial')
        ->toContain('cotizar');

    $intro = $orchestrator->processUserMessage($session, 'cotizar', AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL);

    expect($intro['reply'])->toContain('Vamos a cotizar');

    $entry = $orchestrator->processUserMessage($session, '1, 10', AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL);

    expect($entry['reply'])->toContain('nombre y apellido');

    $name = $orchestrator->processUserMessage($session, 'Juan Pérez', AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL);
    $agent = $orchestrator->processUserMessage($session, 'María Agente', AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL);
    $confirm = $orchestrator->processUserMessage($session, 'si', AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL);

    expect($name['reply'])->toContain('agente')
        ->and($agent['reply'])->toContain('Resumen de tu cotización')
        ->and($confirm['reply'])->toContain('COT-IND-00099');
});

it('requiere la palabra multiple para cotizacion con varios planes', function (): void {
    $makeAgeRange = function (int $id, int $planId, string $range): \App\Models\AgeRange {
        $ageRange = new \App\Models\AgeRange;
        $ageRange->id = $id;
        $ageRange->plan_id = $planId;
        $ageRange->range = $range;
        $ageRange->age_init = 0;
        $ageRange->age_end = 120;

        return $ageRange;
    };

    $quoteService = Mockery::mock(\App\Services\PublicAiAgent\ChatIndividualQuoteService::class);
    $quoteService->shouldReceive('resolveAgeRangeForPlanAndAge')->with(2, 34)->andReturn($makeAgeRange(3, 2, '46 a 75'));
    $quoteService->shouldReceive('resolveAgeRangeForPlanAndAge')->with(3, 45)->andReturn($makeAgeRange(6, 3, '31 a 65'));
    $quoteService->shouldReceive('planLabel')->with(2)->andReturn('Plan Ideal');
    $quoteService->shouldReceive('planLabel')->with(3)->andReturn('Plan Especial');
    $quoteService->shouldReceive('register')->once()->andReturn([
        'success' => true,
        'message' => 'Cotización individual generada exitosamente.',
        'data' => ['code' => 'COT-IND-00100', 'plan' => 'CM'],
    ]);

    $orchestrator = new AgentOrchestrator(
        stateMachine: new AgentConversationStateMachine,
        intentSlotFiller: new IntentSlotFiller,
        prospectAgentRegistrationService: new ProspectAgentRegistrationService,
        publicQuoteSimulationService: new PublicQuoteSimulationService(new AffiliationAffiliateFeeCalculator),
        registrationValidationService: new PublicAgentRegistrationValidationService,
        chatAgentRegistrationService: new ChatAgentRegistrationService,
        chatAgencyMasterRegistrationService: new ChatAgencyMasterRegistrationService,
        chatAgencyGeneralRegistrationService: new \App\Services\PublicAiAgent\ChatAgencyGeneralRegistrationService,
        publicPlanCatalogService: new PublicPlanCatalogService,
        publicPlanBenefitsService: new \App\Services\PublicAiAgent\PublicPlanBenefitsService,
        chatIndividualQuoteService: $quoteService,
    );

    $session = ChatSession::startPublic('127.0.0.1', 'Pest');

    $orchestrator->processUserMessage(
        $session,
        'Seleccioné: Cotización plan individual',
        AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL,
    );
    $orchestrator->processUserMessage($session, 'cotizar', AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL);

    $multiple = $orchestrator->processUserMessage($session, 'multiple', AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL);

    expect($multiple['reply'])->toContain('múltiple activado');

    $orchestrator->processUserMessage($session, '2, 34, 1', AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL);
    $orchestrator->processUserMessage($session, '3, 45, 4', AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL);
    $orchestrator->processUserMessage($session, 'no', AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL);
    $orchestrator->processUserMessage($session, 'Cliente Test', AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL);
    $orchestrator->processUserMessage($session, 'Agente Test', AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL);

    $confirm = $orchestrator->processUserMessage($session, 'si', AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL);

    expect($confirm['reply'])->toContain('COT-IND-00100');
});
