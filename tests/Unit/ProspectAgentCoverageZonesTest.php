<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

use App\Filament\Business\Resources\ProspectAgents\Pages\EditProspectAgent;
use App\Filament\Business\Resources\ProspectAgents\Pages\ViewProspectAgent;
use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;
use App\Filament\Business\Resources\ProspectAgents\Schemas\ProspectAgentForm;
use App\Models\City;
use App\Models\ProspectAgent;
use App\Models\ProspectAgentCoverageZone;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

/**
 * Todo lo que escribe va dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    Filament::setCurrentPanel('business');

    $analyst = User::query()
        ->where('email', 'like', '%@tudrencasa.com')
        ->where('status', 'ACTIVO')
        ->get()
        ->first(fn (User $user): bool => in_array('SUPERADMIN', (array) ($user->departament ?? []), true));

    $this->prospect = ProspectAgent::query()
        ->whereNotNull('state_id')
        ->whereNotNull('city_id')
        ->whereNotNull('type')
        ->whereNotNull('reference_by')
        ->whereIn('status', array_keys(ProspectAgentLabels::statusOptions()))
        ->latest('id')
        ->first();

    $this->city = City::query()
        ->whereIn('state_id', DB::table('states')->where('country_id', 189)->pluck('id'))
        ->with('state')
        ->first();

    if ($analyst === null || $this->prospect === null || $this->city === null) {
        $this->markTestSkipped('Faltan un analista SUPERADMIN, un prospecto completo o el catálogo de Venezuela.');
    }

    $this->actingAs($analyst);
});

afterEach(fn () => DB::rollBack());

/**
 * Agrega una zona vacía en el formulario de edición y devuelve la clave del ítem nuevo.
 */
function addCoverageZoneItem($page): string
{
    $before = array_keys($page->get('data.prospectAgentCoverageZones') ?? []);

    $page->call('mountAction', 'add', [], ['schemaComponent' => 'form.prospectAgentCoverageZones'])
        ->call('callMountedAction');

    $after = array_keys($page->get('data.prospectAgentCoverageZones') ?? []);

    return (string) collect($after)->diff($before)->first();
}

it('declara la zona de cobertura con sus campos y relaciones', function (): void {
    $zone = new ProspectAgentCoverageZone;

    expect($zone->getTable())->toBe('prospect_agent_coverage_zones')
        ->and($zone->getFillable())->toContain('prospect_agent_id', 'classification', 'service_type', 'state_id', 'city_id', 'sort_order')
        ->and($zone->state()->getRelated()->getTable())->toBe('states')
        ->and($zone->city()->getRelated()->getTable())->toBe('cities')
        ->and((new ProspectAgent)->prospectAgentCoverageZones()->getRelated())->toBeInstanceOf(ProspectAgentCoverageZone::class);
});

it('exige el estado solo cuando la zona trae otro dato', function (): void {
    expect(ProspectAgentForm::coverageZoneHasData(['classification' => 'Corretaje']))->toBeTrue()
        ->and(ProspectAgentForm::coverageZoneHasData(['service_type' => 'Venta']))->toBeTrue()
        ->and(ProspectAgentForm::coverageZoneHasData([]))->toBeFalse()
        ->and(ProspectAgentForm::coverageZoneHasData(['classification' => '   ']))->toBeFalse()
        ->and(ProspectAgentForm::coverageZoneHasData(['state_id' => 1]))->toBeFalse()
        ->and(ProspectAgentForm::coverageZoneHasData(['state_id' => 1], includeState: true))->toBeTrue();
});

it('guarda una zona completa desde Editar', function (): void {
    $page = Livewire::test(EditProspectAgent::class, ['record' => $this->prospect->getKey()]);
    $key = addCoverageZoneItem($page);

    $page->set("data.prospectAgentCoverageZones.{$key}.classification", 'Corretaje Pest')
        ->set("data.prospectAgentCoverageZones.{$key}.service_type", 'Venta de planes')
        ->set("data.prospectAgentCoverageZones.{$key}.state_id", $this->city->state_id)
        ->set("data.prospectAgentCoverageZones.{$key}.city_id", $this->city->id)
        ->call('save')
        ->assertHasNoErrors();

    $zone = $this->prospect->prospectAgentCoverageZones()->where('classification', 'Corretaje Pest')->first();

    expect($zone)->not->toBeNull()
        ->and($zone->service_type)->toBe('Venta de planes')
        ->and($zone->state_id)->toBe((int) $this->city->state_id)
        ->and($zone->city_id)->toBe((int) $this->city->id);
});

