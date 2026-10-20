<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicineDoctors\Pages\CreateTelemedicineDoctor;
use App\Filament\Operations\Resources\TelemedicineDoctors\Pages\EditTelemedicineDoctor;
use App\Models\Supplier;
use App\Models\TelemedicineDoctor;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineDoctorSupplierAssignment;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Estos tests escriben (médico + usuario), así que corren dentro de una
 * transacción que siempre se revierte. No se deja nada en la base.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    $suppliers = Supplier::query()->whereNotNull('name')->where('name', '!=', '')->orderBy('id')->limit(3)->get();

    if ($suppliers->count() < 3) {
        $this->markTestSkipped('La base no tiene al menos tres proveedores.');
    }

    [$this->supplierA, $this->supplierB, $this->supplierSinGestion] = [$suppliers[0], $suppliers[1], $suppliers[2]];

    // Dentro de la transacción revertida: A y B con gestión en IntegraCorp, el tercero sin ella.
    Supplier::query()->whereKey([$this->supplierA->id, $this->supplierB->id])->update(['gestion_integracorp' => true]);
    Supplier::query()->whereKey($this->supplierSinGestion->id)->update(['gestion_integracorp' => false]);

    Filament::setCurrentPanel('operations');
});

afterEach(fn () => DB::rollBack());

function actingAsTdgAnalyst(): User
{
    $user = User::factory()->create([
        'email' => 'qa.analista.'.Str::lower(Str::random(8)).'@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
        'supplier_id' => null,
        'is_proveedor_amd' => false,
    ]);

    test()->actingAs($user);

    return $user;
}

function actingAsSupplierUser(Supplier $supplier): User
{
    $user = User::factory()->create([
        'email' => 'qa.proveedor.'.Str::lower(Str::random(8)).'@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
        'supplier_id' => $supplier->id,
    ]);

    test()->actingAs($user);

    return $user;
}

function doctorData(array $overrides = []): array
{
    $seed = (string) random_int(10_000_000, 99_999_999);

    return array_merge([
        'full_name' => 'MEDICO QA '.$seed,
        'nro_identificacion' => $seed,
        'country_code' => '+58',
        'phone' => '+58412'.substr($seed, 0, 7),
        'email' => 'qa.medico.'.$seed.'@correo.test',
        'specialty' => 'MÉDICO GENERAL',
        'code_cm' => '9'.$seed,
        'code_mpps' => '8'.$seed,
        'status' => 'ACTIVO',
    ], $overrides);
}

function fillDoctorForm(mixed $component, array $data): mixed
{
    foreach ($data as $key => $value) {
        $component->set('data.'.$key, $value);
    }

    return $component;
}

it('deja elegir el proveedor solo al analista de TDG', function (): void {
    actingAsTdgAnalyst();
    expect(TelemedicineDoctorSupplierAssignment::canChooseSupplier())->toBeTrue()
        ->and(TelemedicineDoctorSupplierAssignment::defaultManagedBy())->toBe('TDG');

    actingAsSupplierUser($this->supplierA);
    expect(TelemedicineDoctorSupplierAssignment::canChooseSupplier())->toBeFalse()
        ->and(TelemedicineDoctorSupplierAssignment::defaultManagedBy())->toBe(mb_strtoupper(trim($this->supplierA->name)));

    Auth::setUser(User::factory()->make(['supplier_id' => null, 'is_proveedor_amd' => true]));
    expect(TelemedicineDoctorSupplierAssignment::canChooseSupplier())->toBeFalse()
        ->and(TelemedicineDoctorSupplierAssignment::defaultManagedBy())->toBeNull();
});

it('resuelve «Pertenece a» desde el proveedor elegido', function (): void {
    expect(TelemedicineDoctorSupplierAssignment::managedByForSupplier(null))->toBe('TDG')
        ->and(TelemedicineDoctorSupplierAssignment::managedByForSupplier(''))->toBe('TDG')
        ->and(TelemedicineDoctorSupplierAssignment::managedByForSupplier(PHP_INT_MAX))->toBe('TDG')
        ->and(TelemedicineDoctorSupplierAssignment::managedByForSupplier((string) $this->supplierA->id))
        ->toBe(mb_strtoupper(trim($this->supplierA->name)));
});

