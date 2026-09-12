<?php

declare(strict_types=1);

use App\Enums\StatusCuentaPorPagar;
use App\Filament\Operations\Resources\AccountsPayables\AccountsPayableResource;
use App\Filament\Operations\Resources\OperationAccountsPayables\OperationAccountsPayableResource;
use App\Filament\Operations\Resources\OperationAccountsPayables\Pages\EditOperationAccountsPayable;
use App\Filament\Operations\Resources\OperationAccountsPayables\Pages\ListOperationAccountsPayables;
use App\Models\BusinessUnit;
use App\Models\OperationAccountsPayable;
use App\Models\User;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

/*
 * ---------------------------------------------------------------------------
 * Enum de estatus
 * ---------------------------------------------------------------------------
 */

it('expone los tres estatus de pago con PENDIENTE por defecto', function (): void {
    expect(StatusCuentaPorPagar::options())->toBe([
        'PENDIENTE POR PAGAR' => 'Pendiente por pagar',
        'EN GESTION' => 'En gestión',
        'PAGADA' => 'Pagada',
    ]);

    expect(StatusCuentaPorPagar::default())->toBe(StatusCuentaPorPagar::PendientePorPagar);
});

it('resuelve el estatus escrito con acento, en minúsculas o con alias heredados', function (mixed $entrada, StatusCuentaPorPagar $esperado): void {
    expect(StatusCuentaPorPagar::fromStored($entrada))->toBe($esperado);
})->with([
    'valor persistido' => ['EN GESTION', StatusCuentaPorPagar::EnGestion],
    'con acento' => ['En gestión', StatusCuentaPorPagar::EnGestion],
    'minúsculas' => ['pagada', StatusCuentaPorPagar::Pagada],
    'alias heredado' => ['PAGADO', StatusCuentaPorPagar::Pagada],
    'pendiente por pagar' => ['PENDIENTE POR PAGAR', StatusCuentaPorPagar::PendientePorPagar],
    'alias del valor anterior' => ['PENDIENTE', StatusCuentaPorPagar::PendientePorPagar],
]);

it('solo exige detalles de pago cuando la factura está pagada', function (): void {
    expect(StatusCuentaPorPagar::Pagada->requiresPaymentDetails())->toBeTrue()
        ->and(StatusCuentaPorPagar::EnGestion->requiresPaymentDetails())->toBeFalse()
        ->and(StatusCuentaPorPagar::PendientePorPagar->requiresPaymentDetails())->toBeFalse();
});

it('asigna un color distinto a cada estatus', function (): void {
    expect(StatusCuentaPorPagar::PendientePorPagar->filamentColor())->toBe('danger')
        ->and(StatusCuentaPorPagar::EnGestion->filamentColor())->toBe('warning')
        ->and(StatusCuentaPorPagar::Pagada->filamentColor())->toBe('success');
});

/*
 * ---------------------------------------------------------------------------
 * Permisología
 * ---------------------------------------------------------------------------
 */

it('registra el recurso con su propio slug de permiso', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(OperationAccountsPayableResource::class))
        ->toBe(['cuentas-por-pagar-facturas']);

    expect(DepartmentNavigationPermissionRegistry::moduleFor(OperationAccountsPayableResource::class))
        ->toBe('OPERACIONES');
});

it('no pisa el permiso del recurso de cotizaciones ya asignado a usuarios', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(AccountsPayableResource::class))
        ->toBe(['cuentas-por-pagar'])
        ->not->toBe(DepartmentNavigationPermissionRegistry::slugsFor(OperationAccountsPayableResource::class));
});

it('exige permiso para entrar: un usuario sin sesión no accede', function (): void {
    expect(OperationAccountsPayableResource::canAccess())->toBeFalse();
});

/*
 * ---------------------------------------------------------------------------
 * Campos solicitados
 * ---------------------------------------------------------------------------
 */

it('declara en el formulario los dieciséis campos pedidos', function (string $campo): void {
    $form = file_get_contents(base_path('app/Filament/Operations/Resources/OperationAccountsPayables/Schemas/OperationAccountsPayableForm.php'));

    expect($form)->toContain("make('{$campo}')");
})->with([
    'invoice_date', 'invoice_registration_date', 'supplier_name', 'supplier_rif',
    'business_unit_id', 'invoice_number', 'invoice_control_number', 'invoice_amount',
    'payment_status', 'payment_reference', 'payment_date', 'national_bank',
    'international_bank', 'payment_amount_usd', 'payment_amount_ves', 'invoice_currency',
    'payment_receipt_path',
]);

it('declara en la tabla una columna por cada campo pedido', function (string $campo): void {
    $table = file_get_contents(base_path('app/Filament/Operations/Resources/OperationAccountsPayables/Tables/OperationAccountsPayablesTable.php'));

    expect($table)->toContain("TextColumn::make('{$campo}')");
})->with([
    'invoice_date', 'invoice_registration_date', 'supplier_name', 'supplier_rif',
    'invoice_number', 'invoice_control_number', 'invoice_amount', 'payment_status',
    'payment_reference', 'payment_date', 'national_bank', 'international_bank',
    'payment_amount_usd', 'payment_amount_ves', 'payment_receipt_path',
]);

/*
 * ---------------------------------------------------------------------------
 * Modelo (escribe en DB dentro de una transacción revertida)
 * ---------------------------------------------------------------------------
 */