it('guarda una zona de todo el estado cuando no se elige ciudad', function (): void {
    $page = Livewire::test(EditProspectAgent::class, ['record' => $this->prospect->getKey()]);
    $key = addCoverageZoneItem($page);

    $page->set("data.prospectAgentCoverageZones.{$key}.classification", 'Estatal Pest')
        ->set("data.prospectAgentCoverageZones.{$key}.state_id", $this->city->state_id)
        ->call('save')
        ->assertHasNoErrors();

    $zone = $this->prospect->prospectAgentCoverageZones()->where('classification', 'Estatal Pest')->first();

    expect($zone)->not->toBeNull()
        ->and($zone->city_id)->toBeNull();
});

it('no guarda una zona agregada y dejada en blanco', function (): void {
    $before = $this->prospect->prospectAgentCoverageZones()->count();

    $page = Livewire::test(EditProspectAgent::class, ['record' => $this->prospect->getKey()]);
    addCoverageZoneItem($page);

    $page->call('save')->assertHasNoErrors();

    expect($this->prospect->prospectAgentCoverageZones()->count())->toBe($before);
});

it('no deja guardar una zona con datos pero sin estado', function (): void {
    $before = $this->prospect->prospectAgentCoverageZones()->count();

    $page = Livewire::test(EditProspectAgent::class, ['record' => $this->prospect->getKey()]);
    $key = addCoverageZoneItem($page);

    $page->set("data.prospectAgentCoverageZones.{$key}.service_type", 'Asistencia de viaje')
        ->call('save')
        ->assertHasErrors(["data.prospectAgentCoverageZones.{$key}.state_id"]);

    expect($this->prospect->prospectAgentCoverageZones()->count())->toBe($before);
});

it('muestra la zona en su pestaña del infolist y en la ficha PDF', function (): void {
    $this->prospect->prospectAgentCoverageZones()->create([
        'classification' => 'Clasificación Pest',
        'service_type' => 'Servicio Pest',
        'state_id' => $this->city->state_id,
    ]);

    Livewire::test(ViewProspectAgent::class, ['record' => $this->prospect->getKey()])
        ->assertSee('Zonas de cobertura')
        ->assertSee('Tipo de servicio')
        ->assertSee('Clasificación Pest')
        ->assertSee('Todo el estado');

    $html = View::make('documents.prospect-agent-ficha', [
        'prospect' => $this->prospect->fresh()->load(['state', 'city', 'country', 'prospectAgentContacts', 'prospectAgentBranches.state', 'prospectAgentBranches.city', 'prospectAgentCoverageZones.state', 'prospectAgentCoverageZones.city', 'prospect_agent_observations', 'prospect_agent_tasks.rrhh_colaborador']),
        'logoDataUri' => '',
        'generatedAt' => now(),
    ])->render();

    expect($html)->toContain('Zonas de cobertura')
        ->toContain('Clasificación Pest')
        ->toContain('Servicio Pest')
        ->toContain(e($this->city->state->definition))
        ->toContain('Todo el estado');
});

it('coloca Zonas de cobertura después de Sucursales en el formulario y en el infolist', function (): void {
    $form = file_get_contents(app_path('Filament/Business/Resources/ProspectAgents/Schemas/ProspectAgentForm.php'));
    $infolist = file_get_contents(app_path('Filament/Business/Resources/ProspectAgents/Schemas/ProspectAgentInfolist.php'));

    expect(strpos($form, "Tab::make('Zonas de cobertura')"))->toBeGreaterThan(strpos($form, "Tab::make('Sucursales')"))
        ->and(strpos($form, "Tab::make('Zonas de cobertura')"))->toBeLessThan(strpos($form, "Tab::make('Embudo comercial')"))
        ->and(strpos($infolist, "Tab::make('Zonas de cobertura')"))->toBeGreaterThan(strpos($infolist, "Tab::make('Sucursales')"))
        ->and(strpos($infolist, "Tab::make('Zonas de cobertura')"))->toBeLessThan(strpos($infolist, "Tab::make('Ubicación')"));
});
