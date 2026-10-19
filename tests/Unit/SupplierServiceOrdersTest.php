<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\Suppliers\Pages\SupplierServiceOrders;
use App\Filament\Operations\Resources\Suppliers\Pages\ViewSupplier;
use App\Models\ObservationCase;
use App\Models\OperationDocumentList;
use App\Models\OperationServiceOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Operations\OperationServiceOrderDocuments;
use App\Support\Operations\OperationServiceOrderFinalizationException;
use App\Support\Operations\SupplierServiceOrderBuckets;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/*
 * Órdenes de servicio del proveedor y su finalización desde la ficha. Usa
 * órdenes reales que, dentro de una transacción revertida, se ponen en gestión
 * y vigentes: nada de lo que escribe queda en la base.
 */

beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake('public');
});

afterEach(fn () => DB::rollBack());

function serviceOrdersSuperAdmin(): User
{
    return User::query()->whereJsonContains('departament', 'SUPERADMIN')->orderBy('id')->first()
        ?? test()->markTestSkipped('No hay usuario SUPERADMIN.');
}

/**
 * Orden real con caso, puesta en gestión y vigente (solo dentro de la transacción).
 */
function serviceOrderInProgress(): OperationServiceOrder
{
    $order = OperationServiceOrder::query()
        ->whereNotNull('supplier_id')
        ->whereHas('operationCoordinationService', fn ($query) => $query->whereNotNull('telemedicine_case_id'))
        ->latest('id')
        ->first() ?? test()->markTestSkipped('No hay órdenes con caso.');

    $order->update(['status' => 'EN GESTION', 'approved_at' => now(), 'uploaded_documents' => null]);

    return $order->fresh();
}

/**
 * @return list<array<string, mixed>>
 */
function serviceOrderUploadedDocuments(OperationServiceOrder $order, string $file = 'resultado.pdf'): array
{
    $path = OperationServiceOrderDocuments::directory($order).'/'.$file;
    Storage::disk('public')->put($path, '%PDF-1.4 prueba');
    $typeId = (int) OperationDocumentList::query()->orderBy('id')->value('id');

    return OperationServiceOrderDocuments::fromForm(['documents' => [[
        'document_type_ids' => [$typeId],
        'document_file' => $path,
        'document_original_name' => 'Resultado de laboratorio.pdf',
    ]]]);
}

/*
 * ---------------------------------------------------------------------------
 * Grupos
 * ---------------------------------------------------------------------------
 */

it('cuenta cada grupo igual que lo filtra, en una sola consulta', function (): void {
    $this->actingAs(serviceOrdersSuperAdmin());
    $supplierId = (int) (OperationServiceOrder::query()->groupBy('supplier_id')->orderByRaw('COUNT(*) DESC')->value('supplier_id')
        ?? $this->markTestSkipped('No hay órdenes.'));

    $counts = SupplierServiceOrderBuckets::counts($supplierId);

    foreach (array_keys(SupplierServiceOrderBuckets::definitions()) as $bucket) {
        $listed = SupplierServiceOrderBuckets::apply(SupplierServiceOrderBuckets::baseQuery($supplierId), $bucket)->count();

        expect($counts[$bucket])->toBe($listed, "El grupo {$bucket} cuenta {$counts[$bucket]} y lista {$listed}.");
    }

    expect($counts[SupplierServiceOrderBuckets::PENDING_INVOICE])->toBeLessThanOrEqual($counts[SupplierServiceOrderBuckets::FINALIZED]);
});

