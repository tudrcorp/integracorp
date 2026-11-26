<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationMedicationInvoices\Actions\ChangeMedicationInvoicesPayerBulkAction;
use App\Filament\Operations\Resources\OperationMedicationInvoices\Actions\MedicationInvoiceActions;
use App\Models\AffiliationCorporate;
use App\Models\OperationMedicationInvoice;
use App\Models\OperationMedicationInvoiceEvent;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Services\MedicationInvoicePdfService;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Operations\MedicationInvoiceException;
use App\Support\Operations\MedicationInvoicePayerChange;
use App\Support\Operations\QuoteInvoicePayer;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Cambiar a nombre de quién va una factura a paciente (paciente o grupo corporativo), una o
 * varias. Escribe dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    QuoteInvoicePayer::flush();

    $this->invoice = OperationMedicationInvoice::query()
        ->whereIn('status', MedicationInvoicePayerChange::CHANGEABLE_STATUSES)
        ->whereNotNull('operation_coordination_service_id')
        ->orderBy('id')
        ->get()
        ->first(fn (OperationMedicationInvoice $invoice): bool => (MedicationInvoicePayerChange::options($invoice)['corporate'] ?? null) !== null);

    if (! $this->invoice instanceof OperationMedicationInvoice) {
        $this->markTestSkipped('No hay facturas de pacientes con grupo corporativo.');
    }

    $this->invoice->forceFill([
        'payer_type' => null, 'payer_name' => null, 'payer_identification' => null,
        'payer_address' => 'DIRECCION ANTERIOR', 'payer_phone' => null, 'payer_email' => null,
    ])->save();

    $this->user = (new User)->forceFill(['id' => 999_999_601, 'name' => 'QA RESPONSABLE', 'status' => 'ACTIVO']);
    $this->options = MedicationInvoicePayerChange::options($this->invoice);
});

afterEach(function (): void {
    QuoteInvoicePayer::flush();
    DB::rollBack();
});

function corporateOf(OperationMedicationInvoice $invoice): ?AffiliationCorporate
{
    $patient = TelemedicinePatient::query()->find($invoice->operationCoordinationService?->telemedicine_patient_id);

    return filled($patient?->afilliation_corporate_id) ? AffiliationCorporate::query()->withTrashed()->find($patient->afilliation_corporate_id) : null;
}

it('pasa la factura al grupo corporativo con su razón social, RIF y dirección, y lo deja en la bitácora', function (): void {
    $updated = MedicationInvoicePayerChange::change($this->invoice, OperationMedicationInvoice::PAYER_CORPORATE, 'La empresa asume el pago', null, $this->user);
    $corporate = $this->options['corporate'];

    expect($updated->payer_type)->toBe(OperationMedicationInvoice::PAYER_CORPORATE)
        ->and($updated->payer_name)->toBe($corporate['name'])
        ->and($updated->payer_identification)->toBe($corporate['identification'])
        ->and($updated->payer_address)->toBe($corporate['address'] ?? 'DIRECCION ANTERIOR')
        ->and(MedicationInvoicePayerChange::currentName($updated))->toBe($corporate['name']);

    $event = OperationMedicationInvoiceEvent::query()->where('operation_medication_invoice_id', $updated->id)->where('event', OperationMedicationInvoiceEvent::EVENT_PAYER_CHANGED)->latest('id')->first();

    expect($event)->not->toBeNull()
        ->and($event->description)->toContain('el paciente')
        ->and($event->description)->toContain('el grupo corporativo ('.$corporate['name'])
        ->and($event->description)->toContain('Motivo: La empresa asume el pago')
        ->and($event->user_name)->toBe('QA RESPONSABLE')
        ->and($event->payload['before']['payer_type'])->toBeNull();
});

it('vuelve a ponerla a nombre del paciente con sus datos', function (): void {
    MedicationInvoicePayerChange::change($this->invoice, OperationMedicationInvoice::PAYER_CORPORATE, 'Primero empresa', 'DIR EMPRESA', $this->user);
    $back = MedicationInvoicePayerChange::change($this->invoice, OperationMedicationInvoice::PAYER_PATIENT, 'El paciente la pidió a su nombre', 'DIR PACIENTE', $this->user);

    expect($back->payer_type)->toBe(OperationMedicationInvoice::PAYER_PATIENT)
        ->and($back->payer_name)->toBe($this->options['patient']['name'])
        ->and($back->payer_identification)->toBe($this->options['patient']['identification'])
        ->and($back->payer_address)->toBe($this->options['patient']['address'] ?? 'DIR PACIENTE');
});