it('guarda una factura nueva como PENDIENTE sin datos de pago', function (): void {
    $factura = OperationAccountsPayable::factory()->create();

    expect($factura->payment_status)->toBe(StatusCuentaPorPagar::PendientePorPagar)
        ->and($factura->payment_reference)->toBeNull()
        ->and($factura->payment_date)->toBeNull()
        ->and($factura->payment_amount_usd)->toBeNull()
        ->and($factura->payment_amount_ves)->toBeNull()
        ->and($factura->isPaid())->toBeFalse();
});

it('castea fechas y montos al leerlos de vuelta', function (): void {
    $factura = OperationAccountsPayable::factory()->pagada()->create([
        'invoice_date' => '2026-03-15',
        'invoice_amount' => 1234.5678,
    ]);

    $recargada = OperationAccountsPayable::query()->findOrFail($factura->getKey());

    expect($recargada->invoice_date->format('Y-m-d'))->toBe('2026-03-15')
        ->and($recargada->invoice_amount)->toBe('1234.5678')
        ->and($recargada->payment_status)->toBe(StatusCuentaPorPagar::Pagada)
        ->and($recargada->isPaid())->toBeTrue()
        ->and($recargada->payment_date)->not->toBeNull();
});

it('congela el nombre y el RIF del proveedor en la factura', function (): void {
    $factura = OperationAccountsPayable::factory()->create([
        'supplier_name' => 'CLINICA SANTA MARIA',
        'supplier_rif' => 'J-30012345-6',
    ]);

    expect($factura->supplierLabel())->toBe('CLINICA SANTA MARIA')
        ->and($factura->supplier_rif)->toBe('J-30012345-6')
        ->and($factura->supplier_id)->toBeNull();
});

it('relaciona la factura con su unidad de negocio', function (): void {
    $unidad = BusinessUnit::query()->where('status', 'ACTIVO')->first();

    if ($unidad === null) {
        $this->markTestSkipped('No hay unidades de negocio activas en la base.');
    }

    $factura = OperationAccountsPayable::factory()->create(['business_unit_id' => $unidad->getKey()]);

    expect($factura->businessUnit->getKey())->toBe($unidad->getKey());
});

/*
 * ---------------------------------------------------------------------------
 * Render real de la pantalla
 * ---------------------------------------------------------------------------
 */

it('renderiza el listado en el panel de operaciones', function (): void {
    $usuario = User::factory()->create([
        'email' => 'qa.cuentas.pagar@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]);

    OperationAccountsPayable::factory()->create(['supplier_name' => 'PROVEEDOR DE PRUEBA QA']);

    $this->actingAs($usuario);
    Filament::setCurrentPanel('operations');

    Livewire::test(ListOperationAccountsPayables::class)
        ->assertSuccessful()
        ->assertSee('PROVEEDOR DE PRUEBA QA');
});

it('no ofrece creación manual: las cuentas nacen de la orden de servicio', function (): void {
    expect(OperationAccountsPayableResource::canCreate())->toBeFalse();

    $lista = file_get_contents(base_path('app/Filament/Operations/Resources/OperationAccountsPayables/Pages/ListOperationAccountsPayables.php'));

    expect($lista)
        ->not->toContain('CreateAction')
        ->not->toContain('Registrar factura');
});

it('oculta del menú el recurso de cotizaciones sin revocar su permiso', function (): void {
    $recurso = file_get_contents(base_path('app/Filament/Operations/Resources/AccountsPayables/AccountsPayableResource.php'));

    expect($recurso)->toContain('shouldRegisterNavigation(): bool');

    expect(DepartmentNavigationPermissionRegistry::slugsFor(AccountsPayableResource::class))
        ->toBe(['cuentas-por-pagar']);
});

it('muestra en la edición todos los campos pedidos', function (): void {
    $usuario = User::factory()->create([
        'email' => 'qa.form.cuentas.pagar@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]);
    $factura = OperationAccountsPayable::factory()->create();

    $this->actingAs($usuario);
    Filament::setCurrentPanel('operations');

    Livewire::test(EditOperationAccountsPayable::class, ['record' => $factura->getKey()])
        ->assertSuccessful()
        ->assertFormFieldExists('invoice_number')
        ->assertFormFieldExists('invoice_control_number')
        ->assertFormFieldExists('supplier_rif')
        ->assertFormFieldExists('business_unit_id')
        ->assertFormFieldExists('national_bank')
        ->assertFormFieldExists('international_bank')
        ->assertFormSet(['payment_status' => 'PENDIENTE POR PAGAR']);
});

it('rechaza guardar la factura sin los campos obligatorios', function (): void {
    $usuario = User::factory()->create([
        'email' => 'qa.val.cuentas.pagar@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]);
    $factura = OperationAccountsPayable::factory()->create();

    $this->actingAs($usuario);
    Filament::setCurrentPanel('operations');

    Livewire::test(EditOperationAccountsPayable::class, ['record' => $factura->getKey()])
        ->set('data.invoice_number', '')
        ->set('data.supplier_name', '')
        ->set('data.supplier_rif', '')
        ->call('save')
        ->assertHasFormErrors(['invoice_number', 'supplier_name', 'supplier_rif']);
});

it('exige referencia, fecha y monto cuando la factura se marca como PAGADA', function (): void {
    $usuario = User::factory()->create([
        'email' => 'qa.pago.cuentas.pagar@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]);
    $factura = OperationAccountsPayable::factory()->create([
        'business_unit_id' => BusinessUnit::query()->where('status', 'ACTIVO')->value('id'),
    ]);

    $this->actingAs($usuario);
    Filament::setCurrentPanel('operations');

    Livewire::test(EditOperationAccountsPayable::class, ['record' => $factura->getKey()])
        ->set('data.payment_status', 'PAGADA')
        ->call('save')
        ->assertHasFormErrors(['payment_reference', 'payment_date']);
});
