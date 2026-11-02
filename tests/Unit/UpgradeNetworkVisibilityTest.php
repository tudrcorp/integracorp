<?php

declare(strict_types=1);

use App\Enums\UpgradeNetworkVisibility;
use App\Models\Upgrade;
use App\Models\User;
use App\Support\Upgrades\UpgradeCatalogPersistence;
use App\Support\Upgrades\UpgradeNetworkAudience;
use App\Support\Upgrades\UpgradeStructureValidator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Conexión sqlite `:memory:` propia: la config cacheada apunta a MySQL y este test escribe.
 *
 * Red de prueba: MASTER → GENERAL → agente → subagente, más una MASTER ajena con su agente.
 */
beforeEach(function (): void {
    config()->set('database.connections.upgrade_network_testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    config()->set('database.default', 'upgrade_network_testing');
    DB::purge('upgrade_network_testing');
    DB::setDefaultConnection('upgrade_network_testing');

    expect(DB::connection()->getDatabaseName())->toBe(':memory:');

    createUpgradeCatalogTestSchema(withFixture: false);

    foreach (['affiliate_corporate_upgrades', 'affiliate_upgrades'] as $name) {
        Schema::create($name, function (Blueprint $table): void {
            $table->id();
            upgradeAssignmentTestColumns($table);
        });
    }

    Schema::create('logs', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('action')->nullable();
        $table->string('route')->nullable();
        $table->text('response')->nullable();
        $table->string('method')->nullable();
        $table->string('ip')->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamps();
    });

    Schema::create('agencies', function (Blueprint $table): void {
        $table->id();
        $table->string('code')->nullable();
        $table->string('owner_code')->nullable();
        $table->string('name_corporative')->nullable();
        $table->string('status')->nullable();
    });

    Schema::create('agents', function (Blueprint $table): void {
        $table->id();
        $table->string('code_agent')->nullable();
        $table->string('name')->nullable();
        $table->string('owner_code')->nullable();
        $table->string('owner_agent')->nullable();
        $table->string('status')->nullable();
    });

    DB::table('agencies')->insert([
        ['id' => 1, 'code' => 'TDG-200', 'owner_code' => 'TDG-100', 'name_corporative' => 'MASTER UNO', 'status' => 'ACTIVO'],
        ['id' => 2, 'code' => 'TDG-201', 'owner_code' => 'TDG-200', 'name_corporative' => 'GENERAL UNO', 'status' => 'ACTIVO'],
        ['id' => 3, 'code' => 'TDG-300', 'owner_code' => 'TDG-100', 'name_corporative' => 'MASTER AJENA', 'status' => 'ACTIVO'],
        ['id' => 4, 'code' => 'TDG-301', 'owner_code' => 'TDG-100', 'name_corporative' => 'EN REVISION', 'status' => 'POR REVISION'],
    ]);

    DB::table('agents')->insert([
        ['id' => 10, 'code_agent' => 'AG-10', 'name' => 'AGENTE DE GENERAL', 'owner_code' => 'TDG-201', 'owner_agent' => null, 'status' => 'ACTIVO'],
        ['id' => 11, 'code_agent' => 'AG-11', 'name' => 'SUBAGENTE', 'owner_code' => 'TDG-201', 'owner_agent' => '10', 'status' => 'ACTIVO'],
        ['id' => 20, 'code_agent' => 'AG-20', 'name' => 'AGENTE AJENO', 'owner_code' => 'TDG-300', 'owner_agent' => null, 'status' => 'ACTIVO'],
        ['id' => 21, 'code_agent' => 'AG-21', 'name' => 'AGENTE CASA MATRIZ', 'owner_code' => 'TDG-100', 'owner_agent' => null, 'status' => 'ACTIVO'],
        ['id' => 22, 'code_agent' => 'AG-22', 'name' => 'CICLO A', 'owner_code' => null, 'owner_agent' => '23', 'status' => 'ACTIVO'],
        ['id' => 23, 'code_agent' => 'AG-23', 'name' => 'CICLO B', 'owner_code' => null, 'owner_agent' => '22', 'status' => 'ACTIVO'],
    ]);

    Auth::setUser(User::factory()->make(['id' => 99, 'name' => 'Analista Negocios']));
});

