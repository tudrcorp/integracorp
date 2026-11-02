<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

use App\Filament\Business\Resources\ProspectAgents\Pages\EditProspectAgent;
use App\Filament\Business\Resources\ProspectAgents\Pages\ViewProspectAgent;
use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;
use App\Filament\Business\Resources\ProspectAgents\Schemas\ProspectAgentForm;
use App\Models\City;
use App\Models\ProspectAgent;
use App\Models\ProspectAgentBranch;
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
        ->first();

    if ($analyst === null || $this->prospect === null || $this->city === null) {
        $this->markTestSkipped('Faltan un analista SUPERADMIN, un prospecto completo o el catálogo de Venezuela.');
    }

    $this->actingAs($analyst);
});

afterEach(fn () => DB::rollBack());

/**
 * Agrega una sucursal vacía en el formulario de edición y devuelve la clave del ítem nuevo.
 */
function addBranchItem($page): string
{
    $before = array_keys($page->get('data.prospectAgentBranches') ?? []);

    $page->call('mountAction', 'add', [], ['schemaComponent' => 'form.prospectAgentBranches'])
        ->call('callMountedAction');

    $after = array_keys($page->get('data.prospectAgentBranches') ?? []);

    return (string) collect($after)->diff($before)->first();
}

it('declara la sucursal con sus campos y relaciones', function (): void {
    $branch = new ProspectAgentBranch;

    expect($branch->getTable())->toBe('prospect_agent_branches')
        ->and($branch->getFillable())->toContain('prospect_agent_id', 'state_id', 'city_id', 'name', 'email', 'phone', 'local_phone', 'address', 'sort_order')
        ->and($branch->state()->getRelated()->getTable())->toBe('states')
        ->and($branch->city()->getRelated()->getTable())->toBe('cities')
        ->and((new ProspectAgent)->prospectAgentBranches()->getRelated())->toBeInstanceOf(ProspectAgentBranch::class);
});

it('exige el nombre solo cuando la sucursal trae otro dato', function (): void {
    expect(ProspectAgentForm::branchHasData(['city_id' => 5]))->toBeTrue()
        ->and(ProspectAgentForm::branchHasData(['address' => 'Av. Bolívar']))->toBeTrue()
        ->and(ProspectAgentForm::branchHasData([]))->toBeFalse()
        ->and(ProspectAgentForm::branchHasData(['name' => 'Sucursal']))->toBeFalse()
        ->and(ProspectAgentForm::branchHasData(['name' => 'Sucursal'], includeName: true))->toBeTrue();
});

it('filtra las ciudades por el estado elegido y cae en Venezuela si no hay país', function (): void {
    $states = ProspectAgentForm::branchStateOptions(null);
    $cities = ProspectAgentForm::branchCityOptions($this->city->state_id);

    expect($states)->toHaveKey($this->city->state_id)
        ->and($cities)->toHaveKey($this->city->id)
        ->and(ProspectAgentForm::branchCityOptions(null))->toBe([]);
});

it('guarda una sucursal completa desde Editar', function (): void {
    $before = $this->prospect->prospectAgentBranches()->count();

    $page = Livewire::test(EditProspectAgent::class, ['record' => $this->prospect->getKey()]);
    $key = addBranchItem($page);

    $page->set("data.prospectAgentBranches.{$key}.state_id", $this->city->state_id)
        ->set("data.prospectAgentBranches.{$key}.city_id", $this->city->id)
        ->set("data.prospectAgentBranches.{$key}.name", 'Sucursal Pest Centro')
        ->set("data.prospectAgentBranches.{$key}.email", 'centro@ejemplo.com')
        ->set("data.prospectAgentBranches.{$key}.phone", '04125678909')
        ->set("data.prospectAgentBranches.{$key}.local_phone", '02419876543')
        ->set("data.prospectAgentBranches.{$key}.address", 'Av. Bolívar, Torre Pest')
        ->call('save')
        ->assertHasNoErrors();

    $branch = $this->prospect->prospectAgentBranches()->where('name', 'Sucursal Pest Centro')->first();

    expect($this->prospect->prospectAgentBranches()->count())->toBe($before + 1)
        ->and($branch)->not->toBeNull()
        ->and($branch->state_id)->toBe((int) $this->city->state_id)
        ->and($branch->city_id)->toBe((int) $this->city->id)
        ->and($branch->local_phone)->toBe('02419876543')
        ->and($branch->address)->toBe('Av. Bolívar, Torre Pest');
});

