<?php

declare(strict_types=1);

use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Models\TelemedicineCase;
use App\Support\Operations\OperationServiceOrderCaseReference;
use App\Support\Telemedicine\TelemedicineDocumentCaseCode;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Solo lee la base; la transacción revertida garantiza que nada quede escrito.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    TelemedicineDocumentCaseCode::flush();
});

afterEach(fn () => DB::rollBack());

/**
 * @return array<string, mixed>
 */
function documentoTelemedicinaConCaso(): array
{
    return [
        'fecha' => '12/10/2026',
        'case_code' => '89928-0732',
        'code_reference' => 'REF-58180',
        'name_patient' => 'María Fernanda Rivas',
        'ci_patient' => '18456721',
        'age_patient' => 34,
        'doctor_name' => 'Dra. Carolina Pinillo',
        'code_cm' => 'CM-1',
        'code_mpps' => 'MPPS-1',
        'signature' => null,
        'medicationsArr' => [['medicines' => 'IBUPROFENO', 'indications' => 'CADA 8H']],
        'labs' => ['HEMATOLOGÍA COMPLETA'],
        'studies' => ['RX DE TÓRAX'],
        'specialists' => ['CARDIOLOGÍA'],
    ];
}

function coordinacionConCaso(string $code): OperationCoordinationService
{
    $coordination = new OperationCoordinationService(['reference_number' => 'REF-001', 'patient' => 'Paciente Demo']);
    $coordination->setRelation('telemedicineCase', new TelemedicineCase(['code' => $code]));

    return $coordination;
}

it('los documentos de telemedicina imprimen el Nro. de Caso', function (string $view): void {
    $html = view($view, ['data' => documentoTelemedicinaConCaso()])->render();

    expect($html)
        ->toContain('Nro. de Caso: <strong>89928-0732</strong>')
        // La clave del servicio sigue ahí: son dos datos distintos.
        ->toContain('REF-58180');
})->with([
    'informe médico' => 'documents.informe-medico-largo',
    'informe de seguimiento' => 'documents.informe-seguimiento',
    'récipe' => 'documents.medicamentos',
    'laboratorios' => 'documents.laboratorios',
    'imagenología' => 'documents.imagenologia',
    'especialista' => 'documents.especialista',
]);

it('un documento sin caso muestra un guion en lugar de romperse', function (): void {
    $data = documentoTelemedicinaConCaso();
    unset($data['case_code']);

    expect(view('documents.informe-seguimiento', ['data' => $data])->render())
        ->toContain('Nro. de Caso: <strong>—</strong>');
});

it('resuelve el número desde el id del caso que ya viaja en el payload', function (): void {
    $case = TelemedicineCase::query()->withoutGlobalScopes()->whereNotNull('code')->where('code', '!=', '')->first(['id', 'code']);

    if ($case === null) {
        $this->markTestSkipped('No hay casos con código en esta base.');
    }

    expect(TelemedicineDocumentCaseCode::fromData(['telemedicine_case_id' => $case->id]))->toBe(trim((string) $case->code))
        ->and(TelemedicineDocumentCaseCode::fromData(['telemedicine_case_id' => (string) $case->id, 'case_code' => '  ']))->toBe(trim((string) $case->code))
        ->and(TelemedicineDocumentCaseCode::fromData(['telemedicine_case_id' => 0]))->toBeNull()
        ->and(TelemedicineDocumentCaseCode::fromData(['telemedicine_case_id' => 'abc']))->toBeNull()
        ->and(TelemedicineDocumentCaseCode::fromData([]))->toBeNull();
});

it('la coordinación usa el caso cargado y, si no, lo busca por id', function (): void {
    expect(OperationServiceOrderCaseReference::caseCodeForCoordination(coordinacionConCaso('11111-0001')))->toBe('11111-0001')
        ->and(OperationServiceOrderCaseReference::caseCodeForCoordination(null))->toBeNull()
        ->and(OperationServiceOrderCaseReference::caseCodeForCoordination(new OperationCoordinationService))->toBeNull();

    $case = TelemedicineCase::query()->withoutGlobalScopes()->whereNotNull('code')->where('code', '!=', '')->first(['id', 'code']);

    if ($case === null) {
        return;
    }

    $coordination = new OperationCoordinationService;
    $coordination->telemedicine_case_id = $case->id;

    expect(OperationServiceOrderCaseReference::caseCodeForCoordination($coordination))->toBe(trim((string) $case->code));
});

it('la orden de servicio y sus cotizaciones imprimen el Nro. de Caso', function (): void {
    $order = new OperationServiceOrder(['order_number' => 'OS-1', 'description' => 'D', 'created_by' => 'x']);
    $order->exists = true;
    $order->setRelation('operationCoordinationService', coordinacionConCaso('22222-0002'));
    $order->setRelation('supplier', null);
    $order->setRelation('telemedicinePriority', null);

    $quote = view('documents.operation-service-order-quote-pdf', [
        'order' => $order,
        'quoteData' => ['service_label' => 'Laboratorio', 'price_usd' => 10],
        'logoDataUri' => '',
    ])->render();

    $medicationQuote = view('documents.operation-service-order-medication-quote-pdf', [
        'order' => $order,
        'quoteMeta' => ['quote_number' => 'COT-1', 'supplier_name' => 'Proveedor', 'total_amount_usd' => 10],
        'items' => [],
        'logoDataUri' => '',
    ])->render();

    expect($quote)->toContain('Nro. de Caso: <strong>22222-0002</strong>')
        ->and($medicationQuote)->toContain('Nro. de Caso: <strong>22222-0002</strong>')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/operation-service-order-pdf.blade.php'))
        ->toContain('OperationServiceOrderCaseReference::CASE_LABEL')
        ->not->toContain('>Caso: <');
});

it('la cotización generada imprime el Nro. de Caso', function (): void {
    $quote = new OperationQuoteGenerator(['type_service' => 'LABORATORIOS', 'total' => 10, 'items' => []]);
    $quote->id = 5;
    $quote->setAttribute('created_at', now());
    $quote->setRelation('supplier', null);

    $html = view('documents.operation-quote-generator-pdf', [
        'quote' => $quote,
        'coordination' => coordinacionConCaso('33333-0003'),
        'bcvRate' => 100,
        'logoDataUri' => '',
    ])->render();

    expect($html)->toContain('Nro. de Caso: <strong>33333-0003</strong>');
});

it('los reportes y la bitácora usan la misma etiqueta', function (): void {
    $base = dirname(__DIR__, 2).'/resources/views/documents/';

    expect(file_get_contents($base.'operation-service-orders-by-patient-report.blade.php'))->toContain('<th class="col-case">Nro. de Caso</th>')
        ->and(file_get_contents($base.'patient-detailed-siniestralidad-report.blade.php'))
        ->toContain('<th style="width: 13%;">Nro. de Caso</th>')
        ->toContain("{{ \$service['case_code'] ?? '—' }}")
        ->toContain('<td colspan="7" class="num">TOTAL</td>')
        ->and(file_get_contents($base.'bitacora-caso.blade.php'))->toContain('TelemedicineDocumentCaseCode::LABEL')
        ->and(file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/Reports/OperationReportBuilder.php'))
        ->toContain("['case_code', 'Nro. de Caso', false]");
});