function networkUpgrade(string $name, UpgradeNetworkVisibility $visibility = UpgradeNetworkVisibility::BusinessOnly, array $agencies = [], array $agents = [], string $status = 'ACTIVO'): Upgrade
{
    return UpgradeCatalogPersistence::save(null, [
        'name' => $name,
        'status' => $status,
        'network_visibility' => $visibility->value,
        'network_agency_ids' => $agencies,
        'network_agent_ids' => $agents,
        'coverages' => [['amount' => 2000, 'ranges' => [['age_from' => 0, 'age_to' => 60, 'price' => 50]]]],
    ]);
}

function agentUser(int $agentId): User
{
    return User::factory()->make(['id' => 1000 + $agentId, 'is_agent' => true, 'is_agency' => false, 'agent_id' => $agentId, 'code_agency' => null]);
}

function agencyUser(string $code, string $type = 'MASTER'): User
{
    return User::factory()->make(['id' => 2000 + (int) substr($code, -3), 'is_agent' => false, 'is_agency' => true, 'agency_type' => $type, 'agent_id' => null, 'code_agency' => $code]);
}

/** @return list<string> */
function networkLogs(): array
{
    return DB::table('logs')->pluck('action')->all();
}

it('arranca en Solo Negocios: nadie de la red lo ve', function (): void {
    $upgrade = networkUpgrade('PRIVADO');

    expect($upgrade->network_visibility)->toBe(UpgradeNetworkVisibility::BusinessOnly)
        ->and(UpgradeNetworkAudience::visibleUpgradeIds(agentUser(10)))->toBe([])
        ->and(UpgradeNetworkAudience::visibleUpgradeIds(agencyUser('TDG-200')))->toBe([]);
});

it('con Toda la red lo ven agentes, agencias y la casa matriz', function (): void {
    $upgrade = networkUpgrade('PARA TODOS', UpgradeNetworkVisibility::AllNetwork);

    foreach ([agentUser(10), agentUser(20), agentUser(21), agencyUser('TDG-200'), agencyUser('TDG-201', 'GENERAL')] as $user) {
        expect(UpgradeNetworkAudience::visibleUpgradeIds($user))->toBe([(int) $upgrade->getKey()]);
    }
});

it('inactivo gana siempre', function (): void {
    networkUpgrade('RETIRADO', UpgradeNetworkVisibility::AllNetwork, status: 'INACTIVO');
    networkUpgrade('RETIRADO DIRIGIDO', UpgradeNetworkVisibility::Selected, agents: [10], status: 'INACTIVO');

    expect(UpgradeNetworkAudience::visibleUpgradeIds(agentUser(10)))->toBe([]);
});

it('elegir una MASTER lo baja a toda su red y a nadie más', function (): void {
    $upgrade = networkUpgrade('DE LA MASTER', UpgradeNetworkVisibility::Selected, agencies: [1]);
    $id = (int) $upgrade->getKey();

    expect(UpgradeNetworkAudience::visibleUpgradeIds(agencyUser('TDG-200')))->toBe([$id])
        ->and(UpgradeNetworkAudience::visibleUpgradeIds(agencyUser('TDG-201', 'GENERAL')))->toBe([$id])
        ->and(UpgradeNetworkAudience::visibleUpgradeIds(agentUser(10)))->toBe([$id])
        ->and(UpgradeNetworkAudience::visibleUpgradeIds(agentUser(11)))->toBe([$id])
        ->and(UpgradeNetworkAudience::visibleUpgradeIds(agentUser(20)))->toBe([])
        ->and(UpgradeNetworkAudience::visibleUpgradeIds(agencyUser('TDG-300')))->toBe([]);
});

