<?php

declare(strict_types=1);

use App\Models\OperationQuoteGenerator;
use App\Models\Supplier;
use App\Support\Operations\CoordinationServiceQuoteManager;
use App\Support\Operations\OperationServiceOrderProviderContacts;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

function quoteForSupplierContacts(?array $supplierContacts): OperationQuoteGenerator
{
    $supplier = $supplierContacts === null
        ? null
        : Supplier::query()->create(['name' => 'PROVEEDOR PRUEBA CONTACTOS', ...$supplierContacts]);

    return OperationQuoteGenerator::query()->create([
        'type_service' => 'LABORATORIOS',
        'items' => [],
        'supplier_id' => $supplier?->id,
        'created_by' => 'TEST',
    ]);
}

it('no pide correo ni teléfono cuando la ficha del proveedor los tiene', function (): void {
    $quote = quoteForSupplierContacts([
        'correo_principal' => 'lab@example.com',
        'personal_phone' => '04141234567',
    ]);

    expect(CoordinationServiceQuoteManager::approvedQuoteMissingSupplierContacts($quote->id))
        ->toBe(['email' => false, 'phone' => false]);
});

it('pide solo el dato que falta en la ficha', function (): void {
    $withoutPhone = quoteForSupplierContacts([
        'correo_principal' => 'lab@example.com',
        'personal_phone' => null,
        'local_phone' => '  ',
    ]);
    $withoutEmail = quoteForSupplierContacts([
        'correo_principal' => '',
        'personal_phone' => null,
        'local_phone' => '02125551234',
    ]);

    expect(CoordinationServiceQuoteManager::approvedQuoteMissingSupplierContacts($withoutPhone->id))
        ->toBe(['email' => false, 'phone' => true])
        ->and(CoordinationServiceQuoteManager::approvedQuoteMissingSupplierContacts($withoutEmail->id))
        ->toBe(['email' => true, 'phone' => false]);
});

it('pide ambos datos sin proveedor o sin cotización', function (): void {
    $quote = quoteForSupplierContacts(null);

    expect(CoordinationServiceQuoteManager::approvedQuoteMissingSupplierContacts($quote->id))
        ->toBe(['email' => true, 'phone' => true])
        ->and(CoordinationServiceQuoteManager::approvedQuoteMissingSupplierContacts(0))
        ->toBe(['email' => true, 'phone' => true])
        ->and(CoordinationServiceQuoteManager::approvedQuoteMissingSupplierContacts(999999999))
        ->toBe(['email' => true, 'phone' => true]);
});

it('el formulario de cotizaciones muestra cada contacto solo si falta en la ficha', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/ManageCoordinationServiceQuotesForm.php');
    $manager = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceQuoteManager.php');

    expect($form)
        ->toContain("approvedQuoteMissingSupplierContacts((int) \$get('approved_quote_id'))['email']")
        ->toContain("approvedQuoteMissingSupplierContacts((int) \$get('approved_quote_id'))['phone']");

    expect($manager)
        ->toContain("\$set('supplier_notify_email', null);")
        ->toContain("\$set('supplier_notify_phone', null);");
});

it('el formulario de ítems muestra cada contacto editable solo si falta en la ficha y el servicio lleva cita', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/ManageCoordinationServiceItemsForm.php');
    $manager = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceItemsManager.php');

    $emailPos = strpos($form, "TextInput::make('supplier_notify_email')");
    $phonePos = strpos($form, "TextInput::make('supplier_notify_phone')");
    $addressPos = strpos($form, "TextInput::make('supplier_notify_address')");
    $emailField = substr($form, $emailPos, $phonePos - $emailPos);
    $phoneField = substr($form, $phonePos, $addressPos - $phonePos);

    foreach ([[$emailField, 'email'], [$phoneField, 'phone']] as [$field, $key]) {
        expect($field)
            ->toContain("OperationServiceOrderProviderContacts::missingNotifyContacts(\$get)['{$key}']")
            ->toContain('MedicalAppointmentManager::serviceTypeRequiresAppointment')
            ->not->toContain('->readOnly()');
    }

    expect($manager)->toContain('OperationServiceOrderProviderContacts::appointmentNotifyContacts($data)');
});

it('avisa la cita con lo indicado en la orden y, si se ocultó, con la ficha del proveedor', function (): void {
    $supplier = Supplier::query()->create([
        'name' => 'PROVEEDOR PRUEBA CONTACTOS',
        'correo_principal' => 'ficha@example.com',
        'personal_phone' => null,
        'local_phone' => '02125551234',
    ]);

    expect(OperationServiceOrderProviderContacts::appointmentNotifyContacts(['supplier_id' => $supplier->id]))
        ->toBe(['email' => 'ficha@example.com', 'phone' => '02125551234'])
        ->and(OperationServiceOrderProviderContacts::appointmentNotifyContacts([
            'supplier_id' => $supplier->id,
            'supplier_notify_phone' => ' 04141234567 ',
        ]))
        ->toBe(['email' => 'ficha@example.com', 'phone' => '04141234567'])
        ->and(OperationServiceOrderProviderContacts::appointmentNotifyContacts([
            'register_unregistered_provider' => true,
            'supplier_id' => $supplier->id,
            'unregistered_correo_principal' => 'nuevo@example.com',
        ]))
        ->toBe(['email' => 'nuevo@example.com', 'phone' => null])
        ->and(OperationServiceOrderProviderContacts::appointmentNotifyContacts([]))
        ->toBe(['email' => null, 'phone' => null]);
});
