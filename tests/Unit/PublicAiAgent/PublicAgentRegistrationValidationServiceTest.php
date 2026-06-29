<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\PublicAiAgent\PublicAgentRegistrationValidationService;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

it('rechaza correo duplicado con mensaje amigable', function (): void {
    if (! Schema::hasTable('users')) {
        $this->markTestSkipped('Tabla users no disponible en el entorno de prueba.');
    }

    $email = 'chat-validation-'.uniqid().'@test.com';

    User::query()->create([
        'name' => 'Usuario Test',
        'email' => $email,
        'password' => bcrypt('password'),
        'phone' => '04149999999',
    ]);

    $service = new PublicAgentRegistrationValidationService;

    $result = $service->validateSimplifiedPayload([
        'name' => 'María Pérez',
        'email' => $email,
        'phone_1' => '04141234567',
        'classification' => 'agent',
        'agency_name' => 'TDG',
    ]);

    expect($result['errors'])->not->toBeEmpty()
        ->and(collect($result['errors'])->first())->toContain('correo electrónico ya está registrado');
});

it('lista agencias coincidentes para seleccion del usuario', function (): void {
    insertPublicAiAgentTestAgency([
        'id' => 99001,
        'name_corporative' => 'TDG Principal Test',
        'code' => 'TDGT001',
    ]);
    insertPublicAiAgentTestAgency([
        'id' => 99002,
        'name_corporative' => 'TDG Oriente Test',
        'code' => 'TDGT002',
    ]);

    $service = new PublicAgentRegistrationValidationService;

    $agencies = $service->findAgenciesByTerm('TDG Principal Test');

    expect(count($agencies))->toBeGreaterThanOrEqual(1)
        ->and($service->agencySelectionPrompt($agencies))->toContain('TDG Principal Test');

    DB::table('agencies')->whereIn('id', [99001, 99002])->delete();
});

it('resuelve seleccion de agencia por numero', function (): void {
    $service = new PublicAgentRegistrationValidationService;

    $candidates = [
        ['id' => 10, 'name' => 'TDG Principal', 'code' => 'TDG001', 'label' => 'TDG Principal — Código TDG001'],
        ['id' => 11, 'name' => 'TDG Oriente', 'code' => 'TDG002', 'label' => 'TDG Oriente — Código TDG002'],
    ];

    $selected = $service->resolveAgencySelection('2', $candidates);

    expect($selected)->not->toBeNull()
        ->and($selected['id'])->toBe(11);
});

it('encuentra agencias por coincidencia parcial sin importar mayusculas', function (): void {
    insertPublicAiAgentTestAgency([
        'id' => 99010,
        'name_corporative' => 'Corporación ABP Principal Test',
        'code' => 'ABP-99010',
    ]);
    insertPublicAiAgentTestAgency([
        'id' => 99011,
        'name_corporative' => 'Grupo ABP Sur Test',
        'code' => 'ABP-99011',
    ]);

    $service = new PublicAgentRegistrationValidationService;

    $agencies = $service->findAgenciesByTerm('ABp');

    expect(count($agencies))->toBeGreaterThanOrEqual(2)
        ->and(collect($agencies)->pluck('name')->all())->toContain('Corporación ABP Principal Test', 'Grupo ABP Sur Test')
        ->and($service->agencySelectionPrompt($agencies))->toContain('Encontré varias agencias');

    DB::table('agencies')->whereIn('id', [99010, 99011])->delete();
});

it('detecta coincidencia exacta de agencia para autoseleccion', function (): void {
    $service = new PublicAgentRegistrationValidationService;

    $agency = ['id' => 1, 'name' => 'TDG Principal', 'code' => 'TDG001', 'label' => 'TDG Principal — TDG001'];

    expect($service->isExactAgencyMatch('TDG Principal', $agency))->toBeTrue()
        ->and($service->isExactAgencyMatch('tdg001', $agency))->toBeTrue()
        ->and($service->isExactAgencyMatch('TDG', $agency))->toBeFalse();
});

it('resuelve TDG exacto como TuDrGroup con codigo TDG-100 para agentes', function (): void {
    $service = new PublicAgentRegistrationValidationService;

    expect($service->isExactTdgAgencyTerm('TDG'))->toBeTrue()
        ->and($service->isExactTdgAgencyTerm('tdg'))->toBeTrue()
        ->and($service->isExactTdgAgencyTerm('TDG-100'))->toBeFalse()
        ->and($service->isExactTdgAgencyTerm('TDG Principal'))->toBeFalse();

    $payload = $service->applyTdgAgency([
        'agency_name' => 'tdg',
        'name' => 'María Pérez',
    ]);

    expect($payload['owner_code'])->toBe('TDG-100')
        ->and($payload['selected_agency_label'])->toBe('TuDrGroup — TDG-100')
        ->and($payload['belongs_to_tudrgroup_structure'])->toBeTrue()
        ->and($service->belongsToTudrgroupStructure($payload))->toBeTrue();
});
