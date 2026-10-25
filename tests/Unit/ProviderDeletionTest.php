<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\CorporateAllies\Pages\ListCorporateAllies;
use App\Filament\Operations\Resources\DoctorNurses\Pages\ListDoctorNurses;
use App\Filament\Operations\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\CorporateAlly;
use App\Models\DoctorNurse;
use App\Models\OperationCoordinationService;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Operations\ProviderDeletion;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Escribe en base de datos: todo ocurre dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Filament::setCurrentPanel('operations');
    actuarComoGestorDeProveedores(['SUPERADMIN', 'OPERACIONES']);
});

afterEach(fn () => DB::rollBack());

function actuarComoGestorDeProveedores(array $departments): void
{
    test()->actingAs(User::factory()->make([
        'id' => 999_999_051,
        'name' => 'QA PROVEEDORES',
        'email' => 'qa.proveedores@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => $departments,
        'supplier_id' => null,
    ]));
}

function proveedorJuridico(): Supplier
{
    return Supplier::query()->create(['name' => 'QA JURIDICO '.uniqid(), 'rif' => 'J-'.random_int(10000000, 99999999)]);
}

function proveedorNatural(): DoctorNurse
{
    return DoctorNurse::query()->create(['name' => 'QA NATURAL '.uniqid(), 'rif' => 'V-'.random_int(10000000, 99999999)]);
}

function aliadoCorporativo(): CorporateAlly
{
    $ally = new CorporateAlly;
    $ally->forceFill(['company_name' => 'QA ALIADO '.uniqid(), 'country_id' => (int) DB::table('countries')->value('id')])->save();

    return $ally;
}

function servicioMedicoCon(array $attributes): OperationCoordinationService
{
    return OperationCoordinationService::query()->withoutGlobalScopes()->create($attributes + [
        'status' => 'PENDIENTE',
        'ci_patient' => 'V-00000000',
        'contractor' => 'TDG',
        'relationship_patient' => 'TITULAR',
        'symptoms_diagnosis' => 'PRUEBA',
        'created_by' => 'TEST',
    ]);
}

it('cada referencia apunta a una tabla y columna que existen', function (): void {
    foreach (ProviderDeletion::types() as $type) {
        foreach (ProviderDeletion::references($type) as [$table, $column]) {
            expect(Schema::hasColumn($table, $column))->toBeTrue("Falta {$table}.{$column}");
        }
    }
});

it('registra el permiso propio solo en Operaciones', function (): void {
    expect(BusinessFilamentActionPermissionRegistry::all())->toHaveKey(BusinessFilamentActionPermissionRegistry::DELETE_PROVIDERS)
        ->and(BusinessFilamentActionPermissionRegistry::all()[BusinessFilamentActionPermissionRegistry::DELETE_PROVIDERS]['modules'])->toBe(['OPERACIONES']);
});

it('elimina sin borrar y restaura un proveedor sin servicios, con traza', function (): void {
    $supplier = proveedorJuridico();

    ProviderDeletion::delete($supplier, 'Registro duplicado cargado por error');

    $deleted = Supplier::withTrashed()->find($supplier->id);

    expect(Supplier::query()->find($supplier->id))->toBeNull()
        ->and($deleted->trashed())->toBeTrue()
        ->and($deleted->deleted_by_name)->toBe('QA PROVEEDORES')
        ->and($deleted->deletion_reason)->toBe('Registro duplicado cargado por error');

    ProviderDeletion::restore($deleted, 'Se eliminó por error, vuelve a trabajar');

    $restored = Supplier::query()->find($supplier->id);

    expect($restored)->not->toBeNull()
        ->and($restored->deleted_by_name)->toBeNull()
        ->and($restored->deletion_reason)->toBeNull()
        ->and(DB::table('supplier_observacions')->where('supplier_id', $supplier->id)->pluck('observation')->all())
        ->toBe(['PROVEEDOR ELIMINADO · Motivo: Registro duplicado cargado por error', 'PROVEEDOR RESTAURADO · Motivo: Se eliminó por error, vuelve a trabajar']);
});

it('bloquea al proveedor con cualquier servicio, también histórico', function (): void {
    $supplier = proveedorJuridico();
    servicioMedicoCon(['supplier_id' => $supplier->id, 'status' => 'FINALIZADO']);
    servicioMedicoCon(['assigned_supplier_id' => $supplier->id]);

    expect(ProviderDeletion::usageFor(ProviderDeletion::TYPE_SUPPLIER, [$supplier->id]))
        ->toBe([$supplier->id => ['servicios médicos' => 2]])
        ->and(fn () => ProviderDeletion::delete($supplier, 'Intento de eliminar con historial'))
        ->toThrow(InvalidArgumentException::class, 'tiene 2 servicios médicos');

    expect(Supplier::query()->find($supplier->id))->not->toBeNull();
});

it('bloquea al proveedor jurídico con usuarios del portal', function (): void {
    $supplier = proveedorJuridico();
    User::factory()->create(['supplier_id' => $supplier->id, 'email' => 'qa.portal.'.uniqid().'@example.com']);

    expect(fn () => ProviderDeletion::delete($supplier, 'Intento con usuario del portal'))
        ->toThrow(InvalidArgumentException::class, 'usuarios del portal');
});

it('valida a naturales y aliados por sus propias referencias', function (): void {
    $natural = proveedorNatural();
    $ally = aliadoCorporativo();
    servicioMedicoCon(['assigned_doctor_nurse_id' => $natural->id]);
    servicioMedicoCon(['assigned_corporate_ally_id' => $ally->id]);

    expect(ProviderDeletion::usageFor(ProviderDeletion::TYPE_DOCTOR_NURSE, [$natural->id]))->toBe([$natural->id => ['servicios médicos' => 1]])
        ->and(ProviderDeletion::usageFor(ProviderDeletion::TYPE_CORPORATE_ALLY, [$ally->id]))->toBe([$ally->id => ['servicios médicos' => 1]]);
});

