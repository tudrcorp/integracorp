<?php

declare(strict_types=1);

use App\Models\Agency;
use App\Models\Agent;
use App\Support\PlanGenerators\PlanGeneratorAgentLookup;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
});

afterEach(function (): void {
    DB::rollBack();
});

it('no consulta mientras el texto tiene menos de dos letras', function (): void {
    expect(PlanGeneratorAgentLookup::search(''))->toBe([])
        ->and(PlanGeneratorAgentLookup::search(' '))->toBe([])
        ->and(PlanGeneratorAgentLookup::search('a'))->toBe([]);
});

it('guarda el nombre en mayúsculas y conserva un nombre que no es un registro', function (): void {
    expect(PlanGeneratorAgentLookup::nameForStorage('  eiram briceño  '))->toBe('EIRAM BRICEÑO')
        ->and(PlanGeneratorAgentLookup::nameForStorage(''))->toBeNull()
        ->and(PlanGeneratorAgentLookup::nameForStorage('   '))->toBeNull()
        ->and(PlanGeneratorAgentLookup::labelForState('EIRAM BRICEÑO'))->toBe('EIRAM BRICEÑO')
        ->and(PlanGeneratorAgentLookup::nameForStorage('agent:0'))->toBeNull()
        ->and(PlanGeneratorAgentLookup::nameForStorage('agent:999999999'))->toBeNull();
});

it('busca un agente o una agencia activos y guarda el nombre del registro elegido', function (): void {
    $suffix = uniqid('', true);

    $agent = Agent::withoutEvents(fn (): Agent => Agent::query()->create([
        'name' => 'Zzplan Lookup Agente',
        'code_agent' => 'ZZP-AGT-'.$suffix,
        'rif' => 'J-ZZP'.$suffix,
        'email' => 'zzplan-agent-'.$suffix.'@example.test',
        'status' => 'ACTIVO',
    ]));

    Agent::withoutEvents(fn (): Agent => Agent::query()->create([
        'name' => 'Zzplan Lookup Inactivo',
        'code_agent' => 'ZZP-OFF-'.$suffix,
        'email' => 'zzplan-off-'.$suffix.'@example.test',
        'status' => 'INACTIVO',
    ]));

    $agency = Agency::withoutEvents(fn (): Agency => Agency::query()->create([
        'name_corporative' => 'Zzplan Lookup Agencia',
        'code' => 'ZZP-AGC-'.$suffix,
        'rif' => 'J-ZZA'.$suffix,
        'email' => 'zzplan-agency-'.$suffix.'@example.test',
        'agency_type_id' => '1',
        'status' => 'ACTIVO',
    ]));

    $byName = PlanGeneratorAgentLookup::search('zzplan lookup');

    expect($byName)
        ->toHaveKey('agent:'.$agent->getKey())
        ->toHaveKey('agency:'.$agency->getKey())
        ->and($byName['agent:'.$agent->getKey()])->toContain('AGENTE')
        ->and($byName['agent:'.$agent->getKey()])->toContain('ZZP-AGT-'.mb_strtoupper($suffix))
        ->and($byName['agency:'.$agency->getKey()])->toContain('AGENCIA')
        ->and(implode(' ', $byName))->not->toContain('INACTIVO');

    $byCode = PlanGeneratorAgentLookup::search('ZZP-AGC-'.$suffix);

    expect($byCode)->toHaveKey('agency:'.$agency->getKey())
        ->and($byCode)->not->toHaveKey('agent:'.$agent->getKey());

    $byRif = PlanGeneratorAgentLookup::search('J-ZZP'.$suffix);

    expect($byRif)->toHaveKey('agent:'.$agent->getKey());

    $manualKey = collect(array_keys($byName))->first(fn (string $key): bool => str_starts_with($key, 'manual:'));

    expect($manualKey)->toBeString()
        ->and($byName[$manualKey])->toBe('Usar «ZZPLAN LOOKUP»');

    expect(PlanGeneratorAgentLookup::search('%'))->not->toHaveKey('agent:'.$agent->getKey())
        ->and(PlanGeneratorAgentLookup::nameForStorage('agent:'.$agent->getKey()))->toBe('ZZPLAN LOOKUP AGENTE')
        ->and(PlanGeneratorAgentLookup::nameForStorage('agency:'.$agency->getKey()))->toBe('ZZPLAN LOOKUP AGENCIA')
        ->and(PlanGeneratorAgentLookup::labelForState('agent:'.$agent->getKey()))->toContain('ZZPLAN LOOKUP AGENTE');
});

it('guarda un nombre escrito a mano sin crear agente ni agencia', function (): void {
    $name = 'Zzplan Manual '.uniqid('', true);
    $stored = mb_strtoupper($name);
    $agentsBefore = Agent::query()->count();
    $agenciesBefore = Agency::query()->count();

    $results = PlanGeneratorAgentLookup::search($name);
    $manualKey = collect(array_keys($results))->first(fn (string $key): bool => str_starts_with($key, 'manual:'));

    expect($manualKey)->toBeString()
        ->and($results[$manualKey])->toBe('Usar «'.$stored.'»')
        ->and(PlanGeneratorAgentLookup::nameForStorage($manualKey))->toBe($stored)
        ->and(PlanGeneratorAgentLookup::labelForState($manualKey))->toBe($stored)
        ->and(PlanGeneratorAgentLookup::nameForStorage('manual:'))->toBeNull()
        ->and(Agent::query()->count())->toBe($agentsBefore)
        ->and(Agency::query()->count())->toBe($agenciesBefore)
        ->and(Agent::query()->where('name', $stored)->exists())->toBeFalse()
        ->and(Agency::query()->where('name_corporative', $stored)->exists())->toBeFalse();
});

it('el formulario y la cotización derivada usan el mismo buscador de un solo registro', function (): void {
    $lookup = (string) file_get_contents(dirname(__DIR__, 2).'/app/Support/PlanGenerators/PlanGeneratorAgentLookup.php');
    $form = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/Schemas/PlanGeneratorForm.php');
    $action = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/PlanGenerators/Tables/Actions/DeriveQuotationBulkAction.php');

    expect($lookup)
        ->toContain('Select::make(\'agent_name\')')
        ->toContain('->searchable()')
        ->toContain('->preload(false)')
        ->toContain('->getSearchResultsUsing(')
        ->toContain('->dehydrateStateUsing(')
        ->toContain('Usar «')
        ->not->toContain('->multiple(')
        ->not->toContain('::create(');

    expect($form)->toContain('PlanGeneratorAgentLookup::field()');
    expect($action)->toContain('PlanGeneratorAgentLookup::field()');
});