it('elegir una GENERAL no sube a su MASTER', function (): void {
    $id = (int) networkUpgrade('DE LA GENERAL', UpgradeNetworkVisibility::Selected, agencies: [2])->getKey();

    expect(UpgradeNetworkAudience::visibleUpgradeIds(agencyUser('TDG-201', 'GENERAL')))->toBe([$id])
        ->and(UpgradeNetworkAudience::visibleUpgradeIds(agentUser(10)))->toBe([$id])
        ->and(UpgradeNetworkAudience::visibleUpgradeIds(agencyUser('TDG-200')))->toBe([]);
});

it('elegir un agente llega a sus subagentes, no a su agencia', function (): void {
    $id = (int) networkUpgrade('DEL AGENTE', UpgradeNetworkVisibility::Selected, agents: [10])->getKey();

    expect(UpgradeNetworkAudience::visibleUpgradeIds(agentUser(10)))->toBe([$id])
        ->and(UpgradeNetworkAudience::visibleUpgradeIds(agentUser(11)))->toBe([$id])
        ->and(UpgradeNetworkAudience::visibleUpgradeIds(agencyUser('TDG-201', 'GENERAL')))->toBe([]);
});

it('tolera ciclos, agentes inexistentes y usuarios sin red', function (): void {
    networkUpgrade('DIRIGIDO', UpgradeNetworkVisibility::Selected, agents: [10]);

    expect(UpgradeNetworkAudience::chainFor(agentUser(22))['agent_ids'])->toEqualCanonicalizing([22, 23])
        ->and(UpgradeNetworkAudience::visibleUpgradeIds(agentUser(9999)))->toBe([])
        ->and(UpgradeNetworkAudience::visibleUpgradeIds(User::factory()->make(['id' => 5, 'agent_id' => null, 'is_agency' => false, 'code_agency' => null])))->toBe([])
        ->and(UpgradeNetworkAudience::cardsFor(null, network: true))->toBe([]);
});

it('Negocios ve todos los activos; la red solo los suyos', function (): void {
    $mine = networkUpgrade('MIO', UpgradeNetworkVisibility::Selected, agents: [10]);
    $other = networkUpgrade('AJENO', UpgradeNetworkVisibility::Selected, agents: [20]);
    $private = networkUpgrade('PRIVADO');

    expect(array_keys(UpgradeNetworkAudience::cardsFor(agentUser(10), network: true)))->toBe([(int) $mine->getKey()])
        ->and(array_keys(UpgradeNetworkAudience::cardsFor(null, network: false)))->toEqualCanonicalizing([(int) $mine->getKey(), (int) $other->getKey(), (int) $private->getKey()]);
});

it('rechaza por la cobertura aunque la fila diga otro upgrade', function (): void {
    $mine = networkUpgrade('MIO', UpgradeNetworkVisibility::Selected, agents: [10]);
    $other = networkUpgrade('AJENO', UpgradeNetworkVisibility::Selected, agents: [20]);
    $forged = [['upgrade_id' => $mine->getKey(), 'upgrade_coverage_id' => $other->coverages()->value('id')]];
    $fair = [['upgrade_id' => $mine->getKey(), 'upgrade_coverage_id' => $mine->coverages()->value('id')]];

    expect(UpgradeNetworkAudience::rejectedNames(agentUser(10), $forged))->toBe(['AJENO'])
        ->and(UpgradeNetworkAudience::rejectedNames(agentUser(10), $fair))->toBe([])
        ->and(UpgradeNetworkAudience::rejectedNames(null, $fair))->toBe(['MIO']);
});

it('Red seleccionada exige al menos una agencia o agente', function (): void {
    $errors = UpgradeStructureValidator::errors([
        'name' => 'X',
        'network_visibility' => UpgradeNetworkVisibility::Selected->value,
        'coverages' => [['amount' => 2000, 'ranges' => [['age_from' => 0, 'age_to' => 60, 'price' => 50]]]],
    ]);

    expect($errors)->toContain('Elija al menos una agencia o un agente que pueda cotizar este upgrade.')
        ->and(UpgradeStructureValidator::errors(['name' => 'X', 'network_visibility' => 'CUALQUIERA']))->toContain('La visibilidad en la red comercial no es válida.');
});

