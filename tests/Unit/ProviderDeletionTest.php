<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\CorporateAllies\Pages\ListCorporateAllies;
use App\Filament\Operations\Resources\DoctorNurses\Pages\ListDoctorNurses;
use App\Filament\Operations\Resources\Suppliers\Pages\ListSuppliers;
use App\Jobs\SendNotificacionWhatsApp;
use App\Mail\OperationServiceOrderPdfMail;
use App\Mail\OperationServiceOrderWithdrawnMail;
use App\Models\CorporateAlly;
use App\Models\DoctorNurse;
use App\Models\OperationCoordinationService;
use App\Models\OperationMedicalAppointment;
use App\Models\OperationServiceOrder;
use App\Models\ProviderDeletionLog;
use App\Models\ProviderDeletionLogItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Operations\OperationServiceOrderViewActions;
use App\Support\Operations\ProviderDeletion;
use App\Support\Operations\ProviderPendingWork;
use App\Support\Operations\ServiceOrderFlowTrace;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
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

it('el historial no bloquea, pero un servicio médico abierto a su nombre sí (regla desde 04/11/2026)', function (): void {
    $supplier = proveedorJuridico();
    servicioMedicoCon(['supplier_id' => $supplier->id, 'status' => 'FINALIZADO']);
    servicioMedicoCon(['assigned_supplier_id' => $supplier->id]);

    expect(ProviderDeletion::usageFor(ProviderDeletion::TYPE_SUPPLIER, [$supplier->id]))
        ->toBe([$supplier->id => ['servicios médicos' => 2]])
        ->and(fn () => ProviderDeletion::delete($supplier, 'Intento de eliminar con un servicio abierto'))
        ->toThrow(InvalidArgumentException::class, '1 servicio médico abierto');

    expect(Supplier::query()->find($supplier->id))->not->toBeNull();
});

