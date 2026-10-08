<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\AccountsPayables\Pages\ListAccountsPayables;
use App\Filament\Operations\Resources\AccountsPayables\Tables\AccountsPayablesTable;
use App\Models\OperationQuoteGenerator;
use App\Models\User;
use App\Support\Filament\Operations\SpecificBusinessUnitTableTools;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();

    $this->actingAs(User::factory()->create([
        'email' => 'qa.cotizaciones.un@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]));

    Filament::setCurrentPanel('operations');
});

afterEach(fn () => DB::rollBack());

/**
 * Una cotización real con o sin unidad de negocio específica en el paciente de su coordinación.
 */
function quoteWithSpecificUnit(bool $withUnit): ?OperationQuoteGenerator
{
    $hasUnit = fn ($patient) => $patient->whereNotNull('specific_business_unit')->where('specific_business_unit', '!=', '');

    return OperationQuoteGenerator::query()
        ->whereNotNull('operation_coordination_service_id')
        ->when(
            $withUnit,
            fn ($query) => $query->whereHas('operationCoordinationService.telemedicinePatient', $hasUnit),
            fn ($query) => $query->whereDoesntHave('operationCoordinationService.telemedicinePatient', $hasUnit),
        )
        ->with('operationCoordinationService.telemedicinePatient')
        ->first();
}

it('muestra la unidad de negocio específica del paciente de la coordinación', function (): void {
    $conUnidad = quoteWithSpecificUnit(true);
    $sinUnidad = quoteWithSpecificUnit(false);

    if ($conUnidad === null || $sinUnidad === null) {
        $this->markTestSkipped('La base no tiene cotizaciones con y sin unidad específica.');
    }

    $unidad = (string) $conUnidad->operationCoordinationService->telemedicinePatient->specific_business_unit;

    Livewire::test(ListAccountsPayables::class)
        ->assertSuccessful()
        ->assertTableColumnExists('specific_business_unit')
        ->assertTableColumnStateSet('specific_business_unit', $unidad, $conUnidad)
        ->assertTableColumnStateSet('specific_business_unit', '—', $sinUnidad);
});

it('filtra por unidad específica y por «Sin unidad específica»', function (): void {
    $conUnidad = quoteWithSpecificUnit(true);
    $sinUnidad = quoteWithSpecificUnit(false);

    if ($conUnidad === null || $sinUnidad === null) {
        $this->markTestSkipped('La base no tiene cotizaciones con y sin unidad específica.');
    }

    $unidad = (string) $conUnidad->operationCoordinationService->telemedicinePatient->specific_business_unit;
    $ids = [$conUnidad->getKey(), $sinUnidad->getKey()];

    $visibles = fn (array $valores): array => Livewire::test(ListAccountsPayables::class)
        ->filterTable('specific_business_unit', $valores)
        ->instance()
        ->getFilteredTableQuery()
        ->whereKey($ids)
        ->pluck('id')
        ->map(fn (mixed $id): int => (int) $id)
        ->sort()
        ->values()
        ->all();

    expect($visibles([$unidad]))->toBe([$conUnidad->getKey()])
        ->and($visibles([SpecificBusinessUnitTableTools::WITHOUT]))->toBe([$sinUnidad->getKey()])
        ->and($visibles([$unidad, SpecificBusinessUnitTableTools::WITHOUT]))->toBe(collect($ids)->sort()->values()->all());
});

it('las opciones salen de las cotizaciones de la tabla e incluyen «Sin unidad específica»', function (): void {
    $conUnidad = quoteWithSpecificUnit(true);

    if ($conUnidad === null) {
        $this->markTestSkipped('La base no tiene cotizaciones con unidad específica.');
    }

    $unidad = (string) $conUnidad->operationCoordinationService->telemedicinePatient->specific_business_unit;

    expect(AccountsPayablesTable::specificBusinessUnitOptions())
        ->toHaveKey($unidad, $unidad)
        ->toHaveKey(SpecificBusinessUnitTableTools::WITHOUT, SpecificBusinessUnitTableTools::WITHOUT_LABEL);
});

it('las opciones limpian vacíos y repetidos y van ordenadas', function (): void {
    expect(SpecificBusinessUnitTableTools::options([' PQQ ', 'PQQ', '', null, 'ALFA']))->toBe([
        'ALFA' => 'ALFA',
        'PQQ' => 'PQQ',
        SpecificBusinessUnitTableTools::WITHOUT => SpecificBusinessUnitTableTools::WITHOUT_LABEL,
    ]);
});
