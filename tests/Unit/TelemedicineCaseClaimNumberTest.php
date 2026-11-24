<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicineCases\Pages\ViewTelemedicineCase;
use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\OperationMedicationInvoice;
use App\Models\OperationQuoteGenerator;
use App\Models\TelemedicineCase;
use App\Models\User;
use App\Services\MedicationInvoicePdfService;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Operations\MedicationInvoiceException;
use App\Support\Operations\MedicationInvoiceIssuer;
use App\Support\Operations\QuoteInvoiceIssuer;
use App\Support\Operations\QuoteInvoicePayer;
use App\Support\Operations\ServiceInvoiceIssuer;
use App\Support\Operations\TelemedicineCaseClaimNumber;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * N.º de siniestro del caso (obligatorio para facturar) y factura con el formato de Tu Dr. en
 * Casa. Escribe dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    $this->coordination = OperationCoordinationService::query()->whereNotNull('telemedicine_case_id')->whereNotNull('telemedicine_patient_id')->latest('id')->first();

    if (! $this->coordination instanceof OperationCoordinationService || ! ($this->case = TelemedicineCase::query()->find($this->coordination->telemedicine_case_id)) instanceof TelemedicineCase) {
        $this->markTestSkipped('No hay un servicio médico con caso en la base.');
    }

    TelemedicineCase::query()->whereKey($this->case->getKey())->update(['claim_number' => null, 'claim_number_set_at' => null, 'claim_number_set_by_name' => null]);
    $this->case->refresh();
    $this->user = (new User)->forceFill(['id' => 999_999_601, 'name' => 'QA SINIESTROS', 'status' => 'ACTIVO']);
});

afterEach(function (): void {
    DB::rollBack();
});

it('normaliza el N.º de siniestro y rechaza los que no sirven', function (): void {
    expect(TelemedicineCaseClaimNumber::normalize('  2026-001  a '))->toBe('2026-001 A')
        ->and(TelemedicineCaseClaimNumber::problem('2026-00123456'))->toBeNull()
        ->and(TelemedicineCaseClaimNumber::problem(''))->toContain('Escriba')
        ->and(TelemedicineCaseClaimNumber::problem('#12'))->toContain('Use solo')
        ->and(TelemedicineCaseClaimNumber::problem(str_repeat('1', 61)))->toContain('60');
});

it('registra el siniestro en el caso y lo deja en la bitácora; corregirlo exige motivo', function (): void {
    $observations = ObservationCase::query()->where('telemedicine_case_id', $this->case->getKey())->count();

    TelemedicineCaseClaimNumber::set($this->case, '2026-00123456', null, $this->user);

    expect($this->case->claim_number)->toBe('2026-00123456')
        ->and($this->case->claim_number_set_by_name)->toBe('QA SINIESTROS')
        ->and(ObservationCase::query()->where('telemedicine_case_id', $this->case->getKey())->count())->toBe($observations + 1)
        ->and(fn () => TelemedicineCaseClaimNumber::set($this->case, '2026-00999999', '', $this->user))->toThrow(MedicationInvoiceException::class, 'motivo')
        ->and(fn () => TelemedicineCaseClaimNumber::set($this->case, '2026-00123456', 'mismo', $this->user))->toThrow(MedicationInvoiceException::class, 'ya tiene');

    TelemedicineCaseClaimNumber::set($this->case, '2026-00999999', 'La aseguradora cambió el número', $this->user);

    expect($this->case->claim_number)->toBe('2026-00999999')
        ->and(ObservationCase::query()->where('telemedicine_case_id', $this->case->getKey())->latest('id')->value('description'))->toContain('2026-00123456 → 2026-00999999');
});

it('sin N.º de siniestro no se factura: ni medicamentos, ni servicios, ni sin caso', function (): void {
    expect(fn () => TelemedicineCaseClaimNumber::requireForCase($this->case->getKey()))->toThrow(MedicationInvoiceException::class, 'siniestro')
        ->and(fn () => TelemedicineCaseClaimNumber::requireForCase(null))->toThrow(MedicationInvoiceException::class, 'caso')
        ->and(MedicationInvoiceIssuer::validationMessage($this->coordination, []))->toContain('siniestro')
        ->and(ServiceInvoiceIssuer::validationMessage($this->coordination, 'LABORATORY', []))->toContain('siniestro');

    TelemedicineCaseClaimNumber::set($this->case, 'SIN-001', null, $this->user);

    expect(TelemedicineCaseClaimNumber::requireForCase($this->case->getKey()))->toBe('SIN-001')
        ->and((string) MedicationInvoiceIssuer::validationMessage($this->coordination, []))->not->toContain('siniestro');
});

