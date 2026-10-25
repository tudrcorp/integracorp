<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicineCases\Pages\ListTelemedicineCases;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\Operations\CoordinationServiceCaseDeletion;
use App\Support\Telemedicine\Scopes\HideDeletedTelemedicineCasesScope;
use App\Support\Telemedicine\TelemedicineCaseDeletion;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Escribe en base de datos: todo ocurre dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Filament::setCurrentPanel('operations');
});

afterEach(fn () => DB::rollBack());

function actuarComoGestorDeCasos(array $departments): void
{
    test()->actingAs(User::factory()->make([
        'id' => 999_999_041,
        'name' => 'QA GESTION DE CASOS',
        'email' => 'qa.gestion.casos@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => $departments,
        'supplier_id' => null,
    ]));
}

function casoDeGestion(): TelemedicineCase
{
    $patient = TelemedicinePatient::query()->create([
        'full_name' => 'PACIENTE QA GESTION',
        'managed_by' => 'TDG',
    ]);

    return TelemedicineCase::query()->create([
        'telemedicine_patient_id' => $patient->id,
        'code' => 'QAGC-'.uniqid(),
        'status' => 'EN SEGUIMIENTO',
        'managed_by' => 'TDG',
    ]);
}

function estatusReal(TelemedicineCase $case): ?string
{
    return TelemedicineCase::query()
        ->withoutGlobalScope(HideDeletedTelemedicineCasesScope::class)
        ->whereKey($case->id)
        ->value('status');
}

it('elimina un caso desde su fila y lo restaura desde la vista de eliminados', function (): void {
    actuarComoGestorDeCasos(['SUPERADMIN', 'OPERACIONES']);
    $case = casoDeGestion();

    Livewire::test(ListTelemedicineCases::class)
        ->searchTable($case->code)
        ->assertCanSeeTableRecords([$case])
        ->assertActionVisible(TestAction::make('delete_telemedicine_case_record')->table($case))
        ->assertActionHidden(TestAction::make('restore_telemedicine_case_record')->table($case))
        ->mountAction(TestAction::make('delete_telemedicine_case_record')->table($case))
        ->set('mountedActions.0.data.deletion_reason', 'Caso duplicado creado por error en la prueba')
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertCanNotSeeTableRecords([$case]);

    expect(estatusReal($case))->toBe(TelemedicineCaseDeletion::STATUS);

    $deleted = TelemedicineCase::query()->withoutGlobalScope(HideDeletedTelemedicineCasesScope::class)->find($case->id);

    Livewire::test(ListTelemedicineCases::class)
        ->filterTable(CoordinationServiceCaseDeletion::DELETED_TAB, true)
        ->searchTable($case->code)
        ->assertCanSeeTableRecords([$deleted])
        ->assertActionHidden(TestAction::make('delete_telemedicine_case_record')->table($deleted))
        ->mountAction(TestAction::make('restore_telemedicine_case_record')->table($deleted))
        ->set('mountedActions.0.data.restoration_reason', 'Restaurado: la eliminación fue un error')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(estatusReal($case))->toBe('EN SEGUIMIENTO');
});

it('exige el motivo y no toca el caso sin él', function (): void {
    actuarComoGestorDeCasos(['SUPERADMIN']);
    $case = casoDeGestion();

    Livewire::test(ListTelemedicineCases::class)
        ->searchTable($case->code)
        ->mountAction(TestAction::make('delete_telemedicine_case_record')->table($case))
        ->set('mountedActions.0.data.deletion_reason', 'corto')
        ->callMountedAction()
        ->assertHasFormErrors(['deletion_reason']);

    expect(estatusReal($case))->toBe('EN SEGUIMIENTO');
});

it('elimina varios casos a la vez con la acción masiva', function (): void {
    actuarComoGestorDeCasos(['SUPERADMIN']);
    $first = casoDeGestion();
    $second = casoDeGestion();

    Livewire::test(ListTelemedicineCases::class)
        ->searchTable('QAGC-')
        ->selectTableRecords([$first->getKey(), $second->getKey()])
        ->mountAction(TestAction::make('delete_telemedicine_case')->table()->bulk())
        ->set('mountedActions.0.data.deletion_reason', 'Casos duplicados en la carga de prueba')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(estatusReal($first))->toBe(TelemedicineCaseDeletion::STATUS)
        ->and(estatusReal($second))->toBe(TelemedicineCaseDeletion::STATUS);
});

it('sin el permiso no ofrece eliminar, restaurar ni la vista de eliminados', function (): void {
    actuarComoGestorDeCasos(['OPERACIONES']);
    $case = casoDeGestion();

    expect(CoordinationServiceCaseDeletion::userCanDeleteCases())->toBeFalse()
        ->and(CoordinationServiceCaseDeletion::makeDeleteRecordAction()->record($case)->isVisible())->toBeFalse()
        ->and(CoordinationServiceCaseDeletion::makeRestoreRecordAction()->record($case)->isVisible())->toBeFalse()
        ->and(CoordinationServiceCaseDeletion::makeDeleteBulkAction()->isVisible())->toBeFalse()
        ->and(CoordinationServiceCaseDeletion::makeDeletedCasesFilter()->isVisible())->toBeFalse();
});
