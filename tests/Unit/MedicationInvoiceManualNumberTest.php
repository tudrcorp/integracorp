<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationMedicationInvoices\Pages\ViewOperationMedicationInvoice;
use App\Models\OperationAccountsReceivable;
use App\Models\OperationMedicationInvoice;
use App\Models\OperationMedicationInvoiceEvent;
use App\Models\User;
use App\Services\MedicationInvoicePdfService;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Operations\AccountsReceivablePresenter;
use App\Support\Operations\MedicationInvoiceException;
use App\Support\Operations\MedicationInvoiceLifecycle;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Número de factura registrado a mano: reemplaza al FAC-… en lo que se muestra, sin tocar el
 * número interno. Escribe dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    $this->invoice = OperationMedicationInvoice::query()->where('status', '!=', OperationMedicationInvoice::STATUS_ANNULLED)->orderBy('id')->first();

    if (! $this->invoice instanceof OperationMedicationInvoice) {
        $this->markTestSkipped('No hay facturas a pacientes en la base.');
    }

    $this->invoice->forceFill(['manual_invoice_number' => null, 'manual_invoice_number_set_at' => null, 'manual_invoice_number_set_by_name' => null])->save();
    $this->user = (new User)->forceFill(['id' => 999_999_501, 'name' => 'QA FACTURAS', 'status' => 'ACTIVO']);
});

afterEach(function (): void {
    DB::rollBack();
});

function otherMedicationInvoice(OperationMedicationInvoice $source): OperationMedicationInvoice
{
    $copy = $source->replicate();
    $copy->forceFill(['invoice_number' => 'FAC-QA-'.random_int(100000, 999999), 'manual_invoice_number' => null, 'operation_quote_generator_id' => null, 'operation_service_order_id' => null])->save();

    return $copy;
}

it('normaliza el número y rechaza los que no sirven', function (): void {
    expect(MedicationInvoiceLifecycle::normalizeManualNumber('  00-12   345a '))->toBe('00-12 345A')
        ->and(MedicationInvoiceLifecycle::manualNumberProblem('00012345'))->toBeNull()
        ->and(MedicationInvoiceLifecycle::manualNumberProblem('A-001/2026'))->toBeNull()
        ->and(MedicationInvoiceLifecycle::manualNumberProblem(''))->toContain('Escriba')
        ->and(MedicationInvoiceLifecycle::manualNumberProblem('-001'))->toContain('empezando')
        ->and(MedicationInvoiceLifecycle::manualNumberProblem('FAC#1'))->toContain('Use solo')
        ->and(MedicationInvoiceLifecycle::manualNumberProblem(str_repeat('9', 41)))->toContain('40');
});

it('registra el número, lo muestra en lugar del FAC y deja el interno y la bitácora', function (): void {
    $internal = (string) $this->invoice->invoice_number;
    $events = $this->invoice->events()->count();

    $updated = MedicationInvoiceLifecycle::setManualNumber($this->invoice, ' 00012345 ', null, $this->user);

    expect($updated->manual_invoice_number)->toBe('00012345')
        ->and($updated->invoice_number)->toBe($internal)
        ->and($updated->displayNumber())->toBe('00012345')
        ->and($updated->manual_invoice_number_set_by_name)->toBe('QA FACTURAS')
        ->and($this->invoice->events()->count())->toBe($events + 1);

    $event = $this->invoice->events()->reorder('id', 'desc')->first();
    expect($event->event)->toBe(OperationMedicationInvoiceEvent::EVENT_NUMBER_SET)
        ->and($event->description)->toContain('00012345', $internal);
});

