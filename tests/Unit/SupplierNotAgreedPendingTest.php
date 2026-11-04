<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Operations\Resources\Suppliers\Tables\SuppliersTable;
use App\Filament\Operations\Resources\Suppliers\Widgets\SupplierStatsOverviewFirts;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Operations\SupplierListStatCounts;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Escribe en base de datos: todo ocurre dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Filament::setCurrentPanel('operations');
    test()->actingAs(User::factory()->make([
        'id' => 999_999_061,
        'name' => 'QA PROVEEDORES',
        'email' => 'qa.no-convenidos@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
        'supplier_id' => null,
    ]));
});

afterEach(fn () => DB::rollBack());

/**
 * @param  array<string, mixed>  $attributes
 */
function proveedorNoConvenidoQa(array $attributes): Supplier
{
    return Supplier::query()->create($attributes + [
        'name' => 'QA NOCONV '.uniqid(),
        'rif' => 'J-'.random_int(10000000, 99999999),
        'status_convenio' => 'NO CONVENIDO',
        'status_sistema' => 'PENDIENTE',
    ]);
}

it('cuenta solo a los no convenidos que el equipo de proveedores aún no gestionó', function (): void {
    $pending = proveedorNoConvenidoQa([]);
    $pendingLowercase = proveedorNoConvenidoQa(['status_convenio' => ' no convenido ', 'status_sistema' => null]);
    $workedStatus = proveedorNoConvenidoQa(['status_sistema' => 'EN PROCESO']);
    $workedLetter = proveedorNoConvenidoQa(['carta_acceptance' => 'carta.pdf']);
    $agreed = proveedorNoConvenidoQa(['status_convenio' => 'GENERAL', 'status_sistema' => 'AFILIADO', 'carta_acceptance' => 'carta.pdf']);

    $ids = SupplierListStatCounts::applyNotAgreedPending(Supplier::query()->where('name', 'like', 'QA NOCONV%'))->pluck('id')->all();
    $summary = SupplierListStatCounts::summary(Supplier::query()->where('name', 'like', 'QA NOCONV%'), Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());

    expect($ids)->toEqualCanonicalizing([$pending->id, $pendingLowercase->id])
        ->and($ids)->not->toContain($workedStatus->id)
        ->and($ids)->not->toContain($workedLetter->id)
        ->and($ids)->not->toContain($agreed->id)
        ->and($summary['not_agreed'])->toBe(2)
        ->and($summary['with_acceptance_letter'])->toBe(1);
});

it('sale de la tarjeta en cuanto el equipo lo actualiza (caso EL-SHADDAI)', function (): void {
    $supplier = proveedorNoConvenidoQa([]);
    $count = fn (): int => SupplierListStatCounts::summary(Supplier::query()->whereKey($supplier->id), Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth())['not_agreed'];

    expect($count())->toBe(1);

    $supplier->update(['status_convenio' => 'GENERAL', 'status_sistema' => 'AFILIADO', 'carta_acceptance' => 'carta.pdf']);

    expect($count())->toBe(0);
});

it('el filtro de la tabla muestra exactamente los de la tarjeta', function (): void {
    $pending = proveedorNoConvenidoQa([]);
    $worked = proveedorNoConvenidoQa(['status_sistema' => 'AFILIADO']);

    Livewire::test(ListSuppliers::class)
        ->filterTable(SuppliersTable::NOT_AGREED_PENDING_FILTER, true)
        ->searchTable('QA NOCONV')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$worked]);
});

it('la tarjeta lleva al listado con ese filtro aplicado', function (): void {
    $url = urldecode((string) (fn () => $this->getStats()[3]->getUrl())->call(new SupplierStatsOverviewFirts));

    expect($url)->toContain('/operations/suppliers')
        ->and($url)->toContain('filters['.SuppliersTable::NOT_AGREED_PENDING_FILTER.'][isActive]=1');
});