it('una orden finalizada sin factura es pendiente; con factura deja de serlo', function (): void {
    $this->actingAs(serviceOrdersSuperAdmin());
    $order = serviceOrderInProgress();
    $order->update(['status' => 'FINALIZADO', 'administrative_status' => 'PENDIENTE']);
    $inBucket = fn (string $bucket): bool => SupplierServiceOrderBuckets::apply(SupplierServiceOrderBuckets::baseQuery((int) $order->supplier_id), $bucket)->whereKey($order->id)->exists();

    expect($inBucket(SupplierServiceOrderBuckets::PENDING_INVOICE))->toBeTrue()
        ->and($inBucket(SupplierServiceOrderBuckets::FINALIZED))->toBeTrue()
        ->and($inBucket(SupplierServiceOrderBuckets::IN_PROGRESS))->toBeFalse();

    $order->update(['administrative_status' => 'FACTURADO']);

    expect($inBucket(SupplierServiceOrderBuckets::PENDING_INVOICE))->toBeFalse()
        ->and($inBucket(SupplierServiceOrderBuckets::FINALIZED))->toBeTrue();
});

it('canceladas, heredadas como CANCELADO y caducadas van a «Canceladas o caducadas»', function (string $status): void {
    $this->actingAs(serviceOrdersSuperAdmin());
    $order = serviceOrderInProgress();
    $order->update(['status' => $status]);

    expect(SupplierServiceOrderBuckets::apply(SupplierServiceOrderBuckets::baseQuery((int) $order->supplier_id), SupplierServiceOrderBuckets::CLOSED_UNFINISHED)->whereKey($order->id)->exists())->toBeTrue();
})->with(['CANCELADA', 'CANCELADO', 'CADUCADA']);

/*
 * ---------------------------------------------------------------------------
 * Finalizar
 * ---------------------------------------------------------------------------
 */

it('finaliza con documento y deja traza en la orden, el caso, la coordinación y la auditoría', function (): void {
    $user = serviceOrdersSuperAdmin();
    $this->actingAs($user);
    $order = serviceOrderInProgress();
    $documents = serviceOrderUploadedDocuments($order);

    $result = OperationServiceOrderDocuments::finalize($order, $documents, $user, OperationServiceOrderDocuments::ORIGIN_SUPPLIER);

    $order->refresh()->load('operationCoordinationService');
    $caseId = $order->operationCoordinationService->telemedicine_case_id;
    $bitacora = ObservationCase::query()->where('telemedicine_case_id', $caseId)->latest('id')->value('description');
    $audit = DB::table('logs')->where('action', OperationServiceOrderDocuments::AUDIT_FINALIZED)->latest('id')->first();

    expect($order->status)->toBe('FINALIZADO')
        ->and($result['logged_to_case'])->toBeTrue()
        ->and($order->uploaded_documents)->toHaveCount(1)
        ->and($order->uploaded_documents[0]['document_name'])->toBe('Resultado de laboratorio')
        ->and($bitacora)->toStartWith(OperationServiceOrderDocuments::FINALIZE_BITACORA_PREFIX)
        ->toContain('Orden: '.OperationServiceOrderDocuments::orderLabel($order))
        ->toContain('Desde: '.OperationServiceOrderDocuments::ORIGIN_SUPPLIER)
        ->toContain('Analista: '.$user->name)
        ->toContain('- Resultado de laboratorio')
        ->and($order->operationCoordinationService->observations)->toContain(OperationServiceOrderDocuments::FINALIZE_BITACORA_PREFIX)
        ->and($audit)->not->toBeNull()
        ->and($audit->response)->toContain('"operation_service_order_id":'.$order->id)
        ->toContain(OperationServiceOrderDocuments::ORIGIN_SUPPLIER);
});

it('no finaliza dos veces: la segunda se rechaza y su archivo se borra', function (): void {
    $user = serviceOrdersSuperAdmin();
    $this->actingAs($user);
    $order = serviceOrderInProgress();
    $first = serviceOrderUploadedDocuments($order, 'primero.pdf');
    OperationServiceOrderDocuments::finalize($order, $first, $user, OperationServiceOrderDocuments::ORIGIN_SUPPLIER);

    $second = serviceOrderUploadedDocuments($order, 'segundo.pdf');

    expect(fn () => OperationServiceOrderDocuments::finalize($order, $second, $user, OperationServiceOrderDocuments::ORIGIN_SUPPLIER))
        ->toThrow(OperationServiceOrderFinalizationException::class, 'ya no está en gestión');

    expect(Storage::disk('public')->exists($second[0]['file_path']))->toBeFalse()
        ->and(Storage::disk('public')->exists($first[0]['file_path']))->toBeTrue()
        ->and($order->fresh()->uploaded_documents)->toHaveCount(1);
});