it('ignora en el servidor un proveedor manipulado por un usuario de proveedor', function (): void {
    actingAsSupplierUser($this->supplierA);

    $alta = TelemedicineDoctorSupplierAssignment::enforce(['supplier_id' => $this->supplierB->id, 'managed_by' => ' alias ']);
    expect($alta['supplier_id'])->toBe($this->supplierA->id)
        ->and($alta['managed_by'])->toBe('ALIAS');

    $record = new TelemedicineDoctor(['supplier_id' => $this->supplierA->id]);
    $edicion = TelemedicineDoctorSupplierAssignment::enforce(['supplier_id' => $this->supplierB->id], $record);
    expect($edicion['supplier_id'])->toBe($this->supplierA->id);
});

it('respeta el proveedor elegido por el analista, o ninguno', function (): void {
    actingAsTdgAnalyst();

    expect(TelemedicineDoctorSupplierAssignment::enforce(['supplier_id' => (string) $this->supplierB->id])['supplier_id'])
        ->toBe($this->supplierB->id)
        ->and(TelemedicineDoctorSupplierAssignment::enforce(['supplier_id' => ''])['supplier_id'])->toBeNull()
        ->and(TelemedicineDoctorSupplierAssignment::enforce([])['supplier_id'])->toBeNull();
});

it('el analista crea un médico de proveedor y su usuario queda en ese proveedor', function (): void {
    actingAsTdgAnalyst();
    $data = doctorData();

    fillDoctorForm(Livewire::test(CreateTelemedicineDoctor::class), $data)
        ->assertSet('data.managed_by', 'TDG')
        ->set('data.supplier_id', $this->supplierB->id)
        ->assertSet('data.managed_by', mb_strtoupper(trim($this->supplierB->name)))
        ->call('create')
        ->assertHasNoFormErrors();

    $doctor = TelemedicineDoctor::query()->where('email', $data['email'])->firstOrFail();

    expect($doctor->supplier_id)->toBe($this->supplierB->id)
        ->and($doctor->managed_by)->toBe(mb_strtoupper(trim($this->supplierB->name)))
        ->and(User::query()->where('doctor_id', $doctor->id)->value('supplier_id'))->toBe($this->supplierB->id);
});

it('el analista crea un médico de TDG sin proveedor', function (): void {
    actingAsTdgAnalyst();
    $data = doctorData();

    fillDoctorForm(Livewire::test(CreateTelemedicineDoctor::class), $data)
        ->call('create')
        ->assertHasNoFormErrors();

    $doctor = TelemedicineDoctor::query()->where('email', $data['email'])->firstOrFail();

    expect($doctor->supplier_id)->toBeNull()
        ->and($doctor->managed_by)->toBe('TDG');
});

it('exige proveedor si «Pertenece a» no es TDG, y rechaza TDG con proveedor', function (): void {
    actingAsTdgAnalyst();

    fillDoctorForm(Livewire::test(CreateTelemedicineDoctor::class), doctorData(['managed_by' => 'CLINICA QA']))
        ->call('create')
        ->assertHasFormErrors(['supplier_id' => 'required']);

    fillDoctorForm(Livewire::test(CreateTelemedicineDoctor::class), doctorData())
        ->set('data.supplier_id', $this->supplierA->id)
        ->set('data.managed_by', 'TDG')
        ->call('create')
        ->assertHasFormErrors(['managed_by']);
});