it('al cambiar a otra visibilidad limpia la selección y lo audita', function (): void {
    $upgrade = networkUpgrade('CAMBIANTE', UpgradeNetworkVisibility::Selected, agencies: [1, 2], agents: [10]);

    expect($upgrade->networkTargets()->count())->toBe(3);

    $state = UpgradeCatalogPersistence::formState($upgrade);

    expect($state['network_agency_ids'])->toEqualCanonicalizing([1, 2])
        ->and($state['network_agent_ids'])->toBe([10]);

    DB::table('logs')->delete();
    $updated = UpgradeCatalogPersistence::save($upgrade, [...$state, 'network_visibility' => UpgradeNetworkVisibility::AllNetwork->value]);

    expect($updated->network_visibility)->toBe(UpgradeNetworkVisibility::AllNetwork)
        ->and($updated->networkTargets()->count())->toBe(0)
        ->and(networkLogs())->toContain('AUDIT_BUSINESS_UPGRADE_NETWORK_VISIBILITY_CHANGED');
});

it('cambiar la selección reemplaza solo la diferencia e ignora ids inexistentes', function (): void {
    $upgrade = networkUpgrade('AJUSTE', UpgradeNetworkVisibility::Selected, agencies: [1], agents: [10]);
    $keptId = $upgrade->networkTargets()->whereNotNull('agency_id')->value('id');

    $state = UpgradeCatalogPersistence::formState($upgrade);
    $updated = UpgradeCatalogPersistence::save($upgrade, [...$state, 'network_agency_ids' => [1, 3, 777], 'network_agent_ids' => []]);

    expect($updated->networkTargets()->pluck('agency_id')->all())->toEqualCanonicalizing([1, 3])
        ->and($updated->networkTargets()->whereNull('agent_id')->where('agency_id', 1)->value('id'))->toBe($keptId);
});

it('guardar sin tocar la visibilidad no deja traza ni la cambia', function (): void {
    $upgrade = networkUpgrade('QUIETO', UpgradeNetworkVisibility::AllNetwork);
    $state = UpgradeCatalogPersistence::formState($upgrade);

    DB::table('logs')->delete();
    UpgradeCatalogPersistence::save($upgrade, $state);

    expect(networkLogs())->not->toContain('AUDIT_BUSINESS_UPGRADE_NETWORK_VISIBILITY_CHANGED');

    unset($state['network_visibility']);

    expect(UpgradeCatalogPersistence::save($upgrade, $state)->network_visibility)->toBe(UpgradeNetworkVisibility::AllNetwork);
});

it('el buscador solo ofrece agencias y agentes activos, sin la casa matriz', function (): void {
    DB::table('agencies')->insert(['id' => 5, 'code' => 'TDG-100', 'owner_code' => null, 'name_corporative' => 'CASA MATRIZ', 'status' => 'ACTIVO']);
    DB::table('agents')->insert(['id' => 30, 'code_agent' => 'AG-30', 'name' => 'AGENTE INACTIVO', 'owner_code' => 'TDG-100', 'owner_agent' => null, 'status' => 'INACTIVO']);

    expect(UpgradeNetworkAudience::searchAgencies('TDG'))->toHaveKeys([1, 2, 3])->not->toHaveKeys([4, 5])
        ->and(UpgradeNetworkAudience::searchAgencies('uno'))->toHaveKeys([1, 2])
        ->and(UpgradeNetworkAudience::searchAgents('AGENTE'))->toHaveKeys([10, 20])->not->toHaveKey(30)
        ->and(UpgradeNetworkAudience::agencyLabels([4]))->toBe([4 => 'TDG-301 · EN REVISION']);
});
