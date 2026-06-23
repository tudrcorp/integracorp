<?php

declare(strict_types=1);

use App\Services\PublicAiAgent\AgentConversationStateMachine;
use App\Services\PublicAiAgent\IntentSlotFiller;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

it('extrae datos de cotización desde texto libre', function (): void {
    $slotFiller = new IntentSlotFiller;

    $payload = $slotFiller->mergePayloadFromMessage(
        AgentConversationStateMachine::INTENT_COTIZACION,
        [],
        'Quiero plan ideal cobertura 22 para 45 años y 33 años.',
    );

    expect($payload['plan_id'])->toBe(2)
        ->and($payload['coverage_id'])->toBe(22)
        ->and($payload['members'])->toBeArray()
        ->and(count($payload['members']))->toBe(2);
});

it('detecta cuando faltan campos en preregistro', function (): void {
    $slotFiller = new IntentSlotFiller;

    $missing = $slotFiller->missingRequiredFields(
        AgentConversationStateMachine::INTENT_PREREGISTRO,
        ['name' => 'Carlos'],
    );

    expect($missing)->toContain('email')
        ->toContain('phone_1')
        ->toContain('country_id');
});

it('reconoce mensajes de confirmación', function (): void {
    $slotFiller = new IntentSlotFiller;

    expect($slotFiller->isConfirmation('si'))->toBeTrue()
        ->and($slotFiller->isConfirmation('s'))->toBeTrue()
        ->and($slotFiller->isConfirmation('todavía no'))->toBeFalse();
});

it('parsea registro de agente con formato separado por comas', function (): void {
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

    $slotFiller = new IntentSlotFiller;

    $payload = $slotFiller->mergePayloadFromMessage(
        AgentConversationStateMachine::INTENT_PREREGISTRO,
        [],
        'María Pérez, 04141234567, maria@correo.com, 1, TDG',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($payload['name'])->toBe('María Pérez')
        ->and($payload['phone_1'])->toBe('04141234567')
        ->and($payload['email'])->toBe('maria@correo.com')
        ->and($payload['classification'])->toBe('agent')
        ->and($payload['agency_name'])->toBe('TDG')
        ->and($payload['country_id'])->toBe(1)
        ->and($payload['state_id'])->toBe(10)
        ->and($payload['city_id'])->toBe(100);
});

it('actualiza la agencia cuando el usuario envia solo un nuevo termino de busqueda', function (): void {
    $slotFiller = new IntentSlotFiller;

    $existingPayload = [
        'name' => 'María Pérez',
        'phone_1' => '04141234567',
        'email' => 'maria@correo.com',
        'classification' => 'agent',
        'agency_name' => 'vg',
    ];

    $payload = $slotFiller->mergePayloadFromMessage(
        AgentConversationStateMachine::INTENT_PREREGISTRO,
        $existingPayload,
        'VMG',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($payload['agency_name'])->toBe('VMG')
        ->and($payload['name'])->toBe('María Pérez')
        ->and($payload['email'])->toBe('maria@correo.com')
        ->and($payload['phone_1'])->toBe('04141234567')
        ->and($payload['classification'])->toBe('agent');
});

it('actualiza solo el correo cuando el usuario envia un email nuevo', function (): void {
    $slotFiller = new IntentSlotFiller;

    $existingPayload = [
        'name' => 'María Pérez',
        'phone_1' => '04141234567',
        'email' => 'duplicado@correo.com',
        'classification' => 'agent',
        'agency_name' => 'TDG',
    ];

    $payload = $slotFiller->mergePayloadFromMessage(
        AgentConversationStateMachine::INTENT_PREREGISTRO,
        $existingPayload,
        'maria.nueva@correo.com',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($payload['email'])->toBe('maria.nueva@correo.com')
        ->and($payload['name'])->toBe('María Pérez')
        ->and($payload['phone_1'])->toBe('04141234567');
});

it('actualiza solo el telefono cuando el usuario envia un numero nuevo', function (): void {
    $slotFiller = new IntentSlotFiller;

    $existingPayload = [
        'name' => 'María Pérez',
        'phone_1' => '04141111111',
        'email' => 'maria@correo.com',
        'classification' => 'agent',
        'agency_name' => 'TDG',
    ];

    $payload = $slotFiller->mergePayloadFromMessage(
        AgentConversationStateMachine::INTENT_PREREGISTRO,
        $existingPayload,
        '04149998888',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($payload['phone_1'])->toBe('04149998888')
        ->and($payload['email'])->toBe('maria@correo.com');
});

it('actualiza solo el nombre cuando el usuario envia un nombre nuevo', function (): void {
    $slotFiller = new IntentSlotFiller;

    $existingPayload = [
        'name' => 'Ana',
        'phone_1' => '04141234567',
        'email' => 'maria@correo.com',
        'classification' => 'agent',
        'agency_name' => 'TDG',
    ];

    $payload = $slotFiller->mergePayloadFromMessage(
        AgentConversationStateMachine::INTENT_PREREGISTRO,
        $existingPayload,
        'Ana María López',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($payload['name'])->toBe('Ana María López');
});

it('actualiza solo el tipo de perfil cuando el usuario envia 1 o 2', function (): void {
    $slotFiller = new IntentSlotFiller;

    $existingPayload = [
        'name' => 'María Pérez',
        'phone_1' => '04141234567',
        'email' => 'maria@correo.com',
        'classification' => 'agent',
        'agency_name' => 'TDG',
    ];

    $payload = $slotFiller->mergePayloadFromMessage(
        AgentConversationStateMachine::INTENT_PREREGISTRO,
        $existingPayload,
        '2',
        AgentConversationStateMachine::ACTION_REGISTER_AGENT,
    );

    expect($payload['classification'])->toBe('subagent');
});

it('parsea registro de agencia master con tres campos detectando email y rif', function (): void {
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

    $slotFiller = new IntentSlotFiller;

    $payload = $slotFiller->mergePayloadFromMessage(
        AgentConversationStateMachine::INTENT_PREREGISTRO,
        [],
        'Gus Agencia, J12345678, gustavoalberto.camachop@gmail.com',
        AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER,
    );

    expect($payload['agency_corporate_name'])->toBe('Gus Agencia')
        ->and($payload['tax_id'])->toBe('J12345678')
        ->and($payload['email'])->toBe('gustavoalberto.camachop@gmail.com')
        ->and($slotFiller->missingRequiredFields(
            AgentConversationStateMachine::INTENT_PREREGISTRO,
            $payload,
            AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER,
        ))->toBe(['phone_1']);
});

it('acepta razon social suelta en correccion parcial de agencia master', function (): void {
    $slotFiller = new IntentSlotFiller;

    $payload = $slotFiller->mergePartialAgencyMasterCorrection([], 'Gus Agencia');

    expect($payload['agency_corporate_name'])->toBe('Gus Agencia');
});
