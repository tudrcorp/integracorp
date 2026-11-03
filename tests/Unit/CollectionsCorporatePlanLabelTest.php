<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\Collections\Pages\ListCollections;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\Collection;
use App\Models\Plan;
use App\Models\User;
use App\Support\Collections\CollectionReceivableReport;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

function corporateCollectionWithPlans(array $descriptions): Collection
{
    $corporate = new AffiliationCorporate;
    $corporate->setRelation('affiliationCorporatePlans', collect(array_map(function (string $description): AfilliationCorporatePlan {
        $plan = new Plan;
        $plan->forceFill(['description' => $description]);

        $line = new AfilliationCorporatePlan;
        $line->setRelation('plan', $plan);

        return $line;
    }, $descriptions)));

    $record = new Collection;
    $record->forceFill(['type' => 'AFILIACION CORPORATIVA', 'plan_id' => null]);
    $record->setRelation('plan', null);
    $record->setRelation('affiliationCorporateByCode', $corporate);

    return $record;
}

it('una cuota corporativa sin plan muestra los planes de su afiliación', function (): void {
    expect(CollectionReceivableReport::planLabel(corporateCollectionWithPlans(['PLAN ESPECIAL', 'PLAN ESPECIAL', 'PLAN ESPECIAL'])))->toBe('PLAN ESPECIAL')
        ->and(CollectionReceivableReport::planLabel(corporateCollectionWithPlans(['PLAN INICIAL', 'PLAN IDEAL'])))->toBe('PLAN INICIAL / PLAN IDEAL')
        ->and(CollectionReceivableReport::planLabel(corporateCollectionWithPlans([])))->toBeNull();
});

it('la tabla y el CSV de Gestión de Cobranza usan la misma regla y precargan los planes', function (): void {
    $table = file_get_contents(app_path('Filament/Administration/Resources/Collections/Tables/CollectionsTable.php'));
    $csv = file_get_contents(app_path('Support/Exports/CollectionCsvExportService.php'));

    expect($table)
        ->toContain('->state(fn (Collection $record): ?string => CollectionReceivableReport::planLabel($record))')
        ->toContain('...CollectionReceivableReport::corporatePlanEagerLoads()')
        ->and($csv)
        ->toContain('CollectionReceivableReport::planLabel($record)')
        ->toContain('...CollectionReceivableReport::corporatePlanEagerLoads()');
});

it('Gestión de Cobranza se dibuja y muestra el plan de una cuota corporativa real', function (): void {
    $user = User::query()->where('departament', 'like', '%SUPERADMIN%')->where('status', 'ACTIVO')->first();
    $record = Collection::query()
        ->where('type', 'like', '%CORPORATIVA%')
        ->whereNull('plan_id')
        ->where('status', 'POR PAGAR')
        ->whereHas('affiliationCorporateByCode', fn ($query) => $query->where('status', 'ACTIVA')->whereHas('affiliationCorporatePlans'))
        ->latest('id')
        ->first();

    if ($user === null || $record === null) {
        $this->markTestSkipped('No hay superadmin o cuota corporativa sin plan en la base.');
    }

    $label = CollectionReceivableReport::planLabel($record->load(CollectionReceivableReport::corporatePlanEagerLoads()));

    Filament::setCurrentPanel('administration');
    $this->actingAs($user);

    Livewire::test(ListCollections::class)
        ->assertSuccessful()
        ->searchTable((string) $record->collection_invoice_number)
        ->assertCanSeeTableRecords([$record])
        ->assertSee($label);
});
