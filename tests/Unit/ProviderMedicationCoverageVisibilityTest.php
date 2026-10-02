<?php

declare(strict_types=1);

use App\Models\OperationCoordinationService;
use App\Models\Supplier;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatientMedications;
use App\Models\User;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Telemedicine\TelemedicineMedicationCoverage;
use App\Support\Telemedicine\TelemedicineMedicationInventoryOptions;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

function providerAnalystFor(int $supplierId): User
{
    $user = User::query()->where('supplier_id', $supplierId)->where('is_proveedor_amd', true)->first();

    if ($user === null) {
        test()->markTestSkipped("No hay analistas del proveedor {$supplierId}.");
    }

    return $user;
}

/**
 * Un servicio de proveedor sin asignación de TDG, cuyo único ítem es un medicamento manual.
 *
 * @return array{service: OperationCoordinationService, medication: TelemedicinePatientMedications}
 */
function providerServiceWithOnlyManualMedication(): array
{
    $service = OperationCoordinationService::query()
        ->whereNotNull('supplier_id')
        ->where('assigned_to_supplier_by_tdg', false)
        ->whereHas('telemedicinePatientMedications', fn ($medications) => $medications->whereNull('operation_inventory_id'))
        ->whereDoesntHave('telemedicinePatientMedications', fn ($medications) => $medications->whereNotNull('operation_inventory_id'))
        ->whereDoesntHave('telemedicinePatientLabs')
        ->latest('id')
        ->first();

    if ($service === null) {
        test()->markTestSkipped('No hay servicios de proveedor con solo medicamentos manuales.');
    }

    return [
        'service' => $service,
        'medication' => TelemedicinePatientMedications::query()->where('operation_coordination_service_id', $service->id)->firstOrFail(),
    ];
}

it('la regla SQL de cobertura coincide con la de la etiqueta CUB en todos los medicamentos', function (): void {
    $medications = TelemedicinePatientMedications::query()->with('operationInventory:id,is_covered')->get();

    if ($medications->isEmpty()) {
        $this->markTestSkipped('No hay medicamentos.');
    }

    $expected = $medications
        ->filter(fn (TelemedicinePatientMedications $medication): bool => TelemedicineMedicationCoverage::isCovered($medication))
        ->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();

    $fromSql = TelemedicineMedicationCoverage::whereCovered(TelemedicinePatientMedications::query())
        ->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();

    expect($fromSql)->toBe($expected);
});

it('el analista de ATENMEDI ve el servicio del caso 78206-0805, que tiene un medicamento manual cubierto', function (): void {
    $service = OperationCoordinationService::query()->find(1519);

    if ($service === null || (int) $service->supplier_id !== 15) {
        $this->markTestSkipped('El servicio 1519 de ATENMEDI no existe en esta base.');
    }

    $this->actingAs(providerAnalystFor(15));

    expect(OperationsSupplierScope::coordinationServiceQuery()->whereKey(1519)->exists())->toBeTrue();
});

it('el proveedor ve el servicio solo si su medicamento manual está marcado como cubierto', function (): void {
    ['service' => $service, 'medication' => $medication] = providerServiceWithOnlyManualMedication();
    $this->actingAs(providerAnalystFor((int) $service->supplier_id));

    $visible = fn (): bool => OperationsSupplierScope::coordinationServiceQuery()->whereKey($service->id)->exists();

    DB::table('telemedicine_patient_medications')->where('operation_coordination_service_id', $service->id)->update(['is_covered' => false]);
    expect($visible())->toBeFalse();

    DB::table('telemedicine_patient_medications')->where('id', $medication->id)->update(['is_covered' => true]);
    expect($visible())->toBeTrue();
});

it('un servicio de otro proveedor no aparece aunque tenga un medicamento cubierto', function (): void {
    ['service' => $service, 'medication' => $medication] = providerServiceWithOnlyManualMedication();
    DB::table('telemedicine_patient_medications')->where('id', $medication->id)->update(['is_covered' => true]);

    $otherSupplierId = (int) Supplier::query()->whereKeyNot($service->supplier_id)->value('id');
    $this->actingAs((new User)->forceFill(['id' => 999_996, 'supplier_id' => $otherSupplierId, 'is_proveedor_amd' => true, 'departament' => ['OPERACIONES']]));

    expect(OperationsSupplierScope::coordinationServiceQuery()->whereKey($service->id)->exists())->toBeFalse();
});

it('el médico de un proveedor registra la cobertura de su proveedor y el de TDG usa el inventario', function (): void {
    $tdg = new TelemedicineDoctor(['managed_by' => 'TDG', 'supplier_id' => null]);
    $provider = new TelemedicineDoctor(['managed_by' => 'CORPORACION VMC, C.A. (ATENMEDI)', 'supplier_id' => 15]);
    $provider->setRelation('supplier', new Supplier(['name' => "CORPORACION VMC,\nC.A.", 'integracorp_alias' => 'ATENMEDI']));
    $withoutAlias = new TelemedicineDoctor(['supplier_id' => 428]);
    $withoutAlias->setRelation('supplier', new Supplier(['name' => "AUXILIO  MEDICO 24\n", 'integracorp_alias' => null]));
    $orphan = new TelemedicineDoctor(['supplier_id' => 999]);
    $orphan->setRelation('supplier', null);

    expect(TelemedicineMedicationInventoryOptions::prescriberUsesProviderCoverage($tdg))->toBeFalse()
        ->and(TelemedicineMedicationInventoryOptions::prescriberUsesProviderCoverage(null))->toBeFalse()
        ->and(TelemedicineMedicationInventoryOptions::prescriberUsesProviderCoverage($provider))->toBeTrue()
        ->and(TelemedicineMedicationInventoryOptions::providerCoverageLabel($provider))->toBe('Cubierto por ATENMEDI')
        ->and(TelemedicineMedicationInventoryOptions::providerCoverageLabel($withoutAlias))->toBe('Cubierto por AUXILIO MEDICO 24')
        ->and(TelemedicineMedicationInventoryOptions::providerCoverageLabel($orphan))->toBe('Cubierto por el proveedor');
});

it('el formulario oculta el inventario TDC al médico de proveedor y lo rechaza en el servidor', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php');
    $create = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');

    expect($form)
        ->toContain('TelemedicineConsultationSigningDoctor::forUser(Auth::user())')
        ->toContain('TelemedicineMedicationInventoryOptions::prescriberUsesProviderCoverage($prescriber)')
        ->toContain("\$providerCoverage ? null : TableColumn::make('Inventario TDC')")
        ->toContain('...($providerCoverage ? [] : [')
        ->toContain('TableColumn::make($coveredColumnLabel)')
        ->toContain('TelemedicineMedicationCoverage::providerInventoryError($rowNumber, $coveredColumnLabel)')
        ->and($create)
        ->toContain('TelemedicineMedicationInventoryOptions::prescriberUsesProviderCoverage($doctorModel)')
        ->toContain('throw ValidationException::withMessages([');

    expect(TelemedicineMedicationCoverage::providerInventoryError(2, 'Cubierto por ATENMEDI'))
        ->toBe('En la fila 2 no puede usar el inventario TDC: como médico de un proveedor registre el medicamento en «Cubierto por ATENMEDI» o en «No cubierto».');
});
