<?php

declare(strict_types=1);

use App\Models\OperationInventory;
use App\Support\Telemedicine\TelemedicineMedicationCoverage;
use App\Support\Telemedicine\TelemedicineMedicationsPdfRows;

uses(Tests\TestCase::class);

it('usa el nombre cubierto sin inventario cuando medicines está vacío', function (): void {
    $rows = TelemedicineMedicationsPdfRows::normalize([
        [
            'medicines' => null,
            'covered_medicines' => 'AMOXICILINA 500MG',
            'indications' => '1 CADA 8 HORAS',
            'duration' => '7',
            'operation_inventory_id' => null,
        ],
    ]);

    expect($rows[0]['medicines'])->toBe('AMOXICILINA 500MG')
        ->and($rows[0]['coverage'])->toBe('Cubierto')
        ->and($rows[0]['covered_medicines'])->toBe('AMOXICILINA 500MG');
});

it('conserva el nombre manual del medicamento', function (): void {
    $rows = TelemedicineMedicationsPdfRows::normalize([
        [
            'medicines' => 'PARACETAMOL 500MG',
            'indications' => '1 CADA 8 HORAS',
            'duration' => '5',
            'operation_inventory_id' => null,
        ],
    ]);

    expect($rows[0]['medicines'])->toBe('PARACETAMOL 500MG')
        ->and($rows[0]['indications'])->toBe('1 CADA 8 HORAS')
        ->and($rows[0]['coverage'])->toBe('No cubierto');
});

it('resuelve el nombre desde inventario cuando medicines esta vacio', function (): void {
    $inventory = OperationInventory::query()
        ->whereNotNull('name')
        ->where('name', '!=', '')
        ->first();

    if ($inventory === null) {
        $this->markTestSkipped('No hay registros en operation_inventories para validar.');
    }

    $rows = TelemedicineMedicationsPdfRows::normalize([
        [
            'medicines' => null,
            'indications' => 'WADAASDAD',
            'duration' => '3',
            'operation_inventory_id' => $inventory->id,
        ],
    ]);

    expect($rows[0]['medicines'])->toBe((string) $inventory->name)
        ->and($rows[0]['indications'])->toBe('WADAASDAD')
        ->and($rows[0]['coverage'])->toBe((bool) $inventory->is_covered ? 'Cubierto' : 'No cubierto');
});

it('el create, edit y job de medicamentos normalizan el arreglo para el pdf', function (): void {
    $create = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php'
    );
    $edit = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/EditTelemedicineConsultationPatient.php'
    );
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GeneratePdfMedicamentos.php');

    expect($create)->toContain('TelemedicineMedicationsPdfRows::normalize');
    expect($edit)->toContain('TelemedicineMedicationsPdfRows::normalize');
    expect($job)->toContain('TelemedicineCoverageDocumentSplit::medicationGroups');
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCoverageDocumentSplit.php'))
        ->toContain('TelemedicineMedicationsPdfRows::normalize');
});

it('tras normalizar sigue persistiendo cubierto sin inventario', function (): void {
    $rows = TelemedicineMedicationsPdfRows::normalize([
        [
            'medicines' => null,
            'covered_medicines' => 'AMOXICILINA 500MG',
            'indications' => '1 CADA 8 HORAS',
            'duration' => '7',
            'operation_inventory_id' => null,
        ],
    ]);

    $payload = TelemedicineMedicationCoverage::persistPayloadFromRow($rows[0]);

    expect($payload)->not->toBeNull()
        ->and($payload['is_covered'])->toBeTrue()
        ->and($payload['should_deduct_inventory'])->toBeFalse();
});
