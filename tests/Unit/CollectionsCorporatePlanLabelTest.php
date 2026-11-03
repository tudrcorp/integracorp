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

it('la población sale de la afiliación y la copia de la cuota queda de respaldo', function (): void {
    $corporate = new AffiliationCorporate;
    $corporate->forceFill(['poblation' => 28]);

    $record = new Collection;
    $record->forceFill(['type' => 'AFILIACION CORPORATIVA', 'persons' => '30']);
    $record->setRelation('affiliationCorporateByCode', $corporate);
    $record->setRelation('affiliationByCode', null);

    expect(CollectionReceivableReport::affiliationPopulation($record))->toBe(28);

    $individual = new App\Models\Affiliation;
    $individual->forceFill(['family_members' => 3]);

    $record = new Collection;
    $record->forceFill(['type' => 'AFILIACION INDIVIDUAL', 'persons' => '1']);
    $record->setRelation('affiliationByCode', $individual);
    $record->setRelation('affiliationCorporateByCode', null);

    expect(CollectionReceivableReport::affiliationPopulation($record))->toBe(3);

    $record->setRelation('affiliationByCode', null);

    expect(CollectionReceivableReport::affiliationPopulation($record))->toBe(1);

    $record->forceFill(['persons' => null]);

    expect(CollectionReceivableReport::affiliationPopulation($record))->toBeNull();
});

it('las dos tablas de cobranza, el CSV y el Excel muestran la población de la afiliación', function (string $path): void {
    expect(file_get_contents(app_path($path)))->toContain('CollectionReceivableReport::affiliationPopulation($record)');
})->with([
    'Gestión de Cobranza' => ['Filament/Administration/Resources/Collections/Tables/CollectionsTable.php'],
    'Cobranza Por Mes' => ['Filament/Administration/Resources/AnnualCollections/Tables/AnnualCollectionsTable.php'],
    'CSV' => ['Support/Exports/CollectionReceivableCsvExportService.php'],
    'Excel' => ['Support/Exports/CollectionReceivableXlsxExportService.php'],
]);

it('Cobranza Por Mes se dibuja con la población vigente de Casalbeach', function (): void {
    $user = User::query()->where('departament', 'like', '%SUPERADMIN%')->where('status', 'ACTIVO')->first();
    $record = App\Filament\Administration\Resources\AnnualCollections\AnnualCollectionResource::getEloquentQuery()
        ->with(CollectionReceivableReport::eagerLoads())
        ->where('affiliation_code', 'TDEC-COR-00056')
        ->first();

    if ($user === null || $record === null) {
        $this->markTestSkipped('No está la cuota de Casalbeach en esta base.');
    }

    Filament::setCurrentPanel('administration');
    $this->actingAs($user);

    Livewire::test(App\Filament\Administration\Resources\AnnualCollections\Pages\ListAnnualCollections::class)
        ->assertSuccessful()
        ->searchTable('TDEC-COR-00056')
        ->assertCanSeeTableRecords([$record])
        ->assertTableColumnStateSet('persons', CollectionReceivableReport::affiliationPopulation($record), $record);
});