it('sin el permiso no finaliza, no cambia nada y borra el archivo subido', function (): void {
    $order = serviceOrderInProgress();
    $analyst = User::factory()->make(['departament' => ['OPERACIONES'], 'is_superAdmin' => false]);
    $this->actingAs($analyst);
    $documents = serviceOrderUploadedDocuments($order);

    expect(OperationServiceOrderDocuments::userCanFinalize())->toBeFalse()
        ->and(fn () => OperationServiceOrderDocuments::finalize($order, $documents, $analyst, OperationServiceOrderDocuments::ORIGIN_SUPPLIER))
        ->toThrow(OperationServiceOrderFinalizationException::class, 'No tiene permiso');

    expect($order->fresh()->status)->toBe('EN GESTION')
        ->and(Storage::disk('public')->exists($documents[0]['file_path']))->toBeFalse();
});

it('una orden vencida queda CADUCADA y no se finaliza', function (): void {
    $user = serviceOrdersSuperAdmin();
    $this->actingAs($user);
    $order = serviceOrderInProgress();
    $order->update(['approved_at' => now()->subDays(30)]);
    $documents = serviceOrderUploadedDocuments($order->fresh());

    expect(fn () => OperationServiceOrderDocuments::finalize($order->fresh(), $documents, $user, OperationServiceOrderDocuments::ORIGIN_SUPPLIER))
        ->toThrow(OperationServiceOrderFinalizationException::class, 'CADUCADA');

    expect($order->fresh()->status)->toBe('CADUCADA');
});

it('sin documentos no finaliza', function (): void {
    $user = serviceOrdersSuperAdmin();
    $this->actingAs($user);

    expect(fn () => OperationServiceOrderDocuments::finalize(serviceOrderInProgress(), [], $user, OperationServiceOrderDocuments::ORIGIN_SUPPLIER))
        ->toThrow(OperationServiceOrderFinalizationException::class, 'al menos un documento');
});

it('lee el formulario con el nombre original, los tipos y descarta lo incompleto', function (): void {
    $typeId = (int) (OperationDocumentList::query()->orderBy('id')->value('id') ?? $this->markTestSkipped('No hay tipos de documento.'));
    $typeName = (string) OperationDocumentList::query()->whereKey($typeId)->value('name');

    $documents = OperationServiceOrderDocuments::fromForm(['documents' => [
        ['document_type_ids' => [(string) $typeId, 'x', 0], 'document_file' => 'operation-service-orders/1/documents/01HABC.pdf', 'document_original_name' => 'Informe médico.pdf'],
        ['document_type_ids' => [$typeId], 'document_file' => ''],
        'basura',
    ]]);

    expect($documents)->toHaveCount(1)
        ->and($documents[0])->toMatchArray([
            'document_name' => 'Informe médico',
            'file_path' => 'operation-service-orders/1/documents/01HABC.pdf',
            'document_type_ids' => [$typeId],
            'document_types' => [$typeName],
        ]);
});

/*
 * ---------------------------------------------------------------------------
 * Pantalla, ficha y permisos
 * ---------------------------------------------------------------------------
 */

it('la pantalla lista las órdenes en gestión con «Finalizar» y las quita de las finalizadas', function (): void {
    Filament::setCurrentPanel('operations');
    $this->actingAs(serviceOrdersSuperAdmin());
    $order = serviceOrderInProgress();

    Livewire::test(SupplierServiceOrders::class, ['record' => $order->supplier_id])
        ->assertOk()
        ->assertSee('Órdenes de servicio')
        ->assertCanSeeTableRecords([$order])
        ->assertTableActionVisible('finalizeOrder', $order)
        ->set('activeTab', SupplierServiceOrderBuckets::FINALIZED)
        ->assertCanNotSeeTableRecords([$order]);
});