it('al cambiar el proveedor en la edición se actualiza la cuenta del médico', function (): void {
    actingAsTdgAnalyst();

    $doctor = TelemedicineDoctor::query()->forceCreate(collect(doctorData([
        'supplier_id' => $this->supplierA->id,
        'managed_by' => mb_strtoupper(trim($this->supplierA->name)),
    ]))->except('country_code')->all());

    $doctorUser = User::factory()->create([
        'email' => $doctor->email,
        'doctor_id' => $doctor->id,
        'supplier_id' => $this->supplierA->id,
    ]);

    Livewire::test(EditTelemedicineDoctor::class, ['record' => $doctor->getRouteKey()])
        ->set('data.supplier_id', $this->supplierB->id)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($doctor->refresh()->supplier_id)->toBe($this->supplierB->id)
        ->and($doctorUser->refresh()->supplier_id)->toBe($this->supplierB->id);
});

it('un usuario de proveedor no puede mover su médico a otro proveedor', function (): void {
    actingAsSupplierUser($this->supplierA);

    $doctor = TelemedicineDoctor::query()->forceCreate(collect(doctorData([
        'supplier_id' => $this->supplierA->id,
        'managed_by' => mb_strtoupper(trim($this->supplierA->name)),
    ]))->except('country_code')->all());

    Livewire::test(EditTelemedicineDoctor::class, ['record' => $doctor->getRouteKey()])
        ->set('data.supplier_id', $this->supplierB->id)
        ->call('save');

    expect($doctor->refresh()->supplier_id)->toBe($this->supplierA->id);
});

it('la lista de proveedores solo trae los que tienen gestión activa en IntegraCorp', function (): void {
    actingAsTdgAnalyst();

    $ids = TelemedicineDoctorSupplierAssignment::scopeAssignableSuppliers(Supplier::query())->pluck('id');

    expect($ids)->toContain($this->supplierA->id)
        ->toContain($this->supplierB->id)
        ->not->toContain($this->supplierSinGestion->id)
        ->and($ids->count())->toBe(Supplier::query()->where('gestion_integracorp', true)->count());
});

it('mantiene visible el proveedor actual del médico aunque ya no tenga gestión', function (): void {
    actingAsTdgAnalyst();

    $record = new TelemedicineDoctor(['supplier_id' => $this->supplierSinGestion->id]);

    expect(TelemedicineDoctorSupplierAssignment::scopeAssignableSuppliers(Supplier::query(), $record)->pluck('id'))
        ->toContain($this->supplierSinGestion->id)
        ->and(TelemedicineDoctorSupplierAssignment::isAssignable($this->supplierSinGestion->id, $record))->toBeTrue()
        ->and(TelemedicineDoctorSupplierAssignment::isAssignable($this->supplierSinGestion->id))->toBeFalse()
        ->and(TelemedicineDoctorSupplierAssignment::isAssignable(PHP_INT_MAX))->toBeFalse()
        ->and(TelemedicineDoctorSupplierAssignment::isAssignable(null))->toBeTrue();
});

it('rechaza al guardar un proveedor sin gestión aunque se envíe a mano', function (): void {
    actingAsTdgAnalyst();
    $data = doctorData();

    fillDoctorForm(Livewire::test(CreateTelemedicineDoctor::class), $data)
        ->set('data.supplier_id', $this->supplierSinGestion->id)
        ->call('create')
        ->assertHasFormErrors(['supplier_id']);

    expect(TelemedicineDoctor::query()->where('email', $data['email'])->exists())->toBeFalse();
});

it('editar un médico cuyo proveedor apagó la gestión conserva su proveedor', function (): void {
    actingAsTdgAnalyst();

    $doctor = TelemedicineDoctor::query()->forceCreate(collect(doctorData([
        'supplier_id' => $this->supplierSinGestion->id,
        'managed_by' => mb_strtoupper(trim($this->supplierSinGestion->name)),
    ]))->except('country_code')->all());

    Livewire::test(EditTelemedicineDoctor::class, ['record' => $doctor->getRouteKey()])
        ->assertSet('data.supplier_id', $this->supplierSinGestion->id)
        ->set('data.specialty', 'MEDICINA INTERNA')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($doctor->refresh()->supplier_id)->toBe($this->supplierSinGestion->id)
        ->and($doctor->specialty)->toBe('MEDICINA INTERNA');
});
