<?php

declare(strict_types=1);

use App\Models\TravelAgency;
use App\Models\TravelAgent;
use App\Support\TravelAgencies\TravelAgencyPublicRegistrar;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
});

afterEach(function (): void {
    DB::rollBack();
});

it('hereda la jerarquía de la agencia de viajes y el agente queda en esa agencia', function (): void {
    $suffix = uniqid('', true);

    $parent = TravelAgency::query()->create([
        'name' => 'Zzplan Viajes '.$suffix,
        'nivel' => 'Nivel 2',
        'agenciaPpalNivel1' => 'TDEV',
        'classification' => 'AGENCIA DE VIAJES',
        'status' => 'Activo',
    ]);

    $parentHierarchy = $parent->only([
        'nivel',
        'agenciaPpalNivel1',
        'agenciaSuperiorNivel2',
        'agenteSuperiorNivel3',
    ]);

    $child = TravelAgencyPublicRegistrar::registerAgency($parent, [
        'name' => 'zzplan asociada '.$suffix,
        'email' => 'Zzplan-'.$suffix.'@Example.test',
        'representante' => 'ana ruiz',
    ]);

    expect($child->parent_id)->toBe($parent->id)
        ->and($child->nivel)->toBe('3')
        ->and($child->agenciaPpalNivel1)->toBe('TDEV')
        ->and($child->agenciaSuperiorNivel2)->toBe(mb_strtoupper('Zzplan Viajes '.$suffix))
        ->and($child->agenteSuperiorNivel3)->toBeNull()
        ->and($child->name)->toBe(mb_strtoupper('zzplan asociada '.$suffix))
        ->and($child->email)->toBe(mb_strtolower('Zzplan-'.$suffix.'@Example.test'))
        ->and($child->representante)->toBe('ANA RUIZ')
        ->and($child->registration_token)->not->toBe($parent->registration_token)
        ->and($parent->fresh()->only([
            'nivel',
            'agenciaPpalNivel1',
            'agenciaSuperiorNivel2',
            'agenteSuperiorNivel3',
        ]))->toBe($parentHierarchy);

    $agent = TravelAgencyPublicRegistrar::registerAgent($child, [
        'name' => 'zzplan agente '.$suffix,
        'cargo' => 'asesor',
        'email' => 'Carlos@Example.test',
    ]);

    $child->refresh();

    expect($agent->travel_agency_id)->toBe($child->id)
        ->and($agent->name)->toBe(mb_strtoupper('zzplan agente '.$suffix))
        ->and($agent->cargo)->toBe('ASESOR')
        ->and($child->nivel)->toBe('3')
        ->and($child->agenciaPpalNivel1)->toBe('TDEV')
        ->and($child->agenciaSuperiorNivel2)->toBe(mb_strtoupper('Zzplan Viajes '.$suffix))
        ->and($child->travelAgents()->whereKey($agent->getKey())->exists())->toBeTrue()
        ->and(TravelAgent::query()->where('name', mb_strtoupper('zzplan agente '.$suffix))->where('travel_agency_id', '!=', $child->id)->exists())->toBeFalse();

    expect(TravelAgencyPublicRegistrar::agentRegistrationUrl($child))->toContain('/viajes/agente/'.$child->registration_token)
        ->and(TravelAgencyPublicRegistrar::agencyRegistrationUrl($parent))->toContain('/viajes/agencia/'.$parent->agency_registration_token);
});

it('una agencia nivel 1 deja a la asociada en nivel 2 sin agencia superior', function (): void {
    $parent = TravelAgency::query()->create([
        'name' => 'Zzplan Matriz '.uniqid('', true),
        'nivel' => '1',
        'agenciaPpalNivel1' => 'TDEV',
        'status' => 'Activo',
    ]);

    $hierarchy = TravelAgencyPublicRegistrar::hierarchyFromParent($parent);

    expect($hierarchy['nivel'])->toBe('2')
        ->and($hierarchy['agenciaPpalNivel1'])->toBe('TDEV')
        ->and($hierarchy['agenciaSuperiorNivel2'])->toBeNull()
        ->and($hierarchy['parent_id'])->toBe($parent->id);
});

it('la ficha y las rutas publican los dos enlaces de la agencia de viajes', function (): void {
    $form = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/TravelAgencies/Schemas/TravelAgencyForm.php');
    $infolist = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/TravelAgencies/Schemas/TravelAgencyInfolist.php');
    $routes = (string) file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
    $agentPage = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/travel-agent-registration.blade.php');

    expect($form)
        ->toContain('Link de registro de agencias')
        ->toContain('Link de registro de agentes')
        ->toContain('TravelAgencyPublicRegistrar::agencyRegistrationUrl')
        ->toContain('TravelAgencyPublicRegistrar::agentRegistrationUrl');

    expect($infolist)
        ->toContain('Link de registro de agencias')
        ->toContain('Link de registro de agentes');

    expect($routes)
        ->toContain('/viajes/agencia/{token}')
        ->toContain('/viajes/agente/{token}')
        ->toContain("name('travel-agencies.register')")
        ->toContain("name('travel-agents.register')");

    expect($agentPage)
        ->toContain('registrationHierarchyLines')
        ->toContain('dentro de su jerarquía comercial');
});
