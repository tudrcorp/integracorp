<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\Agencies\Pages\ListAgencies;
use App\Filament\Administration\Resources\Agencies\Tables\AgenciesTable;
use App\Filament\Administration\Resources\Agents\Pages\ListAgents;
use App\Filament\Administration\Resources\Agents\Tables\AgentsTable;
use App\Filament\Administration\Resources\TravelAgencies\Pages\ListTravelAgencies;
use App\Filament\Business\Resources\TravelAgencies\Tables\TravelAgenciesTable;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\TravelAgency;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());

afterEach(fn () => DB::rollBack());

function administrationAnalystOrSkip(): User
{
    $user = User::query()
        ->where('status', 'ACTIVO')
        ->where('email', 'like', '%@tudrencasa.com')
        ->get()
        ->first(fn (User $user): bool => array_intersect(
            ['ADMINISTRACION', 'SUPERADMIN'],
            (array) $user->departament,
        ) !== []);

    if ($user === null) {
        test()->markTestSkipped('No hay analista activo de Administración en la base.');
    }

    return $user;
}

/**
 * Las tres tablas resuelven su consulta con Auth::user(), así que necesitan sesión antes de configurarse.
 *
 * @param  class-string  $tableClass
 * @param  class-string  $pageClass
 */
function configuredNetworkTable(string $tableClass, string $pageClass): Table
{
    Filament::setCurrentPanel('administration');
    Filament::auth()->login(administrationAnalystOrSkip());

    return $tableClass::configure(Table::make(new $pageClass));
}

dataset('tablas de red comercial', [
    'agencias' => [AgenciesTable::class, ListAgencies::class],
    'agentes' => [AgentsTable::class, ListAgents::class],
    'agencias de viaje' => [TravelAgenciesTable::class, ListTravelAgencies::class],
]);

/*
 * ---------------------------------------------------------------------------
 * La selección sobrevive a búsquedas, filtros y páginas
 * ---------------------------------------------------------------------------
 */

it('no vacía la selección cuando el analista cambia la búsqueda o los filtros', function (string $tableClass, string $pageClass): void {
    expect(configuredNetworkTable($tableClass, $pageClass)->shouldDeselectAllRecordsWhenFiltered())->toBeFalse();
})->with('tablas de red comercial');

it('no limita la selección a la página visible', function (string $tableClass, string $pageClass): void {
    expect(configuredNetworkTable($tableClass, $pageClass)->selectsCurrentPageOnly())->toBeFalse();
})->with('tablas de red comercial');

it('desactiva el modo de selección por descarte para que «Seleccionar todos» fije IDs', function (string $tableClass, string $pageClass): void {
    expect(configuredNetworkTable($tableClass, $pageClass)->canTrackDeselectedRecords())->toBeFalse();
})->with('tablas de red comercial');

/*
 * ---------------------------------------------------------------------------
 * Los bulk actions reciben lo marcado en búsquedas anteriores
 * ---------------------------------------------------------------------------
 */

it('resuelve los registros marcados aunque la búsqueda actual ya no los devuelva', function (string $modelClass, string $pageClass): void {
    $registro = $modelClass::query()->first();

    if ($registro === null) {
        $this->markTestSkipped("No hay registros de {$modelClass} en la base.");
    }

    Filament::setCurrentPanel('administration');

    $componente = Livewire::actingAs(administrationAnalystOrSkip())
        ->test($pageClass)
        ->set('selectedTableRecords', [(string) $registro->getKey()])
        ->set('tableSearch', 'texto-que-no-coincide-con-nada');

    $seleccionados = $componente->instance()
        ->getSelectedTableRecords()
        ->map(fn (Model $marcado): int => (int) $marcado->getKey())
        ->all();

    expect($seleccionados)->toBe([(int) $registro->getKey()]);
})->with([
    'agencias' => [Agency::class, ListAgencies::class],
    'agentes' => [Agent::class, ListAgents::class],
    'agencias de viaje' => [TravelAgency::class, ListTravelAgencies::class],
]);

/*
 * ---------------------------------------------------------------------------
 * Las pestañas de estatus sí reinician: son universos excluyentes
 * ---------------------------------------------------------------------------
 */

it('reinicia la selección al cambiar de pestaña para no exportar de menos en silencio', function (string $modelClass, string $pageClass): void {
    $activo = $modelClass::query()->where('status', 'ACTIVO')->first();

    if ($activo === null) {
        $this->markTestSkipped("No hay registros ACTIVO de {$modelClass} en la base.");
    }

    Filament::setCurrentPanel('administration');

    Livewire::actingAs(administrationAnalystOrSkip())
        ->test($pageClass)
        ->set('selectedTableRecords', [(string) $activo->getKey()])
        ->set('activeTab', 'inactivo')
        ->assertDispatched('deselectAllTableRecords');
})->with([
    'agencias' => [Agency::class, ListAgencies::class],
    'agentes' => [Agent::class, ListAgents::class],
]);
