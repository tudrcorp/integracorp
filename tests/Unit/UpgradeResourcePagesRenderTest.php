<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Upgrades\Pages\CreateUpgrade;
use App\Filament\Business\Resources\Upgrades\Pages\EditUpgrade;
use App\Filament\Business\Resources\Upgrades\Pages\ListUpgrades;
use App\Filament\Business\Resources\Upgrades\Pages\ViewUpgrade;
use App\Models\Upgrade;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Solo monta las pantallas (lectura): el usuario es `make()`, nunca se guarda.
 */
beforeEach(function (): void {
    if (! Schema::hasTable('upgrades')) {
        $this->markTestSkipped('La tabla upgrades no existe en esta conexión.');
    }

    Filament::setCurrentPanel('business');

    $this->actingAs(User::factory()->make([
        'id' => 999_777,
        'name' => 'Superadmin Pest',
        'email' => 'superadmin.pest@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'NEGOCIOS'],
    ]));
});

it('abre la lista, el alta, la ficha y la edición del catálogo', function (): void {
    Livewire::test(ListUpgrades::class)->assertSuccessful()->assertSee('Upgrades');
    Livewire::test(CreateUpgrade::class)->assertSuccessful()->assertSee('Coberturas y precios por edad');

    $upgrade = Upgrade::query()->orderBy('id')->first();

    if ($upgrade === null) {
        return;
    }

    Livewire::test(ViewUpgrade::class, ['record' => $upgrade->getKey()])->assertSuccessful()->assertSee($upgrade->name);
    Livewire::test(EditUpgrade::class, ['record' => $upgrade->getKey()])
        ->assertSuccessful()
        ->assertSet('data.name', $upgrade->name);
});

it('en afiliados individuales y corporativos el modal de upgrade abre sin errores', function (): void {
    $affiliate = App\Models\Affiliate::query()
        ->where('status', 'ACTIVO')
        ->whereHas('affiliation', fn ($query) => $query->where('status', 'ACTIVA'))
        ->orderByDesc('id')
        ->first();

    if ($affiliate !== null) {
        $action = TestAction::make('add_upgrade')->table($affiliate);

        Livewire::test(App\Filament\Business\Resources\Affiliations\RelationManagers\AffiliatesRelationManager::class, [
            'ownerRecord' => $affiliate->affiliation,
            'pageClass' => App\Filament\Business\Resources\Affiliations\Pages\ViewAffiliation::class,
        ])
            ->assertOk()
            ->assertActionVisible($action)
            ->mountAction($action)
            ->assertActionMounted($action)
            ->assertHasNoErrors();
    }

    $corporate = App\Models\AffiliateCorporate::query()
        ->whereHas('upgrades', fn ($query) => $query->where('status', 'ACTIVO'))
        ->first();

    if ($corporate === null) {
        return;
    }

    Livewire::test(App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\CorporateAffiliatesRelationManager::class, [
        'ownerRecord' => App\Models\AffiliationCorporate::query()->findOrFail($corporate->affiliation_corporate_id),
        'pageClass' => App\Filament\Business\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate::class,
    ])
        ->assertOk()
        ->assertActionVisible(TestAction::make('remove_upgrades')->table($corporate))
        ->mountAction(TestAction::make('add_upgrade')->table($corporate))
        ->assertActionMounted(TestAction::make('add_upgrade')->table($corporate))
        ->assertHasNoErrors();
});

it('«Agregar upgrade» exige el Voucher ILS en individual y en corporativo antes de escribir nada', function (): void {
    if (! Schema::hasColumn('affiliate_upgrades', 'ils_voucher_code')) {
        $this->markTestSkipped('Falta la columna del voucher en esta conexión.');
    }

    $coverageId = App\Models\UpgradeCoverage::query()
        ->where('status', 'ACTIVO')
        ->whereHas('upgrade', fn ($query) => $query->where('status', 'ACTIVO'))
        ->whereHas('activeAgeRanges')
        ->value('id');

    if ($coverageId === null) {
        $this->markTestSkipped('No hay upgrades activos.');
    }

    $before = [App\Models\AffiliateUpgrade::query()->count(), App\Models\AffiliateCorporateUpgrade::query()->count()];

    $affiliate = App\Models\Affiliate::query()
        ->where('status', 'ACTIVO')
        ->whereHas('affiliation', fn ($query) => $query->where('status', 'ACTIVA'))
        ->orderByDesc('id')
        ->first();

    if ($affiliate !== null) {
        $action = TestAction::make('add_upgrade')->table($affiliate);

        Livewire::test(App\Filament\Business\Resources\Affiliations\RelationManagers\AffiliatesRelationManager::class, [
            'ownerRecord' => $affiliate->affiliation,
            'pageClass' => App\Filament\Business\Resources\Affiliations\Pages\ViewAffiliation::class,
        ])
            ->mountAction($action)
            ->set('mountedActions.0.data.upgrade_coverage_id', $coverageId)
            ->callMountedAction()
            ->assertHasActionErrors(['voucher.code', 'voucher.date_init', 'voucher.date_end', 'voucher.document_path']);
    }

    $corporate = App\Models\AffiliateCorporate::query()
        ->whereIn('status', ['ACTIVO', 'PRE-APROBADA'])
        ->whereNotNull('affiliation_corporate_id')
        ->orderByDesc('id')
        ->first();

    if ($corporate !== null) {
        $action = TestAction::make('add_upgrade')->table($corporate);

        Livewire::test(App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\CorporateAffiliatesRelationManager::class, [
            'ownerRecord' => App\Models\AffiliationCorporate::query()->findOrFail($corporate->affiliation_corporate_id),
            'pageClass' => App\Filament\Business\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate::class,
        ])
            ->mountAction($action)
            ->set('mountedActions.0.data.upgrade_coverage_id', $coverageId)
            ->set('mountedActions.0.data.voucher.code', 'ILS-PRUEBA-PEST')
            ->set('mountedActions.0.data.voucher.date_init', now()->subYear()->toDateString())
            ->set('mountedActions.0.data.voucher.date_end', now()->subDay()->toDateString())
            ->callMountedAction()
            ->assertHasActionErrors(['voucher.date_end', 'voucher.document_path']);
    }

    expect([App\Models\AffiliateUpgrade::query()->count(), App\Models\AffiliateCorporateUpgrade::query()->count()])->toBe($before);
});