it('bloquea al proveedor jurídico con usuarios activos del portal', function (): void {
    $supplier = proveedorJuridico();
    User::factory()->create(['supplier_id' => $supplier->id, 'status' => 'ACTIVO', 'email' => 'qa.portal.'.uniqid().'@example.com']);

    expect(fn () => ProviderDeletion::delete($supplier, 'Intento con usuario del portal'))
        ->toThrow(InvalidArgumentException::class, 'usuario activo del portal');
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

/**
 * Orden de servicio (con su servicio médico y, si se pide, su cita) para la prueba.
 *
 * @param  array<string, mixed>  $order
 * @return array{coordination: OperationCoordinationService, order: OperationServiceOrder, appointment: OperationMedicalAppointment|null}
 */
function ordenDelProveedor(array $order, ?string $appointmentAt = '+3 days', array $coordination = []): array
{
    $service = servicioMedicoCon($coordination + ['patient' => 'PACIENTE QA']);

    $model = OperationServiceOrder::query()->create($order + [
        'operation_coordination_service_id' => $service->id,
        'order_number' => 'OS-QA-'.uniqid(),
        'service_type' => 'ATENCION MEDICA DOMICILIARIA',
        'status' => 'EN GESTION',
        'total_amount_usd' => 40,
        'created_by' => 'TEST',
    ]);

    $appointment = $appointmentAt === null ? null : OperationMedicalAppointment::query()->create([
        'operation_service_order_id' => $model->id,
        'operation_coordination_service_id' => $service->id,
        'supplier_id' => $order['supplier_id'] ?? null,
        'appointment_at' => Carbon::parse($appointmentAt),
        'status' => OperationMedicalAppointment::STATUS_SCHEDULED,
        'created_by' => 'TEST',
    ]);

    return ['coordination' => $service, 'order' => $model, 'appointment' => $appointment];
}

function proveedorJuridicoConContacto(): Supplier
{
    $supplier = proveedorJuridico();
    $supplier->forceFill(['correo_principal' => 'qa.'.uniqid().'@example.com', 'personal_phone' => '04141234567'])->save();

    return $supplier;
}

it('elimina directo a un proveedor sin nada vigente y conserva su historial marcado como eliminado', function (): void {
    $supplier = proveedorJuridico();
    $closed = ordenDelProveedor(['supplier_id' => $supplier->id, 'status' => 'CANCELADA'], appointmentAt: '-20 days');

    ProviderDeletion::delete($supplier, 'Ya no presta servicios a la red');

    $order = OperationServiceOrder::query()->with('supplier')->find($closed['order']->id);

    expect(Supplier::query()->find($supplier->id))->toBeNull()
        ->and($order->supplier?->id)->toBe($supplier->id)
        ->and(ServiceOrderFlowTrace::orderProviderLabel($order))->toEndWith('(eliminado)')
        ->and(ProviderDeletion::displayName($order->supplier))->toEndWith('(eliminado)');
});

it('con órdenes vigentes y sin decisiones, pide eliminarlo desde su fila', function (): void {
    $supplier = proveedorJuridico();
    ordenDelProveedor(['supplier_id' => $supplier->id]);

    expect(fn () => ProviderDeletion::delete($supplier, 'Intento sin resolver la orden'))
        ->toThrow(InvalidArgumentException::class, '1 orden vigente');

    expect(Supplier::query()->find($supplier->id))->not->toBeNull();
});

it('reasigna la orden con su cita y su servicio médico al nuevo proveedor, lo avisa y elimina al anterior', function (): void {
    Mail::fake();
    Queue::fake();
    $supplier = proveedorJuridico();
    $target = proveedorJuridicoConContacto();
    $fixture = ordenDelProveedor(['supplier_id' => $supplier->id], coordination: ['assigned_supplier_id' => $supplier->id]);

    $outcome = ProviderDeletion::delete($supplier, 'El proveedor cerró operaciones', [[
        'key' => 'order:'.$fixture['order']->id,
        'action' => ProviderDeletion::RESOLVE_REASSIGN,
        'target' => 'supplier:'.$target->id,
    ]]);

    $order = $fixture['order']->fresh();
    $appointment = $fixture['appointment']->fresh();
    $coordination = OperationCoordinationService::query()->withoutGlobalScopes()->find($fixture['coordination']->id);

    expect($outcome)->toBe(['reassigned' => 1, 'cancelled_orders' => 0, 'cancelled_appointments' => 0])
        ->and(Supplier::query()->find($supplier->id))->toBeNull()
        ->and((int) $order->supplier_id)->toBe($target->id)
        ->and($order->status)->toBe('EN GESTION')
        ->and((int) $appointment->supplier_id)->toBe($target->id)
        ->and($appointment->supplier_notify_email)->toBe($target->correo_principal)
        ->and((int) $coordination->assigned_supplier_id)->toBe($target->id)
        ->and($coordination->observations)->toContain('Reasignación de orden de servicio.')
        ->and(DB::table('operation_service_flow_events')->where('operation_service_order_id', $order->id)->where('event', ServiceOrderFlowTrace::ORDER_PROVIDER_CHANGED)->exists())->toBeTrue();

    Mail::assertQueued(OperationServiceOrderPdfMail::class, fn (OperationServiceOrderPdfMail $mail): bool => $mail->hasTo($target->correo_principal));
    Queue::assertPushed(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => str_contains($job->body, 'ORDEN DE SERVICIO ASIGNADA'));
});

it('puede reasignar a un proveedor natural', function (): void {
    Mail::fake();
    Queue::fake();
    $supplier = proveedorJuridico();
    $natural = proveedorNatural();
    $fixture = ordenDelProveedor(['supplier_id' => $supplier->id]);

    ProviderDeletion::delete($supplier, 'Lo reemplaza un médico de la zona', [[
        'key' => 'order:'.$fixture['order']->id,
        'action' => ProviderDeletion::RESOLVE_REASSIGN,
        'target' => 'doctor_nurse:'.$natural->id,
    ]]);

    $order = $fixture['order']->fresh();

    expect($order->supplier_id)->toBeNull()
        ->and((int) $order->doctor_nurse_id)->toBe($natural->id)
        ->and($fixture['appointment']->fresh()->supplier_id)->toBeNull();
});

it('cancela la orden y su cita cuando no hay quien la atienda, y libera el servicio médico', function (): void {
    $supplier = proveedorJuridico();
    $fixture = ordenDelProveedor(['supplier_id' => $supplier->id], coordination: ['assigned_supplier_id' => $supplier->id]);

    $outcome = ProviderDeletion::delete($supplier, 'Proveedor sin reemplazo en la zona', [[
        'key' => 'order:'.$fixture['order']->id,
        'action' => ProviderDeletion::RESOLVE_CANCEL,
        'cancel_reason' => 'No hay otro proveedor en la zona',
    ]]);

    $coordination = OperationCoordinationService::query()->withoutGlobalScopes()->find($fixture['coordination']->id);

    expect($outcome['cancelled_orders'])->toBe(1)
        ->and($fixture['order']->fresh()->status)->toBe('CANCELADA')
        ->and($fixture['appointment']->fresh()->status)->toBe(OperationMedicalAppointment::STATUS_CANCELLED)
        ->and($coordination->assigned_supplier_id)->toBeNull()
        ->and($coordination->observations)->toContain('Proveedor asignado retirado')
        ->and(Supplier::query()->find($supplier->id))->toBeNull()
        ->and(DB::table('supplier_observacions')->where('supplier_id', $supplier->id)->value('observation'))->toContain('1 orden cancelada');
});

it('si una resolución falla no guarda nada y el proveedor queda', function (): void {
    Mail::fake();
    Queue::fake();
    $supplier = proveedorJuridico();
    $target = proveedorJuridicoConContacto();
    $first = ordenDelProveedor(['supplier_id' => $supplier->id]);
    $second = ordenDelProveedor(['supplier_id' => $supplier->id]);

    expect(fn () => ProviderDeletion::delete($supplier, 'Intento con una decisión inválida', [
        ['key' => 'order:'.$first['order']->id, 'action' => ProviderDeletion::RESOLVE_REASSIGN, 'target' => 'supplier:'.$target->id],
        ['key' => 'order:'.$second['order']->id, 'action' => ProviderDeletion::RESOLVE_REASSIGN, 'target' => 'supplier:'.$supplier->id],
    ]))->toThrow(InvalidArgumentException::class, 'mismo proveedor');

    expect((int) $first['order']->fresh()->supplier_id)->toBe($supplier->id)
        ->and(Supplier::query()->find($supplier->id))->not->toBeNull();
});

it('rechaza decisiones incompletas: sin acción, sin proveedor destino o sin motivo de cancelación', function (array $resolution, string $message): void {
    $supplier = proveedorJuridico();
    $fixture = ordenDelProveedor(['supplier_id' => $supplier->id]);

    expect(fn () => ProviderDeletion::delete($supplier, 'Intento con decisión incompleta', [['key' => 'order:'.$fixture['order']->id, ...$resolution]]))
        ->toThrow(InvalidArgumentException::class, $message);

    expect($fixture['order']->fresh()->status)->toBe('EN GESTION');
})->with([
    'sin acción' => [[], 'se reasigna o se cancela'],
    'sin destino' => [['action' => 'reassign'], 'Elija el proveedor'],
    'motivo corto' => [['action' => 'cancel', 'cancel_reason' => 'corto'], 'mínimo 10'],
]);

it('una cita futura cuya orden ya cerró solo puede cancelarse', function (): void {
    $supplier = proveedorJuridico();
    $fixture = ordenDelProveedor(['supplier_id' => $supplier->id, 'status' => 'FINALIZADO'], appointmentAt: '+5 days');
    $key = 'appointment:'.$fixture['appointment']->id;

    expect(ProviderPendingWork::forProvider(ProviderDeletion::TYPE_SUPPLIER, $supplier)['appointments'])->toHaveCount(1)
        ->and(fn () => ProviderDeletion::delete($supplier, 'Intento de reasignar la cita', [['key' => $key, 'action' => 'reassign', 'target' => 'supplier:1']]))
        ->toThrow(InvalidArgumentException::class, 'solo puede cancelarse');

    ProviderDeletion::delete($supplier, 'Proveedor dado de baja', [['key' => $key, 'action' => 'cancel', 'cancel_reason' => 'El proveedor ya no atiende']]);

    expect($fixture['appointment']->fresh()->status)->toBe(OperationMedicalAppointment::STATUS_CANCELLED)
        ->and(Supplier::query()->find($supplier->id))->toBeNull();
});

it('siguen bloqueando las cuentas por pagar pendientes y las órdenes con factura', function (): void {
    $supplier = proveedorJuridico();
    $fixture = ordenDelProveedor(['supplier_id' => $supplier->id]);
    DB::table('operation_accounts_payables')->insert([
        'supplier_id' => $supplier->id,
        'operation_service_order_id' => $fixture['order']->id,
        'payment_status' => ProviderPendingWork::PENDING_PAYABLE_STATUS,
        'invoice_date' => now()->toDateString(),
        'invoice_registration_date' => now()->toDateString(),
        'invoice_number' => 'QA-'.uniqid(),
        'supplier_name' => $supplier->name,
        'supplier_rif' => (string) $supplier->rif,
        'invoice_amount' => 40,
        'created_by' => 'TEST',
    ]);

    $pending = ProviderPendingWork::forProvider(ProviderDeletion::TYPE_SUPPLIER, $supplier);

    expect($pending['orders'])->toBe([])
        ->and(implode(' ', $pending['blockers']))->toContain('1 cuenta por pagar pendiente')
        ->and(implode(' ', $pending['blockers']))->toContain('1 orden en gestión con factura registrada')
        ->and(fn () => ProviderDeletion::delete($supplier, 'Intento con factura pendiente'))
        ->toThrow(InvalidArgumentException::class, 'no se puede eliminar todavía');
});

it('cancelar una orden desde su pantalla también cancela su cita', function (): void {
    $supplier = proveedorJuridico();
    $fixture = ordenDelProveedor(['supplier_id' => $supplier->id]);

    $cancelled = OperationServiceOrderViewActions::cancelOrderOrFail($fixture['order'], 'El paciente desistió del servicio', 'QA PROVEEDORES');

    expect($cancelled->status)->toBe('CANCELADA')
        ->and($fixture['appointment']->fresh()->status)->toBe(OperationMedicalAppointment::STATUS_CANCELLED);
});

it('resuelve y elimina desde la ventana de la tabla, con el atajo de reasignar todas', function (): void {
    Mail::fake();
    Queue::fake();
    $supplier = proveedorJuridico();
    $target = proveedorJuridicoConContacto();
    $first = ordenDelProveedor(['supplier_id' => $supplier->id]);
    $second = ordenDelProveedor(['supplier_id' => $supplier->id], appointmentAt: null);

    $component = Livewire::test(ListSuppliers::class)
        ->searchTable($supplier->name)
        ->mountAction(TestAction::make('delete_provider')->table($supplier))
        ->assertMountedActionModalSeeHtml('Resuélvalas abajo');

    $rows = $component->get('mountedActions.0.data.resolutions');
    expect($rows)->toHaveCount(2);

    $component
        ->set('mountedActions.0.data.reassign_all', 'supplier:'.$target->id)
        ->set('mountedActions.0.data.deletion_reason', 'Proveedor reemplazado en la zona')
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Eliminación completada');

    expect(Supplier::query()->find($supplier->id))->toBeNull()
        ->and((int) $first['order']->fresh()->supplier_id)->toBe($target->id)
        ->and((int) $second['order']->fresh()->supplier_id)->toBe($target->id);
});

it('la ventana exige decidir cada orden antes de eliminar', function (): void {
    $supplier = proveedorJuridico();
    ordenDelProveedor(['supplier_id' => $supplier->id]);

    $component = Livewire::test(ListSuppliers::class)
        ->searchTable($supplier->name)
        ->mountAction(TestAction::make('delete_provider')->table($supplier))
        ->set('mountedActions.0.data.deletion_reason', 'Intento sin decidir la orden')
        ->callMountedAction();

    $key = array_key_first($component->get('mountedActions.0.data.resolutions'));

    $component->assertHasFormErrors(["resolutions.{$key}.action" => 'required']);

    expect(Supplier::query()->find($supplier->id))->not->toBeNull();
});

it('la eliminación masiva no resuelve pendientes: omite al que tiene órdenes vigentes', function (): void {
    $free = proveedorJuridico();
    $busy = proveedorJuridico();
    ordenDelProveedor(['supplier_id' => $busy->id]);

    Livewire::test(ListSuppliers::class)
        ->searchTable('QA JURIDICO')
        ->selectTableRecords([$free->getKey(), $busy->getKey()])
        ->mountAction(TestAction::make('delete_providers')->table()->bulk())
        ->assertMountedActionModalSeeHtml('elimínelo desde su fila')
        ->set('mountedActions.0.data.deletion_reason', 'Limpieza de proveedores de prueba')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(Supplier::query()->find($free->id))->toBeNull()
        ->and(Supplier::query()->find($busy->id))->not->toBeNull();
});

/**
 * Laboratorio clínico del paciente que corresponde al ítem de una orden de LABORATORIOS.
 */
function laboratorioDeLaOrden(OperationServiceOrder $order, string $name = 'HEMATOLOGIA COMPLETA'): int
{
    DB::table('operation_service_order_items')->insert([
        'operation_service_order_id' => $order->id,
        'item_name' => $name,
        'category' => 'LABORATORIO',
        'amount' => 10,
        'created_by' => 'TEST',
    ]);

    return (int) DB::table('telemedicine_patient_labs')->insertGetId([
        'telemedicine_patient_id' => (int) DB::table('telemedicine_patients')->value('id'),
        'laboratory' => $name,
        'type' => 'LABORATORIO',
        'operation_coordination_service_id' => $order->operation_coordination_service_id,
        'status' => 'EN GESTION',
    ]);
}

it('al eliminar guarda la foto de cada orden reasignada o cancelada', function (): void {
    Mail::fake();
    Queue::fake();
    $supplier = proveedorJuridico();
    $target = proveedorJuridicoConContacto();
    $reassigned = ordenDelProveedor(['supplier_id' => $supplier->id], coordination: ['assigned_supplier_id' => $supplier->id]);
    $cancelled = ordenDelProveedor(['supplier_id' => $supplier->id, 'service_type' => 'LABORATORIOS']);
    $labId = laboratorioDeLaOrden($cancelled['order']);

    ProviderDeletion::delete($supplier, 'Proveedor dado de baja', [
        ['key' => 'order:'.$reassigned['order']->id, 'action' => 'reassign', 'target' => 'supplier:'.$target->id],
        ['key' => 'order:'.$cancelled['order']->id, 'action' => 'cancel', 'cancel_reason' => 'Sin reemplazo en la zona'],
    ]);

    $log = ProviderDeletionLog::query()->where('provider_id', $supplier->id)->sole();
    $items = $log->items()->get()->keyBy('operation_service_order_id');

    expect($log->provider_type)->toBe(ProviderDeletion::TYPE_SUPPLIER)
        ->and($log->restored_at)->toBeNull()
        ->and($items)->toHaveCount(2)
        ->and($items[$reassigned['order']->id]->snapshot['order']['supplier_id'])->toBe($supplier->id)
        ->and($items[$reassigned['order']->id]->snapshot['coordination']['assigned_supplier_id'])->toBe($supplier->id)
        ->and($items[$reassigned['order']->id]->result)->toEqual(['target_type' => 'supplier', 'target_id' => $target->id])
        ->and($items[$cancelled['order']->id]->snapshot['order']['status'])->toBe('EN GESTION')
        ->and($items[$cancelled['order']->id]->snapshot['clinical_items'][0]['id'])->toBe($labId)
        ->and(DB::table('telemedicine_patient_labs')->where('id', $labId)->value('status'))->toBe('CANCELADA');
});

it('restaurar devuelve la orden reasignada al proveedor original y avisa a los dos', function (): void {
    Mail::fake();
    Queue::fake();
    $supplier = proveedorJuridicoConContacto();
    $target = proveedorJuridicoConContacto();
    $fixture = ordenDelProveedor(['supplier_id' => $supplier->id], coordination: ['assigned_supplier_id' => $supplier->id]);
    $fixture['appointment']->forceFill(['supplier_notify_email' => 'original@example.com', 'supplier_notify_phone' => '04141112233'])->save();

    ProviderDeletion::delete($supplier, 'Eliminado por error en la prueba', [
        ['key' => 'order:'.$fixture['order']->id, 'action' => 'reassign', 'target' => 'supplier:'.$target->id],
    ]);
    Mail::fake();
    Queue::fake();

    $summary = ProviderDeletion::restore(Supplier::withTrashed()->find($supplier->id), 'Se eliminó por error, vuelve a trabajar');

    $order = $fixture['order']->fresh();
    $appointment = $fixture['appointment']->fresh();
    $coordination = OperationCoordinationService::query()->withoutGlobalScopes()->find($fixture['coordination']->id);
    $log = ProviderDeletionLog::query()->where('provider_id', $supplier->id)->sole();

    expect($summary)->toMatchArray(['restored' => 1, 'skipped' => 0, 'has_log' => true])
        ->and(Supplier::query()->find($supplier->id))->not->toBeNull()
        ->and((int) $order->supplier_id)->toBe($supplier->id)
        ->and((int) $appointment->supplier_id)->toBe($supplier->id)
        ->and($appointment->supplier_notify_email)->toBe('original@example.com')
        ->and((int) $coordination->assigned_supplier_id)->toBe($supplier->id)
        ->and($coordination->observations)->toContain('Devolución de orden por restauración del proveedor.')
        ->and($log->restored_at)->not->toBeNull()
        ->and($log->items()->sole()->restore_status)->toBe(ProviderDeletionLogItem::RESTORED);

    Mail::assertQueued(OperationServiceOrderPdfMail::class, fn (OperationServiceOrderPdfMail $mail): bool => $mail->hasTo($supplier->correo_principal));
    Mail::assertQueued(OperationServiceOrderWithdrawnMail::class, fn (OperationServiceOrderWithdrawnMail $mail): bool => $mail->hasTo($target->correo_principal));
    Queue::assertPushed(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => str_contains($job->body, 'ORDEN DE SERVICIO RETIRADA'));
    Queue::assertPushed(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $job): bool => str_contains($job->body, 'ORDEN DE SERVICIO ASIGNADA'));
});

it('restaurar devuelve la orden cancelada a su estado, con sus ítems clínicos, su cita y su servicio médico', function (): void {
    $supplier = proveedorJuridico();
    $fixture = ordenDelProveedor(['supplier_id' => $supplier->id, 'service_type' => 'LABORATORIOS'], coordination: ['assigned_supplier_id' => $supplier->id]);
    $labId = laboratorioDeLaOrden($fixture['order']);

    ProviderDeletion::delete($supplier, 'Eliminado por error en la prueba', [
        ['key' => 'order:'.$fixture['order']->id, 'action' => 'cancel', 'cancel_reason' => 'Sin reemplazo en la zona'],
    ]);

    $summary = ProviderDeletion::restore(Supplier::withTrashed()->find($supplier->id), 'Se eliminó por error, vuelve a trabajar');

    expect($summary['restored'])->toBe(1)
        ->and($fixture['order']->fresh()->status)->toBe('EN GESTION')
        ->and(DB::table('telemedicine_patient_labs')->where('id', $labId)->value('status'))->toBe('EN GESTION')
        ->and($fixture['appointment']->fresh()->status)->toBe(OperationMedicalAppointment::STATUS_SCHEDULED)
        ->and((int) OperationCoordinationService::query()->withoutGlobalScopes()->find($fixture['coordination']->id)->assigned_supplier_id)->toBe($supplier->id);
});

it('no devuelve la orden que el nuevo proveedor ya finalizó, y lo explica', function (): void {
    Mail::fake();
    Queue::fake();
    $supplier = proveedorJuridico();
    $target = proveedorJuridicoConContacto();
    $fixture = ordenDelProveedor(['supplier_id' => $supplier->id]);

    ProviderDeletion::delete($supplier, 'Eliminado por error en la prueba', [
        ['key' => 'order:'.$fixture['order']->id, 'action' => 'reassign', 'target' => 'supplier:'.$target->id],
    ]);
    $fixture['order']->fresh()->update(['status' => 'FINALIZADO']);
    Mail::fake();

    $restored = Supplier::withTrashed()->find($supplier->id);
    $preview = ProviderDeletion::restorationPreview(ProviderDeletion::TYPE_SUPPLIER, $restored);
    $summary = ProviderDeletion::restore($restored, 'Se eliminó por error, vuelve a trabajar');

    expect($preview[0]['ok'])->toBeFalse()
        ->and($preview[0]['note'])->toContain('FINALIZADO')
        ->and($summary)->toMatchArray(['restored' => 0, 'skipped' => 1])
        ->and((int) $fixture['order']->fresh()->supplier_id)->toBe($target->id)
        ->and(Supplier::query()->find($supplier->id))->not->toBeNull()
        ->and(ProviderDeletionLogItem::query()->where('operation_service_order_id', $fixture['order']->id)->value('restore_status'))->toBe(ProviderDeletionLogItem::SKIPPED);

    Mail::assertNothingQueued();
});

it('no devuelve la orden que volvió a cambiar de proveedor ni la cancelada que alguien reabrió', function (): void {
    Mail::fake();
    Queue::fake();
    $supplier = proveedorJuridico();
    $target = proveedorJuridicoConContacto();
    $other = proveedorJuridico();
    $moved = ordenDelProveedor(['supplier_id' => $supplier->id]);
    $reopened = ordenDelProveedor(['supplier_id' => $supplier->id]);

    ProviderDeletion::delete($supplier, 'Eliminado por error en la prueba', [
        ['key' => 'order:'.$moved['order']->id, 'action' => 'reassign', 'target' => 'supplier:'.$target->id],
        ['key' => 'order:'.$reopened['order']->id, 'action' => 'cancel', 'cancel_reason' => 'Sin reemplazo en la zona'],
    ]);
    $moved['order']->fresh()->update(['supplier_id' => $other->id]);
    $reopened['order']->fresh()->update(['status' => 'EN GESTION']);

    $preview = collect(ProviderDeletion::restorationPreview(ProviderDeletion::TYPE_SUPPLIER, Supplier::withTrashed()->find($supplier->id)));

    expect($preview->pluck('ok')->all())->toBe([false, false])
        ->and($preview->pluck('note')->implode(' '))->toContain('cambió de proveedor otra vez')
        ->and($preview->pluck('note')->implode(' '))->toContain('ya no está cancelada');
});

it('avisa que hay que reprogramar la cita que ya pasó', function (): void {
    $supplier = proveedorJuridico();
    $fixture = ordenDelProveedor(['supplier_id' => $supplier->id], appointmentAt: '+1 hour');

    ProviderDeletion::delete($supplier, 'Eliminado por error en la prueba', [
        ['key' => 'order:'.$fixture['order']->id, 'action' => 'cancel', 'cancel_reason' => 'Sin reemplazo en la zona'],
    ]);
    $fixture['appointment']->fresh()->forceFill(['appointment_at' => Carbon::now()->subDay()])->save();

    $summary = ProviderDeletion::restore(Supplier::withTrashed()->find($supplier->id), 'Se eliminó por error, vuelve a trabajar');

    expect($summary['restored'])->toBe(1)
        ->and(implode(' ', $summary['notes']))->toContain('reprográmela');
});

it('si la devolución falla a mitad, no restaura nada', function (): void {
    Mail::fake();
    Queue::fake();
    $supplier = proveedorJuridico();
    $target = proveedorJuridicoConContacto();
    $reassigned = ordenDelProveedor(['supplier_id' => $supplier->id]);
    $cancelled = ordenDelProveedor(['supplier_id' => $supplier->id]);

    ProviderDeletion::delete($supplier, 'Eliminado por error en la prueba', [
        ['key' => 'order:'.$reassigned['order']->id, 'action' => 'reassign', 'target' => 'supplier:'.$target->id],
        ['key' => 'order:'.$cancelled['order']->id, 'action' => 'cancel', 'cancel_reason' => 'Sin reemplazo en la zona'],
    ]);

    $failingAppointmentId = $cancelled['appointment']->id;
    OperationMedicalAppointment::saving(function (OperationMedicalAppointment $appointment) use ($failingAppointmentId): void {
        if ($appointment->id === $failingAppointmentId && $appointment->status === OperationMedicalAppointment::STATUS_SCHEDULED) {
            throw new RuntimeException('Falla simulada al devolver la cita');
        }
    });

    expect(fn () => ProviderDeletion::restore(Supplier::withTrashed()->find($supplier->id), 'Se eliminó por error, vuelve a trabajar'))
        ->toThrow(RuntimeException::class, 'Falla simulada');

    expect(Supplier::query()->find($supplier->id))->toBeNull()
        ->and((int) $reassigned['order']->fresh()->supplier_id)->toBe($target->id)
        ->and($cancelled['order']->fresh()->status)->toBe('CANCELADA')
        ->and(ProviderDeletionLog::query()->where('provider_id', $supplier->id)->value('restored_at'))->toBeNull();
});

it('una eliminación se deshace una sola vez y una nueva eliminación tiene su propia foto', function (): void {
    $supplier = proveedorJuridico();
    $fixture = ordenDelProveedor(['supplier_id' => $supplier->id]);

    ProviderDeletion::delete($supplier, 'Primera eliminación de la prueba', [
        ['key' => 'order:'.$fixture['order']->id, 'action' => 'cancel', 'cancel_reason' => 'Sin reemplazo en la zona'],
    ]);
    ProviderDeletion::restore(Supplier::withTrashed()->find($supplier->id), 'Primera restauración de la prueba');

    expect(fn () => ProviderDeletion::restore(Supplier::query()->find($supplier->id), 'Segunda restauración'))
        ->toThrow(InvalidArgumentException::class, 'no está eliminado');

    $fixture['order']->fresh()->update(['status' => 'FINALIZADO']);
    $fixture['appointment']->fresh()->forceFill(['appointment_at' => Carbon::now()->subDay()])->save();
    ProviderDeletion::delete(Supplier::query()->find($supplier->id), 'Segunda eliminación de la prueba');

    $logs = ProviderDeletionLog::query()->where('provider_id', $supplier->id)->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->restored_at)->not->toBeNull()
        ->and($logs[1]->items()->count())->toBe(0)
        ->and(ProviderDeletion::restorationPreview(ProviderDeletion::TYPE_SUPPLIER, Supplier::withTrashed()->find($supplier->id)))->toBe([]);
});

