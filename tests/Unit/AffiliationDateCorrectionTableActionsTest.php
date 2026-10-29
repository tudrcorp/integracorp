<?php

declare(strict_types=1);

use App\Filament\Shared\Affiliations\AffiliationDateCorrectionActions;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\AffiliationDateCorrection;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * «Corregir emisión y vigencia» y «Revertir» en el menú «Acciones» de las tablas. Solo lee
 * afiliaciones reales; el único dato escrito (el usuario) queda dentro de la transacción.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    AffiliationDateCorrectionActions::forgetAppliedCorrections();
});

afterEach(function (): void {
    AffiliationDateCorrectionActions::forgetAppliedCorrections();
    DB::rollBack();
});

function dateCorrectionTableUser(array $departments): User
{
    return User::factory()->create([
        'email' => 'qa.vigencia.tabla.'.uniqid().'@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => $departments,
    ]);
}

it('pone las dos acciones dentro del menú «Acciones» de las cuatro tablas', function (string $table): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/'.$table);
    $actions = strpos($source, '...AffiliationDateCorrectionActions::tableActions(),');

    expect($actions)->toBeInt()
        ->and($actions)->toBeGreaterThan(strpos($source, 'ActionGroup::make(['))
        ->and($actions)->toBeLessThan(strpos($source, '...AffiliationDeletionActions::recordActions(),'));
})->with([
    'Negocios · corporativas' => 'Business/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php',
    'Negocios · individuales' => 'Business/Resources/Affiliations/Tables/AffiliationsTable.php',
    'Administración · corporativas' => 'Administration/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php',
    'Administración · individuales' => 'Administration/Resources/Affiliations/Tables/AffiliationsTable.php',
]);

it('ofrece corregir y revertir desde la tabla corporativa e individual de cada panel', function (string $panel, string $page, string $model): void {
    $record = $model === AffiliationCorporate::class
        ? AffiliationCorporate::query()->whereKey(
            AffiliationDateCorrection::query()->where('affiliation_type', 'CORPORATIVA')->where('status', AffiliationDateCorrection::STATUS_APPLIED)->value('affiliation_id')
        )->where('status', 'ACTIVA')->first()
        : Affiliation::query()->where('status', 'ACTIVA')->first();

    if ($record === null) {
        $this->markTestSkipped('No hay una afiliación activa para probar en esta base.');
    }

    $this->actingAs(dateCorrectionTableUser(['SUPERADMIN', 'NEGOCIOS', 'ADMINISTRACION']));
    Filament::setCurrentPanel($panel);

    $hasCorrection = AffiliationDateCorrectionActions::hasAppliedCorrection($record);

    $component = Livewire::test($page)
        ->assertActionVisible(TestAction::make('correctEffectiveDate')->table($record));

    $hasCorrection
        ? $component->assertActionVisible(TestAction::make('revertEffectiveDateCorrection')->table($record))
        : $component->assertActionHidden(TestAction::make('revertEffectiveDateCorrection')->table($record));
})->with([
    'Negocios · corporativas' => ['business', App\Filament\Business\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates::class, AffiliationCorporate::class],
    'Negocios · individuales' => ['business', App\Filament\Business\Resources\Affiliations\Pages\ListAffiliations::class, Affiliation::class],
    'Administración · corporativas' => ['administration', App\Filament\Administration\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates::class, AffiliationCorporate::class],
    'Administración · individuales' => ['administration', App\Filament\Administration\Resources\Affiliations\Pages\ListAffiliations::class, Affiliation::class],
]);

it('sin el permiso de corrección el analista no ve ninguna de las dos acciones', function (): void {
    $record = AffiliationCorporate::query()->where('status', 'ACTIVA')->first();

    if ($record === null) {
        $this->markTestSkipped('No hay una afiliación corporativa activa en esta base.');
    }

    $this->actingAs(dateCorrectionTableUser(['NEGOCIOS']));
    Filament::setCurrentPanel('business');

    foreach (AffiliationDateCorrectionActions::tableActions() as $action) {
        expect($action->record($record)->isVisible())->toBeFalse()
            ->and($action->isAuthorized())->toBeFalse();
    }
});

it('decide «Revertir» de toda la tabla con una sola consulta por tipo, no una por fila', function (): void {
    $corporates = AffiliationCorporate::query()->limit(25)->get();
    $individuals = Affiliation::query()->limit(25)->get();

    DB::flushQueryLog();
    DB::enableQueryLog();

    foreach ($corporates->concat($individuals) as $record) {
        AffiliationDateCorrectionActions::hasAppliedCorrection($record);
        AffiliationDateCorrectionActions::hasAppliedCorrection($record);
    }

    $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'affiliation_date_corrections'));
    DB::disableQueryLog();

    expect($queries)->toHaveCount(($corporates->isNotEmpty() ? 1 : 0) + ($individuals->isNotEmpty() ? 1 : 0));
});

it('olvida lo cargado tras corregir o revertir, para que el menú refleje el cambio enseguida', function (): void {
    $applied = AffiliationDateCorrection::query()->where('status', AffiliationDateCorrection::STATUS_APPLIED)->where('affiliation_type', 'CORPORATIVA')->first();

    if ($applied === null) {
        $this->markTestSkipped('No hay correcciones aplicadas en esta base.');
    }

    $record = AffiliationCorporate::query()->findOrFail($applied->affiliation_id);

    expect(AffiliationDateCorrectionActions::hasAppliedCorrection($record))->toBeTrue();

    AffiliationDateCorrection::query()->whereKey($applied->getKey())->update(['status' => 'REVERTED']);

    expect(AffiliationDateCorrectionActions::hasAppliedCorrection($record))->toBeTrue();

    AffiliationDateCorrectionActions::forgetAppliedCorrections();

    expect(AffiliationDateCorrectionActions::hasAppliedCorrection($record))->toBeFalse();
});