it('no guarda una sucursal agregada y dejada en blanco', function (): void {
    $before = $this->prospect->prospectAgentBranches()->count();

    $page = Livewire::test(EditProspectAgent::class, ['record' => $this->prospect->getKey()]);
    addBranchItem($page);

    $page->call('save')->assertHasNoErrors();

    expect($this->prospect->prospectAgentBranches()->count())->toBe($before);
});

it('no deja guardar una sucursal con datos pero sin nombre', function (): void {
    $before = $this->prospect->prospectAgentBranches()->count();

    $page = Livewire::test(EditProspectAgent::class, ['record' => $this->prospect->getKey()]);
    $key = addBranchItem($page);

    $page->set("data.prospectAgentBranches.{$key}.local_phone", '02419876543')
        ->call('save')
        ->assertHasErrors(["data.prospectAgentBranches.{$key}.name"]);

    expect($this->prospect->prospectAgentBranches()->count())->toBe($before);
});

it('rechaza teléfonos con letras o signos', function (): void {
    $page = Livewire::test(EditProspectAgent::class, ['record' => $this->prospect->getKey()]);
    $key = addBranchItem($page);

    $page->set("data.prospectAgentBranches.{$key}.name", 'Sucursal Pest')
        ->set("data.prospectAgentBranches.{$key}.phone", '0412-567')
        ->call('save')
        ->assertHasErrors(["data.prospectAgentBranches.{$key}.phone"]);
});

it('muestra la sucursal en su pestaña del infolist y en la ficha PDF', function (): void {
    $this->prospect->prospectAgentBranches()->create([
        'state_id' => $this->city->state_id,
        'city_id' => $this->city->id,
        'name' => 'Sucursal Pest Infolist',
        'local_phone' => '02415550000',
        'address' => 'Calle Pest 123',
    ]);

    Livewire::test(ViewProspectAgent::class, ['record' => $this->prospect->getKey()])
        ->assertSee('Sucursales')
        ->assertSee('Nombre / razón social')
        ->assertSee('Sucursal Pest Infolist')
        ->assertSee($this->city->definition);

    $html = View::make('documents.prospect-agent-ficha', [
        'prospect' => $this->prospect->fresh()->load(['state', 'city', 'country', 'prospectAgentContacts', 'prospectAgentBranches.state', 'prospectAgentBranches.city', 'prospect_agent_observations', 'prospect_agent_tasks.rrhh_colaborador']),
        'logoDataUri' => '',
        'generatedAt' => now(),
    ])->render();

    expect($html)->toContain('Sucursales')
        ->toContain('Sucursal Pest Infolist')
        ->toContain('02415550000')
        ->toContain('Calle Pest 123')
        ->toContain(e($this->city->definition));
});

it('coloca Sucursales después de Datos del prospecto en el formulario y de Contactos Secundarios en el infolist', function (): void {
    $form = file_get_contents(app_path('Filament/Business/Resources/ProspectAgents/Schemas/ProspectAgentForm.php'));
    $infolist = file_get_contents(app_path('Filament/Business/Resources/ProspectAgents/Schemas/ProspectAgentInfolist.php'));

    expect(strpos($form, "Tab::make('Sucursales')"))->toBeGreaterThan(strpos($form, "Tab::make('Datos del prospecto')"))
        ->and(strpos($form, "Tab::make('Sucursales')"))->toBeLessThan(strpos($form, "Tab::make('Embudo comercial')"))
        ->and(strpos($infolist, "Tab::make('Sucursales')"))->toBeGreaterThan(strpos($infolist, "Tab::make('Contactos Secundarios')"))
        ->and(strpos($infolist, "Tab::make('Sucursales')"))->toBeLessThan(strpos($infolist, "Tab::make('Ubicación')"));
});
