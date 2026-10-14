<?php

declare(strict_types=1);

use App\Models\OperationServiceOrder;
use App\Models\User;
use App\Support\Operations\ServiceOrderPdfAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

/**
 * @param  array<string, mixed>  $attributes
 */
function usuarioDePrueba(array $attributes): User
{
    return User::factory()->make(array_merge([
        'id' => 999_999_010,
        'name' => 'QA ORDENES PDF',
        'email' => 'qa.ordenes.pdf@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['OPERACIONES'],
        'supplier_id' => null,
        'is_agent' => false,
        'is_agency' => false,
        'is_admin' => false,
    ], $attributes));
}

function ordenCualquiera(): ?OperationServiceOrder
{
    return OperationServiceOrder::query()->latest('id')->first();
}

it('un analista interno de Operaciones ve cualquier orden', function (): void {
    $order = ordenCualquiera() ?? $this->markTestSkipped('No hay órdenes en la base local.');
    $analyst = usuarioDePrueba([]);
    $this->actingAs($analyst);

    expect(ServiceOrderPdfAccess::canView($analyst, $order))->toBeTrue();
});

it('un médico de Telemedicina sin proveedor ve la orden de su caso', function (): void {
    $order = ordenCualquiera() ?? $this->markTestSkipped('No hay órdenes en la base local.');
    $doctor = usuarioDePrueba(['departament' => ['TELEMEDICINA'], 'email' => 'qa.medico@tudrencasa.com']);
    $this->actingAs($doctor);

    expect(ServiceOrderPdfAccess::canView($doctor, $order))->toBeTrue();
});

it('un agente, aunque haya iniciado sesión, no ve ninguna orden', function (): void {
    $order = ordenCualquiera() ?? $this->markTestSkipped('No hay órdenes en la base local.');
    $agent = usuarioDePrueba(['email' => 'agente@gmail.com', 'departament' => [], 'is_agent' => true]);
    $this->actingAs($agent);

    expect(ServiceOrderPdfAccess::canView($agent, $order))->toBeFalse()
        ->and(ServiceOrderPdfAccess::canView(null, $order))->toBeFalse();
});

it('un proveedor ve sus órdenes y no las de otro proveedor', function (): void {
    $supplierUser = User::query()
        ->whereNotNull('supplier_id')
        ->where('status', 'ACTIVO')
        ->get()
        ->first(fn (User $user): bool => OperationServiceOrder::query()->where('telemedicine_supplier_id', $user->supplier_id)->exists()
            && (clone $user)->canAccessPanel(Filament\Facades\Filament::getPanel('operations')));

    if (! $supplierUser instanceof User) {
        $this->markTestSkipped('No hay un usuario de proveedor con órdenes propias en la base local.');
    }

    $this->actingAs($supplierUser);

    $own = OperationServiceOrder::query()->where('telemedicine_supplier_id', $supplierUser->supplier_id)->firstOrFail();
    $foreign = OperationServiceOrder::query()
        ->where(fn ($query) => $query->whereNull('telemedicine_supplier_id')->orWhere('telemedicine_supplier_id', '!=', $supplierUser->supplier_id))
        ->first() ?? $this->markTestSkipped('No hay órdenes de otro proveedor en la base local.');

    expect(ServiceOrderPdfAccess::canView($supplierUser, $own))->toBeTrue()
        ->and(ServiceOrderPdfAccess::canView($supplierUser, $foreign))->toBeFalse();
});

it('ATENMEDI solo ve las órdenes que gestiona', function (): void {
    $atenmedi = usuarioDePrueba(['departament' => ['OPERACIONES', 'ATENMEDI']]);
    $this->actingAs($atenmedi);

    $own = OperationServiceOrder::query()->where('managed_by', 'ATENMEDI')->first();
    $foreign = OperationServiceOrder::query()->where(fn ($query) => $query->whereNull('managed_by')->orWhere('managed_by', '!=', 'ATENMEDI'))->first();

    if (! $foreign instanceof OperationServiceOrder) {
        $this->markTestSkipped('No hay órdenes fuera de ATENMEDI en la base local.');
    }

    expect(ServiceOrderPdfAccess::canView($atenmedi, $foreign))->toBeFalse();

    if ($own instanceof OperationServiceOrder) {
        expect(ServiceOrderPdfAccess::canView($atenmedi, $own))->toBeTrue();
    }
});

it('no deja la sesión del usuario modificada al consultar los paneles', function (): void {
    $order = ordenCualquiera() ?? $this->markTestSkipped('No hay órdenes en la base local.');
    $inactive = usuarioDePrueba(['status' => 'INACTIVO', 'departament' => ['TELEMEDICINA']]);
    $this->actingAs($inactive);

    $before = $inactive->getAttributes();

    ServiceOrderPdfAccess::canView($inactive, $order);

    expect($inactive->status)->toBe('INACTIVO')
        ->and($inactive->getAttributes())->toBe($before);
});

it('la ruta responde 404 sin generar el PDF a quien no puede verla', function (string $routeName): void {
    $order = ordenCualquiera() ?? $this->markTestSkipped('No hay órdenes en la base local.');
    $pathBefore = $order->service_order_pdf_path;
    $filesBefore = count(Storage::disk('public')->files('operation-service-orders/generated-pdf'));

    $this->actingAs(usuarioDePrueba(['email' => 'agente@gmail.com', 'departament' => [], 'is_agent' => true]))
        ->get(route($routeName, ['operationServiceOrder' => $order]))
        ->assertNotFound();

    expect($order->fresh()->service_order_pdf_path)->toBe($pathBefore)
        ->and(count(Storage::disk('public')->files('operation-service-orders/generated-pdf')))->toBe($filesBefore);
})->with([
    'vista previa' => ['operations.operation-service-orders.pdf.preview'],
    'descarga' => ['operations.operation-service-orders.pdf'],
]);

it('la ruta sigue pidiendo sesión iniciada', function (): void {
    $order = ordenCualquiera() ?? $this->markTestSkipped('No hay órdenes en la base local.');

    $this->get(route('operations.operation-service-orders.pdf', ['operationServiceOrder' => $order]))
        ->assertRedirect();
});