it('corregir el número exige motivo y guarda el valor anterior', function (): void {
    MedicationInvoiceLifecycle::setManualNumber($this->invoice, '00012345', null, $this->user);

    expect(fn () => MedicationInvoiceLifecycle::setManualNumber($this->invoice, '00012346', '', $this->user))->toThrow(MedicationInvoiceException::class, 'motivo')
        ->and(fn () => MedicationInvoiceLifecycle::setManualNumber($this->invoice, '00012345', 'mismo número', $this->user))->toThrow(MedicationInvoiceException::class, 'ya tiene');

    $updated = MedicationInvoiceLifecycle::setManualNumber($this->invoice, '00012346', 'Se transcribió mal', $this->user);
    $event = $this->invoice->events()->reorder('id', 'desc')->first();

    expect($updated->displayNumber())->toBe('00012346')
        ->and($event->payload['previous'])->toBe('00012345')
        ->and($event->description)->toContain('00012345 → 00012346', 'Se transcribió mal');
});

it('no repite el número de otra factura, ni su número interno', function (): void {
    $other = otherMedicationInvoice($this->invoice);
    MedicationInvoiceLifecycle::setManualNumber($other, 'B-777', null, $this->user);

    expect(fn () => MedicationInvoiceLifecycle::setManualNumber($this->invoice, 'b-777', null, $this->user))->toThrow(MedicationInvoiceException::class, 'ya lo tiene')
        ->and(fn () => MedicationInvoiceLifecycle::setManualNumber($this->invoice, $other->invoice_number, null, $this->user))->toThrow(MedicationInvoiceException::class, 'ya lo tiene')
        ->and($this->invoice->fresh()->manual_invoice_number)->toBeNull();
});

it('no registra número en una factura anulada', function (): void {
    $this->invoice->forceFill(['status' => OperationMedicationInvoice::STATUS_ANNULLED])->save();

    expect(fn () => MedicationInvoiceLifecycle::setManualNumber($this->invoice, '00099999', null, $this->user))->toThrow(MedicationInvoiceException::class, 'anulada');
});

it('el PDF, la cuenta por cobrar y el nombre del archivo usan el número registrado', function (): void {
    $updated = MedicationInvoiceLifecycle::setManualNumber($this->invoice, '00012345', null, $this->user);
    $html = MedicationInvoicePdfService::html($updated);
    $receivable = (new OperationAccountsReceivable)->setRelation('operationMedicationInvoice', $updated);

    expect($html)->toContain('Factura No. 00012345')
        ->and($html)->not->toContain('Factura No. '.$updated->invoice_number)
        ->and(MedicationInvoicePdfService::filename($updated))->toBe('factura-00012345.pdf')
        ->and(AccountsReceivablePresenter::medicationInvoiceNumber($receivable))->toBe('00012345');
});

it('la acción está en el menú de la factura y en la tabla, con permiso propio', function (): void {
    $root = dirname(__DIR__, 2);

    expect(BusinessFilamentActionPermissionRegistry::all())->toHaveKey(BusinessFilamentActionPermissionRegistry::SET_MEDICATION_INVOICE_NUMBER)
        ->and(DB::table('permissions')->where('slug', BusinessFilamentActionPermissionRegistry::SET_MEDICATION_INVOICE_NUMBER)->where('module', 'OPERACIONES')->exists())->toBeTrue()
        ->and((string) file_get_contents($root.'/app/Filament/Operations/Resources/OperationMedicationInvoices/Pages/ViewOperationMedicationInvoice.php'))->toContain('MedicationInvoiceActions::setNumber()')
        ->and((string) file_get_contents($root.'/app/Filament/Operations/Resources/OperationMedicationInvoices/Tables/OperationMedicationInvoicesTable.php'))->toContain('MedicationInvoiceActions::setNumber()');
});

it('un superadmin registra el número desde el menú de acciones de la factura', function (): void {
    $admin = User::query()->where('is_superAdmin', 1)->where('status', 'ACTIVO')->first();

    if (! $admin instanceof User) {
        $this->markTestSkipped('No hay superadmin activo.');
    }

    Auth::login($admin);
    Filament::setCurrentPanel(Filament::getPanel('operations'));

    Livewire::test(ViewOperationMedicationInvoice::class, ['record' => $this->invoice->getKey()])
        ->call('mountAction', 'set_medication_invoice_number')
        ->set('mountedActions.0.data.number', 'qa-2026/001')
        ->call('callMountedAction');

    expect($this->invoice->fresh()->manual_invoice_number)->toBe('QA-2026/001');
});
