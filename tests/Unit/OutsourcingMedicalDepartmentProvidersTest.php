<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\DoctorNurses\Pages\CreateDoctorNurse;
use App\Filament\Operations\Resources\DoctorNurses\Pages\ListDoctorNurses;
use App\Filament\Operations\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Operations\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\DoctorNurse;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Operations\OutsourcingMedicalDepartment;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

/**
 * `fillForm()` no escribe nada cuando la config cacheada deja `APP_ENV=local`
 * (`runningUnitTests()` es falso); `set()` sí recorre los hooks del formulario.
 *
 * @param  array<string, mixed>  $state
 */
function fillOutsourcingProviderForm(mixed $component, array $state): mixed
{
    foreach ($state as $key => $value) {
        $component->set('data.'.$key, $value);
    }

    return $component;
}

function actingAsOutsourcingOperationsAnalyst(): void
{
    $usuario = User::factory()->create([
        'email' => 'qa.outsourcing.medico@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]);

    test()->actingAs($usuario);
    Filament::setCurrentPanel('operations');
}

/*
 * ---------------------------------------------------------------------------
 * Regla de dominio
 * ---------------------------------------------------------------------------
 */

it('descarta la tarifa de un proveedor que no es outsourcing', function (string $modelClass): void {
    $proveedor = new $modelClass([
        OutsourcingMedicalDepartment::FLAG_COLUMN => false,
        OutsourcingMedicalDepartment::FEE_COLUMN => 15,
    ]);

    OutsourcingMedicalDepartment::normalize($proveedor);

    expect($proveedor->getAttribute(OutsourcingMedicalDepartment::FEE_COLUMN))->toBeNull()
        ->and($proveedor->getAttribute(OutsourcingMedicalDepartment::FLAG_COLUMN))->toBeFalse();
})->with([
    'natural' => [DoctorNurse::class],
    'jurídico' => [Supplier::class],
]);

it('conserva la tarifa de un proveedor outsourcing', function (): void {
    $proveedor = new Supplier([
        OutsourcingMedicalDepartment::FLAG_COLUMN => true,
        OutsourcingMedicalDepartment::FEE_COLUMN => '12.5',
    ]);

    OutsourcingMedicalDepartment::normalize($proveedor);

    expect($proveedor->getAttribute(OutsourcingMedicalDepartment::FEE_COLUMN))->toBe('12.50');
});

it('trata un indicador nulo como «no outsourcing»', function (): void {
    $proveedor = new DoctorNurse([OutsourcingMedicalDepartment::FEE_COLUMN => 9]);

    OutsourcingMedicalDepartment::normalize($proveedor);

    expect($proveedor->getAttribute(OutsourcingMedicalDepartment::FLAG_COLUMN))->toBeFalse()
        ->and($proveedor->getAttribute(OutsourcingMedicalDepartment::FEE_COLUMN))->toBeNull();
});

it('formatea la tarifa en dólares con el estilo del sistema', function (): void {
    expect(OutsourcingMedicalDepartment::formatFee('1234.5'))->toBe('US$ 1.234,50')
        ->and(OutsourcingMedicalDepartment::formatFee(null))->toBeNull()
        ->and(OutsourcingMedicalDepartment::formatFee(''))->toBeNull()
        ->and(OutsourcingMedicalDepartment::formatFee('abc'))->toBeNull();
});

it('resume la condición outsourcing para la tabla', function (): void {
    expect(OutsourcingMedicalDepartment::summary(new Supplier([
        OutsourcingMedicalDepartment::FLAG_COLUMN => true,
        OutsourcingMedicalDepartment::FEE_COLUMN => 20,
    ])))->toBe('US$ 20,00 / afiliado / mes')
        ->and(OutsourcingMedicalDepartment::summary(new Supplier))->toBe('No');
});

/*
 * ---------------------------------------------------------------------------
 * Persistencia: el modelo aplica la regla en cualquier punto de entrada
 * ---------------------------------------------------------------------------
 */

it('al guardar un proveedor natural sin outsourcing no persiste tarifa', function (): void {
    $proveedor = DoctorNurse::query()->create([
        'name' => 'QA OUTSOURCING NATURAL',
        'rif' => 'V-00000001-0',
        OutsourcingMedicalDepartment::FLAG_COLUMN => true,
        OutsourcingMedicalDepartment::FEE_COLUMN => 18,
    ]);

    $proveedor->update([OutsourcingMedicalDepartment::FLAG_COLUMN => false]);

    expect($proveedor->fresh()->getAttribute(OutsourcingMedicalDepartment::FEE_COLUMN))->toBeNull();
});

/*
 * ---------------------------------------------------------------------------
 * Formularios reales del panel Operaciones
 * ---------------------------------------------------------------------------
 */

it('crea un proveedor natural outsourcing con su tarifa mensual', function (): void {
    actingAsOutsourcingOperationsAnalyst();

    fillOutsourcingProviderForm(Livewire::test(CreateDoctorNurse::class), [
        'name' => 'QA OUTSOURCING CREAR',
        'rif' => 'V-00000002-0',
        OutsourcingMedicalDepartment::FLAG_COLUMN => true,
        OutsourcingMedicalDepartment::FEE_COLUMN => '12.50',
    ])
        ->call('create')
        ->assertHasNoFormErrors();

    $proveedor = DoctorNurse::query()->where('rif', 'V-00000002-0')->firstOrFail();

    expect($proveedor->getAttribute(OutsourcingMedicalDepartment::FLAG_COLUMN))->toBeTrue()
        ->and($proveedor->getAttribute(OutsourcingMedicalDepartment::FEE_COLUMN))->toBe('12.50');
});

it('exige una tarifa válida cuando el proveedor es outsourcing', function (mixed $tarifa, string $regla): void {
    actingAsOutsourcingOperationsAnalyst();

    fillOutsourcingProviderForm(Livewire::test(CreateDoctorNurse::class), [
        'name' => 'QA OUTSOURCING INVALIDO',
        'rif' => 'V-00000003-0',
        OutsourcingMedicalDepartment::FLAG_COLUMN => true,
        OutsourcingMedicalDepartment::FEE_COLUMN => $tarifa,
    ])
        ->call('create')
        ->assertHasFormErrors([OutsourcingMedicalDepartment::FEE_COLUMN => $regla]);

    expect(DoctorNurse::query()->where('rif', 'V-00000003-0')->exists())->toBeFalse();
})->with([
    'vacía' => [null, 'required'],
    'cero' => ['0', 'min'],
    'negativa' => ['-5', 'min'],
    'tres decimales' => ['10.555', 'decimal'],
]);

it('oculta la tarifa y no la exige si el proveedor no es outsourcing', function (): void {
    actingAsOutsourcingOperationsAnalyst();

    Livewire::test(CreateDoctorNurse::class)
        ->assertFormFieldHidden(OutsourcingMedicalDepartment::FEE_COLUMN)
        ->set('data.'.OutsourcingMedicalDepartment::FLAG_COLUMN, true)
        ->assertFormFieldVisible(OutsourcingMedicalDepartment::FEE_COLUMN)
        ->set('data.'.OutsourcingMedicalDepartment::FEE_COLUMN, '40')
        ->set('data.name', 'QA SIN OUTSOURCING')
        ->set('data.rif', 'V-00000004-0')
        ->set('data.'.OutsourcingMedicalDepartment::FLAG_COLUMN, false)
        ->assertFormFieldHidden(OutsourcingMedicalDepartment::FEE_COLUMN)
        ->assertSet('data.'.OutsourcingMedicalDepartment::FEE_COLUMN, null)
        ->call('create')
        ->assertHasNoFormErrors();

    $proveedor = DoctorNurse::query()->where('rif', 'V-00000004-0')->firstOrFail();

    expect($proveedor->getAttribute(OutsourcingMedicalDepartment::FLAG_COLUMN))->toBeFalse()
        ->and($proveedor->getAttribute(OutsourcingMedicalDepartment::FEE_COLUMN))->toBeNull();
});

it('al desactivar outsourcing en un proveedor jurídico se borra su tarifa', function (): void {
    actingAsOutsourcingOperationsAnalyst();

    $proveedor = Supplier::query()->create([
        'name' => 'QA CLINICA OUTSOURCING',
        OutsourcingMedicalDepartment::FLAG_COLUMN => true,
        OutsourcingMedicalDepartment::FEE_COLUMN => 30,
    ]);

    Livewire::test(EditSupplier::class, ['record' => $proveedor->getKey()])
        ->assertFormSet([
            OutsourcingMedicalDepartment::FLAG_COLUMN => true,
        ])
        ->set('data.'.OutsourcingMedicalDepartment::FLAG_COLUMN, false)
        ->assertSet('data.'.OutsourcingMedicalDepartment::FEE_COLUMN, null)
        ->call('save')
        ->assertHasNoFormErrors();

    $proveedor->refresh();

    expect($proveedor->getAttribute(OutsourcingMedicalDepartment::FLAG_COLUMN))->toBeFalse()
        ->and($proveedor->getAttribute(OutsourcingMedicalDepartment::FEE_COLUMN))->toBeNull();
});

/*
 * ---------------------------------------------------------------------------
 * Tablas: columna y filtro
 * ---------------------------------------------------------------------------
 */

it('filtra solo los proveedores naturales outsourcing', function (): void {
    actingAsOutsourcingOperationsAnalyst();

    $outsourcing = DoctorNurse::query()->create([
        'name' => 'QA FILTRO SI',
        'rif' => 'V-00000005-0',
        OutsourcingMedicalDepartment::FLAG_COLUMN => true,
        OutsourcingMedicalDepartment::FEE_COLUMN => 11,
    ]);
    $regular = DoctorNurse::query()->create([
        'name' => 'QA FILTRO NO',
        'rif' => 'V-00000006-0',
    ]);

    Livewire::test(ListDoctorNurses::class)
        ->searchTable('QA FILTRO')
        ->filterTable(OutsourcingMedicalDepartment::FLAG_COLUMN, true)
        ->assertCanSeeTableRecords([$outsourcing])
        ->assertCanNotSeeTableRecords([$regular])
        ->filterTable(OutsourcingMedicalDepartment::FLAG_COLUMN, false)
        ->assertCanSeeTableRecords([$regular])
        ->assertCanNotSeeTableRecords([$outsourcing]);
});

it('filtra y muestra la tarifa en la tabla de proveedores jurídicos pese a sus joins', function (): void {
    actingAsOutsourcingOperationsAnalyst();

    $outsourcing = Supplier::query()->create([
        'name' => 'QA JURIDICO SI',
        OutsourcingMedicalDepartment::FLAG_COLUMN => true,
        OutsourcingMedicalDepartment::FEE_COLUMN => 25,
    ]);
    $regular = Supplier::query()->create(['name' => 'QA JURIDICO NO']);

    Livewire::test(ListSuppliers::class)
        ->searchTable('QA JURIDICO')
        ->filterTable(OutsourcingMedicalDepartment::FLAG_COLUMN, true)
        ->assertCanSeeTableRecords([$outsourcing])
        ->assertCanNotSeeTableRecords([$regular])
        ->assertTableColumnFormattedStateSet(OutsourcingMedicalDepartment::FLAG_COLUMN, 'US$ 25,00 / afiliado / mes', $outsourcing)
        ->sortTable(OutsourcingMedicalDepartment::FLAG_COLUMN, 'desc')
        ->assertSuccessful();
});