it('un proveedor eliminado sin bitácora (antes de este cambio) se restaura solo él', function (): void {
    $supplier = proveedorJuridico();
    $supplier->forceFill(['deleted_by_name' => 'ANTES', 'deletion_reason' => 'Eliminado con la regla vieja'])->saveQuietly();
    $supplier->delete();

    $summary = ProviderDeletion::restore(Supplier::withTrashed()->find($supplier->id), 'Restauración de un proveedor viejo');

    expect($summary)->toBe(['restored' => 0, 'skipped' => 0, 'notes' => [], 'has_log' => false])
        ->and(Supplier::query()->find($supplier->id))->not->toBeNull();
});

it('la ventana de restaurar muestra qué vuelve y la masiva omite a quien tiene órdenes por devolver', function (): void {
    $withOrders = proveedorJuridico();
    $plain = proveedorJuridico();
    $fixture = ordenDelProveedor(['supplier_id' => $withOrders->id]);
    ProviderDeletion::delete($withOrders, 'Eliminado por error en la prueba', [
        ['key' => 'order:'.$fixture['order']->id, 'action' => 'cancel', 'cancel_reason' => 'Sin reemplazo en la zona'],
    ]);
    ProviderDeletion::delete($plain, 'Eliminado sin órdenes en la prueba');

    $deleted = Supplier::withTrashed()->find($withOrders->id);

    Livewire::test(ListSuppliers::class)
        ->filterTable(ProviderDeletion::DELETED_FILTER, true)
        ->searchTable($withOrders->name)
        ->mountAction(TestAction::make('restore_provider')->table($deleted))
        ->assertMountedActionModalSeeHtml('✓ Vuelve')
        ->assertMountedActionModalSeeHtml('Restaurar y devolver');

    Livewire::test(ListSuppliers::class)
        ->filterTable(ProviderDeletion::DELETED_FILTER, true)
        ->searchTable('QA JURIDICO')
        ->selectTableRecords([$withOrders->id, $plain->id])
        ->mountAction(TestAction::make('restore_providers')->table()->bulk())
        ->assertMountedActionModalSeeHtml('restáurelo desde su fila')
        ->set('mountedActions.0.data.restoration_reason', 'Restauración masiva de la prueba')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(Supplier::query()->find($plain->id))->not->toBeNull()
        ->and(Supplier::query()->find($withOrders->id))->toBeNull()
        ->and($fixture['order']->fresh()->status)->toBe('CANCELADA');
});
