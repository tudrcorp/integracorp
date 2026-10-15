<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates;
use App\Filament\Administration\Resources\Affiliations\Pages\ListAffiliations;
use App\Filament\Administration\Resources\AnnualCollections\Pages\ListAnnualCollections;
use App\Filament\Administration\Resources\Collections\Pages\ListCollections;
use App\Filament\Administration\Resources\RenovationCorporates\Pages\ListRenovationCorporates;
use App\Filament\Administration\Resources\Renovations\Pages\ListRenovations;
use App\Filament\Concerns\ReviewsTableSelection;
use App\Models\Affiliation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Las tablas solo leen, pero algunas páginas escriben al cargarse (bitácoras, caducidades):
 * todo va en una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Filament::setCurrentPanel('administration');
    $this->actingAs(User::factory()->make([
        'id' => 999999971,
        'email' => 'analista.adm@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'ADMINISTRACION'],
    ]));
});

afterEach(fn () => DB::rollBack());

dataset('tablas de administración', [
    'afiliaciones individuales' => [ListAffiliations::class, 'afiliaciones'],
    'afiliaciones corporativas' => [ListAffiliationCorporates::class, 'afiliaciones corporativas'],
    'gestión de cobranza' => [ListCollections::class, 'cuotas'],
    'cobranza por mes' => [ListAnnualCollections::class, 'cuentas por cobrar'],
    'renovaciones individuales' => [ListRenovations::class, 'renovaciones'],
    'renovaciones corporativas' => [ListRenovationCorporates::class, 'renovaciones corporativas'],
]);

/**
 * Tres registros de la primera página que la tabla muestra hoy (con su orden y filtros por defecto).
 *
 * @param  class-string  $page
 * @return list<Illuminate\Database\Eloquent\Model>
 */
function adminSelectionRecords(string $page): array
{
    $component = Livewire::test($page)->call('loadTable');
    $records = collect($component->instance()->getTableRecords()->items())->take(3)->values()->all();

    if (count($records) < 3) {
        test()->markTestSkipped('No hay suficientes registros locales en '.$page.'.');
    }

    return $records;
}

it('conserva la selección entre búsquedas y la enlaza a Livewire', function (string $page): void {
    $component = Livewire::test($page);
    $table = $component->instance()->getTable();

    expect(class_uses_recursive($component->instance()))->toHaveKey(ReviewsTableSelection::class)
        ->and($table->getCurrentSelectionLivewireProperty())->toBe('tableSelection')
        ->and($table->shouldDeselectAllRecordsWhenFiltered())->toBeFalse();
})->with('tablas de administración');

it('al dejar de buscar muestra solo lo seleccionado, con el aviso y su nombre', function (string $page, string $plural): void {
    [$first, $second, $third] = adminSelectionRecords($page);

    Livewire::test($page)
        ->call('loadTable')
        ->set('tableSearch', 'zz-sin-resultados')
        ->set('tableSelection', [(string) $first->getKey(), (string) $third->getKey()])
        ->set('tableSearch', '')
        ->assertSet('reviewingSelection', true)
        ->assertCanSeeTableRecords([$first, $third])
        ->assertCanNotSeeTableRecords([$second])
        ->assertSee('Mostrando solo las 2 '.$plural.' seleccionadas')
        ->assertSee('Ver todas las '.$plural)
        ->call('showAllWhileSelectingRecords')
        ->assertCanSeeTableRecords([$second])
        ->call('clearTableSelection')
        ->assertSet('tableSelection', [])
        ->assertDispatched('deselectAllTableRecords');
})->with('tablas de administración');

it('marcar sin haber buscado no reduce la tabla', function (string $page): void {
    [$first, $second] = adminSelectionRecords($page);

    Livewire::test($page)
        ->call('loadTable')
        ->set('tableSelection', [(string) $first->getKey()])
        ->call('gotoPage', 1)
        ->assertSet('reviewingSelection', false)
        ->assertCanSeeTableRecords([$first, $second])
        ->assertSee('Ver solo las seleccionadas');
})->with('tablas de administración');

it('en afiliaciones la vista de seleccionadas ignora la pestaña activa', function (): void {
    $active = Affiliation::query()->where('status', 'ACTIVA')->latest('id')->firstOrFail();
    $other = Affiliation::query()->where('status', '!=', 'ACTIVA')->latest('id')->firstOrFail();

    Livewire::test(ListAffiliations::class)
        ->set('activeTab', 'activa')
        ->call('loadTable')
        ->assertCanNotSeeTableRecords([$other])
        ->set('tableSearch', 'zz-sin-resultados')
        ->set('tableSelection', [(string) $active->id, (string) $other->id])
        ->set('tableSearch', '')
        ->assertCanSeeTableRecords([$active, $other]);
});

it('en cobranza por mes los totales del resumen siguen a lo seleccionado', function (): void {
    [$first, $second] = adminSelectionRecords(ListAnnualCollections::class);

    $component = Livewire::test(ListAnnualCollections::class)
        ->call('loadTable')
        ->set('tableSearch', 'zz-sin-resultados')
        ->set('tableSelection', [(string) $first->getKey()])
        ->set('tableSearch', '');

    expect($component->instance()->getFilteredTableQuery()->pluck($first->getKeyName())->all())->toBe([$first->getKey()]);
});

it('cobranza por mes exporta en CSV solo las cuentas seleccionadas', function (): void {
    [$first, $second] = adminSelectionRecords(ListAnnualCollections::class);

    $component = Livewire::test(ListAnnualCollections::class)->call('loadTable');
    $component->instance()->selectedTableRecords = [(string) $first->getKey(), (string) $second->getKey()];
    $query = $component->instance()->getSelectedTableRecordsQuery(shouldFetchSelectedRecords: false);

    expect($query->pluck($first->getKeyName())->sort()->values()->all())
        ->toBe(collect([$first->getKey(), $second->getKey()])->sort()->values()->all());

    $component->callTableBulkAction('exportSelectedReceivables', [$first, $second])
        ->assertHasNoErrors()
        ->assertFileDownloaded();
});