it('las tres formas de facturar exigen el siniestro antes de crear la factura', function (): void {
    $root = dirname(__DIR__, 2);

    foreach (['MedicationInvoiceIssuer', 'ServiceInvoiceIssuer', 'QuoteInvoiceIssuer'] as $issuer) {
        $source = (string) file_get_contents($root.'/app/Support/Operations/'.$issuer.'.php');
        $claim = strpos($source, '$claimNumber = TelemedicineCaseClaimNumber::requireForCase(');
        $create = strpos($source, 'OperationMedicationInvoice::query()->create([');

        expect($claim)->not->toBeFalse()
            ->and($claim < $create)->toBeTrue()
            ->and($source)->toContain("'claim_number' => \$claimNumber");
    }
});

it('la factura de una cotización exige el siniestro y copia siniestro, teléfono y correo', function (): void {
    $source = OperationQuoteGenerator::query()->where('generates_invoice', true)->whereNotNull('operation_coordination_service_id')->latest('id')->first();

    if (! $source instanceof OperationQuoteGenerator) {
        $this->markTestSkipped('No hay cotizaciones que generen factura.');
    }

    $coordination = OperationCoordinationService::query()->find($source->operation_coordination_service_id);
    $case = TelemedicineCase::query()->find($coordination?->telemedicine_case_id);

    if (! $case instanceof TelemedicineCase) {
        $this->markTestSkipped('La cotización no tiene caso.');
    }

    TelemedicineCase::query()->whereKey($case->getKey())->update(['claim_number' => null]);
    /** Libera los ítems ya facturados por la cotización original (todo se revierte al final). */
    OperationMedicationInvoice::query()->where('operation_quote_generator_id', $source->getKey())->update(['status' => OperationMedicationInvoice::STATUS_ANNULLED]);
    $quote = $source->replicate();
    $quote->forceFill(['operation_medication_invoice_id' => null, 'operation_service_order_id' => null, 'invoice_payer_phone' => '0212 555 0000', 'invoice_payer_email' => 'pagos@example.com'])->save();

    expect(fn () => QuoteInvoiceIssuer::issueForApprovedQuote($coordination, $quote, $this->user))->toThrow(MedicationInvoiceException::class, 'siniestro')
        ->and(OperationMedicationInvoice::query()->where('operation_quote_generator_id', $quote->getKey())->exists())->toBeFalse();

    TelemedicineCaseClaimNumber::set($case->refresh(), 'COT-777', null, $this->user);
    $invoice = QuoteInvoiceIssuer::issueForApprovedQuote($coordination, $quote, $this->user);

    expect($invoice instanceof OperationMedicationInvoice)->toBeTrue()
        ->and($invoice->claim_number)->toBe('COT-777')
        ->and($invoice->payer_phone)->toBe('0212 555 0000')
        ->and($invoice->payer_email)->toBe('pagos@example.com');
});

it('el responsable de pago trae teléfono y correo de la ficha, o los que escriba el analista', function (): void {
    QuoteInvoicePayer::flush();
    $snapshot = QuoteInvoicePayer::snapshot($this->coordination, OperationMedicationInvoice::PAYER_PATIENT, 'DIRECCIÓN QA', '0414 000 0000', 'Analista.QA@Example.com');

    expect($snapshot)->toHaveKey('invoice_payer_phone')
        ->and($snapshot)->toHaveKey('invoice_payer_email')
        ->and($snapshot['invoice_payer_phone'])->not->toBeNull()
        ->and($snapshot['invoice_payer_email'])->toBe(mb_strtolower((string) $snapshot['invoice_payer_email']));

    $contact = QuoteInvoicePayer::patientContact($this->coordination);
    expect($contact)->toHaveKey('address')
        ->and($contact)->toHaveKey('phone')
        ->and($contact)->toHaveKey('email');
});