it('rechaza motivos cortos, doble eliminación y restaurar algo vigente', function (): void {
    $supplier = proveedorJuridico();

    expect(fn () => ProviderDeletion::delete($supplier, 'corto'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ProviderDeletion::restore($supplier, 'No estaba eliminado'))->toThrow(InvalidArgumentException::class);

    ProviderDeletion::delete($supplier, 'Primera eliminación válida');

    expect(fn () => ProviderDeletion::delete(Supplier::withTrashed()->find($supplier->id), 'Segunda eliminación'))
        ->toThrow(InvalidArgumentException::class, 'ya está eliminado');
});

it('elimina y restaura proveedores jurídicos desde su tabla', function (): void {
    $supplier = proveedorJuridico();

    Livewire::test(ListSuppliers::class)
        ->searchTable($supplier->name)
        ->assertCanSeeTableRecords([$supplier])
        ->mountAction(TestAction::make('delete_provider')->table($supplier))
        ->set('mountedActions.0.data.deletion_reason', 'Registro duplicado en la prueba')
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertCanNotSeeTableRecords([$supplier]);

    $deleted = Supplier::withTrashed()->find($supplier->id);

    Livewire::test(ListSuppliers::class)
        ->filterTable(ProviderDeletion::DELETED_FILTER, true)
        ->searchTable($supplier->name)
        ->assertCanSeeTableRecords([$deleted])
        ->assertActionHidden(TestAction::make('delete_provider')->table($deleted))
        ->mountAction(TestAction::make('restore_provider')->table($deleted))
        ->set('mountedActions.0.data.restoration_reason', 'Restaurado tras la prueba')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(Supplier::query()->find($supplier->id))->not->toBeNull();
});

it('la eliminación masiva omite a los que tienen servicios y elimina al resto', function (): void {
    $free = proveedorNatural();
    $busy = proveedorNatural();
    servicioMedicoCon(['assigned_doctor_nurse_id' => $busy->id]);

    Livewire::test(ListDoctorNurses::class)
        ->searchTable('QA NATURAL')
        ->selectTableRecords([$free->getKey(), $busy->getKey()])
        ->mountAction(TestAction::make('delete_providers')->table()->bulk())
        ->set('mountedActions.0.data.deletion_reason', 'Limpieza de registros de prueba')
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Eliminación completada');

    expect(DoctorNurse::query()->find($free->id))->toBeNull()
        ->and(DoctorNurse::query()->find($busy->id))->not->toBeNull();
});

it('elimina un aliado corporativo desde su tabla', function (): void {
    $ally = aliadoCorporativo();

    Livewire::test(ListCorporateAllies::class)
        ->searchTable($ally->company_name)
        ->mountAction(TestAction::make('delete_provider')->table($ally))
        ->set('mountedActions.0.data.deletion_reason', 'Aliado registrado por error')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(CorporateAlly::query()->find($ally->id))->toBeNull()
        ->and(CorporateAlly::withTrashed()->find($ally->id)?->trashed())->toBeTrue();
});

it('sin el permiso no se ofrece eliminar, restaurar ni la vista de eliminados', function (): void {
    actuarComoGestorDeProveedores(['OPERACIONES']);
    $supplier = proveedorJuridico();

    expect(ProviderDeletion::userCan())->toBeFalse()
        ->and(ProviderDeletion::makeDeleteAction(ProviderDeletion::TYPE_SUPPLIER)->record($supplier)->isVisible())->toBeFalse()
        ->and(ProviderDeletion::makeRestoreAction(ProviderDeletion::TYPE_SUPPLIER)->record($supplier)->isVisible())->toBeFalse()
        ->and(ProviderDeletion::makeDeleteBulkAction(ProviderDeletion::TYPE_SUPPLIER)->isVisible())->toBeFalse()
        ->and(ProviderDeletion::makeDeletedFilter()->isVisible())->toBeFalse();
});

it('los listados crudos de proveedores excluyen a los eliminados', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Services/SupplierReportPdfService.php'))->toContain("->whereNull('suppliers.deleted_at')")
        ->and(substr_count(file_get_contents(dirname(__DIR__, 2).'/app/Services/NotificationMasiveService.php'), "DB::table('suppliers')\n                                ->whereNull('deleted_at')"))->toBe(2);
});

it('la ventana de eliminar muestra confirmar y cancelar, no dos «Eliminar»', function (): void {
    $supplier = proveedorJuridico();

    foreach ([
        [ProviderDeletion::makeDeleteAction(ProviderDeletion::TYPE_SUPPLIER)->record($supplier), 'Sí, eliminar'],
        [ProviderDeletion::makeDeleteBulkAction(ProviderDeletion::TYPE_SUPPLIER), 'Sí, eliminar'],
        [ProviderDeletion::makeRestoreAction(ProviderDeletion::TYPE_SUPPLIER)->record($supplier), 'Sí, restaurar'],
        [ProviderDeletion::makeRestoreBulkAction(ProviderDeletion::TYPE_SUPPLIER), 'Sí, restaurar'],
    ] as [$action, $submitLabel]) {
        expect($action->getModalSubmitAction()?->getLabel())->toBe($submitLabel)
            ->and($action->getModalCancelAction()?->getLabel())->toBe('Cancelar');
    }
});
