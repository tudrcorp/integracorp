<?php

declare(strict_types=1);

use App\Enums\OperationReportFormat;
use App\Enums\OperationReportType;
use App\Filament\Operations\Pages\GeneradorDeReportes;
use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderItem;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatientMedications;
use App\Models\User;
use App\Support\Operations\Reports\CaseMedicationReportBuilder;
use App\Support\Operations\Reports\OperationReportBuilder;
use App\Support\Operations\Reports\OperationReportFilters;
use App\Support\Operations\Reports\OperationReportGenerator;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Escribe dentro de una transacción que siempre se revierte. Si PHPUnit fuerza
 * sqlite vacío (sin migraciones), se omite el bloque de integración.
 */
beforeEach(function (): void {
    if (! Schema::hasTable('telemedicine_patient_medications')
        || ! Schema::hasTable('operation_quote_generators')
        || ! Schema::hasTable('operation_service_order_items')) {
        $this->markTestSkipped('Faltan tablas de medicamentos, cotización u órdenes en esta conexión.');
    }

    DB::beginTransaction();

    Filament::setCurrentPanel('operations');

    $this->actingAs(User::factory()->make([
        'id' => 999_790,
        'name' => 'Analista Medicamentos',
        'email' => 'analista.medicamentos@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]));
});

afterEach(function (): void {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

/**
 * @return array{
 *     covered: TelemedicinePatientMedications,
 *     uncovered: TelemedicinePatientMedications,
 *     coordination: OperationCoordinationService,
 *     case: TelemedicineCase
 * }
 */
function cmrScenario(): array
{
    $case = TelemedicineCase::query()->whereNotNull('telemedicine_patient_id')->orderByDesc('id')->first();

    if (! $case instanceof TelemedicineCase) {
        test()->markTestSkipped('No hay casos de telemedicina para armar el escenario.');
    }

    $coordination = OperationCoordinationService::query()->create([
        'telemedicine_case_id' => $case->id,
        'telemedicine_patient_id' => $case->telemedicine_patient_id,
        'patient' => 'PACIENTE CMR',
        'ci_patient' => 'V-9999999',
        'contractor' => 'CMR',
        'relationship_patient' => 'TITULAR',
        'symptoms_diagnosis' => 'PRUEBA CMR',
        'status' => 'PENDIENTE',
        'managed_by' => 'TDG',
        'created_by' => 'CMR',
    ]);

    $covered = TelemedicinePatientMedications::query()->create([
        'telemedicine_patient_id' => $case->telemedicine_patient_id,
        'telemedicine_case_id' => $case->id,
        'medicine' => 'CMR CUBIERTO 500MG',
        'indications' => 'NO DEBE APARECER EN EL REPORTE',
        'quantity' => 1,
        'status' => 'PENDIENTE',
        'is_covered' => true,
        'operation_coordination_service_id' => $coordination->id,
    ]);

    $uncovered = TelemedicinePatientMedications::query()->create([
        'telemedicine_patient_id' => $case->telemedicine_patient_id,
        'telemedicine_case_id' => $case->id,
        'medicine' => 'CMR NO CUBIERTO 250MG',
        'indications' => 'TAMPOCO DEBE APARECER',
        'quantity' => 2,
        'status' => 'PENDIENTE',
        'is_covered' => false,
        'operation_coordination_service_id' => $coordination->id,
    ]);

    OperationQuoteGenerator::query()->create([
        'telemedicine_patient_id' => $case->telemedicine_patient_id,
        'telemedicine_case_id' => $case->id,
        'operation_coordination_service_id' => $coordination->id,
        'type_service' => 'MEDICAMENTOS',
        'status' => OperationQuoteGenerator::STATUS_APPROVED,
        'costo_dolares' => 15,
        'costo_bolivares' => 1500,
        'porcentaje_ganancia' => 20,
        'subtotal' => 18,
        'total' => 18,
        'items' => [
            [
                'key' => 'medication:'.$uncovered->id,
                'label' => $uncovered->medicine,
                'category' => 'MEDICAMENTOS',
                'unit_price_usd' => 15,
                'profit_percentage' => 20,
            ],
        ],
        'created_by' => 'CMR',
    ]);

    $order = OperationServiceOrder::query()->create([
        'operation_coordination_service_id' => $coordination->id,
        'telemedicine_case_id' => $case->id,
        'telemedicine_patient_id' => $case->telemedicine_patient_id,
        'order_number' => 'OS-CMR-'.uniqid(),
        'service_type' => 'MEDICAMENTOS',
        'total_amount_usd' => 15,
        'created_by' => 'CMR',
    ]);

    OperationServiceOrderItem::query()->create([
        'operation_service_order_id' => $order->id,
        'telemedicine_patient_medication_id' => $uncovered->id,
        'item_name' => $uncovered->medicine,
        'category' => 'MEDICAMENTOS',
        'quantity' => 2,
        'amount' => 15,
        'currency' => 'USD',
        'created_by' => 'CMR',
    ]);

    return compact('covered', 'uncovered', 'coordination', 'case');
}

it('lista cubiertos y no cubiertos con costos y sin indicaciones', function (): void {
    cmrScenario();
    $filters = OperationReportFilters::fromArray([
        'period' => OperationReportFilters::PERIOD_CUSTOM,
        'from' => now()->subDay()->toDateString(),
        'to' => now()->toDateString(),
    ]);

    expect(CaseMedicationReportBuilder::count($filters))->toBeGreaterThanOrEqual(2);

    $data = CaseMedicationReportBuilder::build(OperationReportType::CaseMedications, $filters);
    $rows = iterator_to_array($data->rows, false);
    $medicines = array_column($rows, 4);
    $serialized = json_encode($rows, JSON_THROW_ON_ERROR);

    expect($medicines)->toContain('CMR CUBIERTO 500MG', 'CMR NO CUBIERTO 250MG')
        ->and($serialized)->not->toContain('NO DEBE APARECER')
        ->and($serialized)->not->toContain('TAMPOCO DEBE APARECER')
        ->and($data->sections)->not->toBeEmpty()
        ->and($data->sections[0]['title'])->toBe('Conciliación por caso y paciente');

    $uncoveredRow = collect($rows)->first(fn (array $row): bool => $row[4] === 'CMR NO CUBIERTO 250MG');

    expect($uncoveredRow)->not->toBeNull()
        ->and($uncoveredRow[13])->toBe('No cubierto')
        ->and($uncoveredRow[16])->toBe(15.0)
        ->and($uncoveredRow[17])->toBe(18.0)
        ->and($uncoveredRow[19])->toBe(15.0);

    $coveredOnly = CaseMedicationReportBuilder::build(
        OperationReportType::CaseMedications,
        OperationReportFilters::fromArray([
            'period' => OperationReportFilters::PERIOD_CUSTOM,
            'from' => now()->subDay()->toDateString(),
            'to' => now()->toDateString(),
            'coverages' => [CaseMedicationReportBuilder::COVERAGE_COVERED],
        ]),
    );

    expect(array_column(iterator_to_array($coveredOnly->rows, false), 4))
        ->toContain('CMR CUBIERTO 500MG')
        ->not->toContain('CMR NO CUBIERTO 250MG');
});

it('concilia por caso y paciente cubiertos frente a no cubiertos', function (): void {
    $scenario = cmrScenario();
    $filters = OperationReportFilters::fromArray([
        'period' => OperationReportFilters::PERIOD_CUSTOM,
        'from' => now()->subDay()->toDateString(),
        'to' => now()->toDateString(),
    ]);

    $data = CaseMedicationReportBuilder::build(OperationReportType::CaseMedications, $filters);
    $caseCode = $scenario['case']->code;
    $conciliation = collect($data->sections[0]['rows'])
        ->first(fn (array $row): bool => $row[0] === $caseCode);

    expect($conciliation)->not->toBeNull()
        ->and($conciliation[3])->toBeGreaterThanOrEqual(2)
        ->and($conciliation[4])->toBeGreaterThanOrEqual(1)
        ->and($conciliation[5])->toBeGreaterThanOrEqual(1)
        ->and($conciliation[6])->toBeGreaterThanOrEqual(15.0)
        ->and($conciliation[8])->toBeGreaterThanOrEqual(15.0);
});

it('genera excel, csv y pdf con la sección de conciliación', function (string $format): void {
    Storage::fake(OperationReportGenerator::DISK);
    cmrScenario();

    $type = OperationReportType::CaseMedications;
    $reportFormat = OperationReportFormat::from($format);
    $filters = OperationReportFilters::fromArray([
        'period' => OperationReportFilters::PERIOD_CUSTOM,
        'from' => now()->subDay()->toDateString(),
        'to' => now()->toDateString(),
    ]);
    $filename = OperationReportGenerator::filename($type, $reportFormat);
    $path = OperationReportGenerator::store($type, $reportFormat, $filters, 999_790, $filename);
    $contents = (string) Storage::disk(OperationReportGenerator::DISK)->get($path);

    expect(strlen($contents))->toBeGreaterThan(100);

    if ($reportFormat === OperationReportFormat::Csv) {
        expect($contents)->toContain('Conciliación por caso y paciente')
            ->toContain('CMR CUBIERTO 500MG')
            ->not->toContain('NO DEBE APARECER');
    }

    if ($reportFormat === OperationReportFormat::Pdf) {
        expect($contents)->toStartWith('%PDF');
    }
})->with(['xlsx', 'csv', 'pdf']);

it('aparece en el generador de reportes en la sección Medicamentos', function (): void {
    Livewire::test(GeneradorDeReportes::class)
        ->assertSuccessful()
        ->assertSee('Medicamentos')
        ->assertSee('Medicamentos por caso (cubiertos y no cubiertos)')
        ->set('data.type', OperationReportType::CaseMedications->value)
        ->assertSee('Por fecha en que el médico asignó el medicamento al caso.');
});

it('el orquestador despacha el builder de medicamentos por caso', function (): void {
    cmrScenario();
    $filters = OperationReportFilters::fromArray([
        'period' => OperationReportFilters::PERIOD_CUSTOM,
        'from' => now()->subDay()->toDateString(),
        'to' => now()->toDateString(),
    ]);

    expect(OperationReportBuilder::count(OperationReportType::CaseMedications, $filters))
        ->toBe(CaseMedicationReportBuilder::count($filters))
        ->and(OperationReportBuilder::build(OperationReportType::CaseMedications, $filters)->title)
        ->toBe(OperationReportType::CaseMedications->label());
});