it('si la ficha no tiene dirección usa la escrita por el analista; sin ninguna, no cambia', function (): void {
    $corporate = corporateOf($this->invoice);

    if (! $corporate instanceof AffiliationCorporate) {
        $this->markTestSkipped('El paciente no tiene afiliación corporativa directa.');
    }

    $corporate->forceFill(['address' => ''])->saveQuietly();
    QuoteInvoicePayer::flush();

    expect(fn () => MedicationInvoicePayerChange::change($this->invoice, OperationMedicationInvoice::PAYER_CORPORATE, 'Sin dirección', null, $this->user))
        ->toThrow(MedicationInvoiceException::class, 'no tiene dirección');

    expect($this->invoice->fresh()->payer_type)->toBeNull();

    $updated = MedicationInvoicePayerChange::change($this->invoice, OperationMedicationInvoice::PAYER_CORPORATE, 'Con dirección escrita', 'AV. PRINCIPAL, CARACAS', $this->user);

    expect($updated->payer_address)->toBe('AV. PRINCIPAL, CARACAS');
});

it('toma la dirección de la ficha seleccionada aunque la factura tenga otra', function (): void {
    $corporate = corporateOf($this->invoice);

    if (! $corporate instanceof AffiliationCorporate) {
        $this->markTestSkipped('El paciente no tiene afiliación corporativa directa.');
    }

    $corporate->forceFill(['address' => 'TORRE QA, PISO 3'])->saveQuietly();
    QuoteInvoicePayer::flush();

    $updated = MedicationInvoicePayerChange::change($this->invoice, OperationMedicationInvoice::PAYER_CORPORATE, 'Dirección de la ficha', 'IGNORADA', $this->user);

    expect($updated->payer_address)->toBe('TORRE QA, PISO 3');
});

it('rechaza anuladas, sin motivo, tipo inválido y el cambio que no cambia nada', function (): void {
    expect(fn () => MedicationInvoicePayerChange::change($this->invoice, 'OTRO', 'Motivo válido', null, $this->user))->toThrow(MedicationInvoiceException::class, 'Indique a nombre')
        ->and(fn () => MedicationInvoicePayerChange::change($this->invoice, OperationMedicationInvoice::PAYER_CORPORATE, ' no ', null, $this->user))->toThrow(MedicationInvoiceException::class, 'motivo');

    MedicationInvoicePayerChange::change($this->invoice, OperationMedicationInvoice::PAYER_CORPORATE, 'Primer cambio', 'DIR', $this->user);

    expect(fn () => MedicationInvoicePayerChange::change($this->invoice, OperationMedicationInvoice::PAYER_CORPORATE, 'Otra vez', 'DIR', $this->user))->toThrow(MedicationInvoiceException::class, 'ya está a nombre de');

    $this->invoice->forceFill(['status' => OperationMedicationInvoice::STATUS_ANNULLED])->save();

    expect(MedicationInvoicePayerChange::isChangeable($this->invoice))->toBeFalse()
        ->and(fn () => MedicationInvoicePayerChange::change($this->invoice, OperationMedicationInvoice::PAYER_PATIENT, 'Anulada', 'DIR', $this->user))->toThrow(MedicationInvoiceException::class, 'anulada');
});

it('permite cambiar una factura cobrada', function (): void {
    $this->invoice->forceFill(['status' => OperationMedicationInvoice::STATUS_PAID])->save();

    expect(MedicationInvoicePayerChange::isChangeable($this->invoice))->toBeTrue()
        ->and(MedicationInvoicePayerChange::change($this->invoice, OperationMedicationInvoice::PAYER_CORPORATE, 'Cobrada a la empresa', 'DIR', $this->user)->payer_type)
        ->toBe(OperationMedicationInvoice::PAYER_CORPORATE);
});

it('un afiliado sin grupo corporativo no puede pasar a nombre del grupo', function (): void {
    $patient = TelemedicinePatient::query()->find($this->invoice->operationCoordinationService?->telemedicine_patient_id);
    $patient->forceFill(['type_affiliation' => 'INDIVIDUAL', 'afilliation_corporate_id' => null, 'nro_identificacion' => 'QA'.random_int(100000, 999999)])->saveQuietly();
    QuoteInvoicePayer::flush();

    if (MedicationInvoicePayerChange::options($this->invoice->fresh())['corporate'] !== null) {
        $this->markTestSkipped('El paciente sigue vinculado a una afiliación corporativa por otra vía.');
    }

    expect(fn () => MedicationInvoicePayerChange::change($this->invoice->fresh(), OperationMedicationInvoice::PAYER_CORPORATE, 'No aplica', 'DIR', $this->user))
        ->toThrow(MedicationInvoiceException::class, 'afiliado individual');
});