it('el PDF dice NRO. DE SINIESTRO, contado, vencimiento a 2 días y montos en bolívares', function (): void {
    $invoice = OperationMedicationInvoice::query()->orderBy('id')->first();

    if (! $invoice instanceof OperationMedicationInvoice) {
        $this->markTestSkipped('No hay facturas a pacientes.');
    }

    $invoice->forceFill(['claim_number' => '2026-00123456', 'payer_phone' => '0212 213 8350', 'payer_email' => 'pagos@example.com'])->save();
    $html = MedicationInvoicePdfService::html($invoice->fresh());

    expect($html)->toContain('Nro. de siniestro: 2026-00123456', 'Condiciones de pago: CONTADO', 'F. Vcto de la factura: '.$invoice->issued_at->copy()->addDays(2)->format('d/m/Y'))
        ->and($html)->toContain('Monto exento Bs.:', 'Base imponible Bs.:', 'I.V.A. (16%) Bs.:', 'Importe en (USD)', '0212 213 8350', 'pagos@example.com')
        ->and($html)->toContain(number_format((float) $invoice->total_ves, 2, ',', '.'))
        ->and($html)->not->toContain('Propuesta')
        ->and($html)->not->toContain('no constituye factura fiscal');
});

it('la acción está en la ficha del caso con permiso propio y un superadmin la usa', function (): void {
    expect(BusinessFilamentActionPermissionRegistry::all())->toHaveKey(BusinessFilamentActionPermissionRegistry::SET_TELEMEDICINE_CASE_CLAIM_NUMBER)
        ->and(DB::table('permissions')->where('slug', 'registrar-siniestro-caso')->where('module', 'OPERACIONES')->exists())->toBeTrue();

    $admin = User::query()->where('is_superAdmin', 1)->where('status', 'ACTIVO')->first();

    if (! $admin instanceof User) {
        $this->markTestSkipped('No hay superadmin activo.');
    }

    Auth::login($admin);
    Filament::setCurrentPanel(Filament::getPanel('operations'));

    $page = Livewire::test(ViewTelemedicineCase::class, ['record' => $this->case->getKey()]);
    expect($page->html())->toContain('Registrar N.º de siniestro', 'Sin registrar: obligatorio para facturar');

    $page->call('mountAction', 'set_claim_number', [], ['schemaComponent' => 'infolist.caso-de-telemedicina::section'])
        ->set('mountedActions.0.data.number', 'qa-sin/2026')
        ->call('callMountedAction');

    expect($this->case->fresh()->claim_number)->toBe('QA-SIN/2026');
});

it('al registrar el siniestro se completa en las facturas del caso que no lo tienen, nunca en las que ya lo tienen', function (): void {
    $invoice = OperationMedicationInvoice::query()->where('status', '!=', OperationMedicationInvoice::STATUS_ANNULLED)->orderBy('id')->first();

    if (! $invoice instanceof OperationMedicationInvoice || blank($invoice->telemedicine_case_id)) {
        $this->markTestSkipped('No hay facturas con caso.');
    }

    $case = TelemedicineCase::query()->find($invoice->telemedicine_case_id);
    TelemedicineCase::query()->whereKey($case->getKey())->update(['claim_number' => null]);
    $invoice->forceFill(['claim_number' => null])->save();
    $annulled = $invoice->replicate();
    $annulled->forceFill(['invoice_number' => 'FAC-QA-ANUL', 'manual_invoice_number' => null, 'operation_quote_generator_id' => null, 'operation_service_order_id' => null, 'status' => OperationMedicationInvoice::STATUS_ANNULLED, 'claim_number' => null])->save();

    TelemedicineCaseClaimNumber::set($case->refresh(), 'SIN-COMPLETA', null, $this->user);

    expect($invoice->fresh()->claim_number)->toBe('SIN-COMPLETA')
        ->and($annulled->fresh()->claim_number)->toBeNull()
        ->and($invoice->events()->reorder('id', 'desc')->first()->event)->toBe(App\Models\OperationMedicationInvoiceEvent::EVENT_CLAIM_SET);

    TelemedicineCaseClaimNumber::set($case->refresh(), 'SIN-CORREGIDO', 'La aseguradora lo cambió', $this->user);

    expect($invoice->fresh()->claim_number)->toBe('SIN-COMPLETA');
});