it('la ficha del proveedor carga con su nombre como título, los contadores y el menú de acciones', function (): void {
    Filament::setCurrentPanel('operations');
    $this->actingAs(serviceOrdersSuperAdmin());
    $order = serviceOrderInProgress();
    $supplier = Supplier::query()->findOrFail($order->supplier_id);

    Livewire::test(ViewSupplier::class, ['record' => $supplier->getKey()])
        ->assertOk()
        ->assertSee($supplier->name)
        ->assertSee('En gestión')
        ->assertSee('?grupo=pendientes', false)
        ->assertActionVisible('service_orders');
});

it('el permiso de finalizar existe en Operaciones', function (): void {
    expect(BusinessFilamentActionPermissionRegistry::all())->toHaveKey(BusinessFilamentActionPermissionRegistry::FINALIZE_OPERATION_SERVICE_ORDER)
        ->and(BusinessFilamentActionPermissionRegistry::modulesForSlug(BusinessFilamentActionPermissionRegistry::FINALIZE_OPERATION_SERVICE_ORDER))->toBe(['OPERACIONES']);
});

it('la vista de la orden finaliza por el mismo servicio y solo con permiso', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Pages/ViewOperationServiceOrder.php');

    expect($source)->toContain('OperationServiceOrderDocuments::finalize(')
        ->toContain('OperationServiceOrderDocuments::ORIGIN_ORDER_VIEW')
        ->toContain('&& OperationServiceOrderDocuments::userCanFinalize()')
        ->not->toContain('OperationServiceOrderCoordinationSync::finalizeOrder(');
});

it('el filtro de estado encuentra las canceladas', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Tables/OperationServiceOrdersTable.php');

    expect($source)->toContain("'CANCELADA' => 'Cancelada',")
        ->not->toContain("'CANCELADO' => 'Cancelado',")
        ->toContain("\$values[] = 'CANCELADO';");
});

it('el encabezado muestra la identidad del proveedor y enlaza cada contador a su grupo', function (): void {
    $supplier = new Supplier(['name' => 'CLÍNICA PRUEBA', 'razon_social' => 'CLÍNICA PRUEBA C.A.', 'rif' => 'J-12345678-9', 'status_convenio' => 'ACTIVO', 'status_sistema' => 'PENDIENTE']);

    $html = view('filament.operations.suppliers.supplier-page-header', [
        'supplier' => $supplier,
        'counts' => [
            SupplierServiceOrderBuckets::IN_PROGRESS => 2,
            SupplierServiceOrderBuckets::PENDING_INVOICE => 5,
            SupplierServiceOrderBuckets::FINALIZED => 9,
            SupplierServiceOrderBuckets::CLOSED_UNFINISHED => 0,
        ],
        'buckets' => SupplierServiceOrderBuckets::definitions(),
        'bucketUrl' => fn (string $bucket): string => '/ordenes?grupo='.$bucket,
    ])->render();

    expect($html)->toContain('CLÍNICA PRUEBA C.A.')
        ->toContain('J-12345678-9')
        ->toContain('Copiar RIF')
        ->toContain('Convenio: ACTIVO')
        ->toContain('bg-emerald-50')
        ->toContain('Sistema: PENDIENTE')
        ->toContain('href="/ordenes?grupo=pendientes"')
        ->toContain('Canceladas o caducadas')
        // Solo elementos en línea: Filament lo envuelve en un <p>.
        ->not->toMatch('/<(div|p|section|ul|ol|li)\b/');
});

it('sin acceso a órdenes el encabezado no muestra contadores', function (): void {
    $html = view('filament.operations.suppliers.supplier-page-header', [
        'supplier' => new Supplier(['name' => 'X']),
        'counts' => null,
        'buckets' => SupplierServiceOrderBuckets::definitions(),
        'bucketUrl' => fn (string $bucket): string => '#',
    ])->render();

    expect($html)->not->toContain('Órdenes de servicio del proveedor');
});