it('el masivo cambia las que puede y omite las demás con el motivo', function (): void {
    $annulled = $this->invoice->replicate();
    $annulled->forceFill(['invoice_number' => 'FAC-QA-'.random_int(100000, 999999), 'manual_invoice_number' => null, 'operation_quote_generator_id' => null, 'operation_service_order_id' => null, 'status' => OperationMedicationInvoice::STATUS_ANNULLED])->save();

    $result = MedicationInvoicePayerChange::changeMany([$this->invoice->id, $annulled->id], OperationMedicationInvoice::PAYER_CORPORATE, 'Cambio masivo QA', $this->user);

    expect($result['changed'])->toBe([$this->invoice->displayNumber()])
        ->and($result['skipped'])->toHaveCount(1)
        ->and($result['skipped'][0]['number'])->toBe($annulled->displayNumber())
        ->and($result['skipped'][0]['reason'])->toContain('anulada')
        ->and($this->invoice->fresh()->payer_type)->toBe(OperationMedicationInvoice::PAYER_CORPORATE)
        ->and($annulled->fresh()->payer_type)->toBeNull();
});

it('el PDF sale a nombre del grupo corporativo después del cambio', function (): void {
    $updated = MedicationInvoicePayerChange::change($this->invoice, OperationMedicationInvoice::PAYER_CORPORATE, 'PDF a la empresa', 'DIR', $this->user);
    $html = MedicationInvoicePdfService::html($updated);

    expect($html)->toContain('Cliente: '.e($updated->payer_name))
        ->and($html)->toContain('RIF:')
        ->and($html)->toContain('Paciente: '.e($updated->patient_name));
});

it('tiene permiso propio y la acción está en los tres puntos, en la ficha y en el masivo', function (): void {
    $root = dirname(__DIR__, 2);
    $table = (string) file_get_contents($root.'/app/Filament/Operations/Resources/OperationMedicationInvoices/Tables/OperationMedicationInvoicesTable.php');
    $view = (string) file_get_contents($root.'/app/Filament/Operations/Resources/OperationMedicationInvoices/Pages/ViewOperationMedicationInvoice.php');

    expect(BusinessFilamentActionPermissionRegistry::all())->toHaveKey(BusinessFilamentActionPermissionRegistry::CHANGE_MEDICATION_INVOICE_PAYER)
        ->and(DB::table('permissions')->where('slug', BusinessFilamentActionPermissionRegistry::CHANGE_MEDICATION_INVOICE_PAYER)->where('module', 'OPERACIONES')->exists())->toBeTrue()
        ->and($table)->toContain('MedicationInvoiceActions::changePayer()')
        ->and($table)->toContain('ChangeMedicationInvoicesPayerBulkAction::make()')
        ->and($view)->toContain('MedicationInvoiceActions::changePayer()')
        ->and(MedicationInvoiceActions::changePayer()->getName())->toBe('change_medication_invoice_payer')
        ->and(ChangeMedicationInvoicesPayerBulkAction::make()->getName())->toBe('change_medication_invoices_payer');
});

it('el RIF sale con la letra en mayúscula pegada a los dígitos y «J» si no trae letra', function (mixed $raw, ?string $expected): void {
    expect(QuoteInvoicePayer::formatRif($raw))->toBe($expected);
})->with([
    'sin letra' => ['000214107', 'J000214107'],
    'con guion' => ['J-40906370-4', 'J409063704'],
    'minúscula y espacios' => ['j 409063704', 'J409063704'],
    'gubernamental' => ['G-20000123-1', 'G200001231'],
    'vacío' => ['', null],
    'nulo' => [null, null],
]);

it('el PDF trae el RIF con J, vence el mismo día de la emisión y la tasa va bajo «B.C.V:»', function (): void {
    $updated = MedicationInvoicePayerChange::change($this->invoice, OperationMedicationInvoice::PAYER_CORPORATE, 'PDF formato', 'DIR', $this->user);
    $updated->forceFill(['payer_identification' => '000214107'])->save();
    $html = MedicationInvoicePdfService::html($updated);

    expect($html)->toContain('RIF: J000214107')
        ->and($html)->toContain('F. Vcto de la factura: '.$updated->issued_at->format('d/m/Y'))
        ->and($updated->dueDate()->format('Y-m-d'))->toBe($updated->issued_at->format('Y-m-d'))
        ->and($html)->toMatch('/<td class="bcv">B\.C\.V:<\/td>/')
        ->and($html)->toContain('<td class="bcv">'.number_format((float) $updated->bcv_rate, 4, ',', '.').'</td>');
});
