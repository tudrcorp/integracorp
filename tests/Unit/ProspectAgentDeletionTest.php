<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

use App\Filament\Business\Resources\ProspectAgents\Pages\EditProspectAgent;
use App\Filament\Business\Resources\ProspectAgents\Pages\ListProspectAgents;
use App\Filament\Business\Resources\ProspectAgents\Pages\ViewProspectAgent;
use App\Filament\Business\Resources\ProspectAgents\ProspectAgentResource;
use App\Http\Controllers\ProspectAgentExportCsvController;
use App\Models\City;
use App\Models\ProspectAgent;
use App\Models\ProspectAgentObservation;
use App\Models\State;
use App\Models\User;
use App\Support\ProspectAgents\ProspectAgentDeletion;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Todo lo que escribe va dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    Filament::setCurrentPanel('business');

    $this->analyst = User::query()
        ->where('email', 'like', '%@tudrencasa.com')
        ->where('status', 'ACTIVO')
        ->get()
        ->first(fn (User $user): bool => in_array('SUPERADMIN', (array) ($user->departament ?? []), true));

    if ($this->analyst === null) {
        $this->markTestSkipped('No hay un analista SUPERADMIN activo en la base.');
    }

    $this->actingAs($this->analyst);

    $this->prospect = ProspectAgent::query()->create([
        'name' => 'Prospecto Pest Eliminación',
        'type' => 'agencia-viajes',
        'status' => 'contacto-inicial',
        'reference_by' => ProspectAgent::query()->whereNotNull('reference_by')->value('reference_by') ?? 'otro',
        'phone_1' => '04120000000',
        'email' => 'pest-eliminacion@ejemplo.com',
        'country_id' => 189,
        'created_by' => 'PEST',
    ]);
});

afterEach(fn () => DB::rollBack());

it('exporta el CSV con las 15 columnas en el orden acordado', function (): void {
    expect(ProspectAgentExportCsvController::HEADERS)->toBe([
        'Estado',
        'Ciudad',
        'Nombre del Prospecto',
        'Tipo',
        'Clasificación',
        'Estatus',
        'Teléfono principal',
        'Teléfono alternativo',
        'Correo',
        'Instagram',
        'Fecha Creado',
        'Fecha Actualizado',
        'Creado por',
        'Actualizado por',
        'Observaciones iniciales',
    ]);
});

it('arma cada fila del CSV en el mismo orden que el encabezado', function (): void {
    $record = new ProspectAgent([
        'name' => 'Viajes Pest',
        'type' => 'agencia-viajes',
        'classification' => 'VIP',
        'status' => 'contacto-inicial',
        'phone_1' => '04121111111',
        'phone_2' => '02122222222',
        'email' => 'viajes@pest.com',
        'instagram' => '@viajespest',
        'created_by' => 'Ana',
        'updated_by' => 'Luis',
        'initial_observ' => 'Llamar el lunes',
    ]);
    $record->created_at = now()->setDate(2026, 10, 5)->setTime(9, 30);
    $record->updated_at = now()->setDate(2026, 11, 2)->setTime(14, 5);
    $record->setRelation('state', new State(['definition' => 'CARABOBO']));
    $record->setRelation('city', new City(['definition' => 'VALENCIA']));

    $row = (new ProspectAgentExportCsvController)->buildRow($record);

    expect($row)->toHaveCount(count(ProspectAgentExportCsvController::HEADERS))
        ->and(array_combine(ProspectAgentExportCsvController::HEADERS, $row))->toBe([
            'Estado' => 'CARABOBO',
            'Ciudad' => 'VALENCIA',
            'Nombre del Prospecto' => 'Viajes Pest',
            'Tipo' => 'Agencia de Viajes',
            'Clasificación' => 'VIP',
            'Estatus' => 'Contacto inicial',
            'Teléfono principal' => '04121111111',
            'Teléfono alternativo' => '02122222222',
            'Correo' => 'viajes@pest.com',
            'Instagram' => '@viajespest',
            'Fecha Creado' => '05/10/2026 09:30',
            'Fecha Actualizado' => '02/11/2026 14:05',
            'Creado por' => 'Ana',
            'Actualizado por' => 'Luis',
            'Observaciones iniciales' => 'Llamar el lunes',
        ]);
});

it('rechaza un motivo vacío o demasiado corto', function (string $reason): void {
    expect(fn (): int => ProspectAgentDeletion::delete([$this->prospect], $reason, 'Pest'))
        ->toThrow(InvalidArgumentException::class);

    expect($this->prospect->fresh()->trashed())->toBeFalse();
})->with([
    'vacío' => [''],
    'espacios' => ['          '],
    'corto' => ['duplicado'],
]);

it('elimina de forma lógica, guarda quién y por qué y lo anota en la bitácora', function (): void {
    $deleted = ProspectAgentDeletion::delete([$this->prospect], '  Registro   duplicado del prospecto  ', 'Analista Pest');

    $fresh = ProspectAgent::withTrashed()->find($this->prospect->getKey());

    expect($deleted)->toBe(1)
        ->and($fresh)->not->toBeNull()
        ->and($fresh->trashed())->toBeTrue()
        ->and($fresh->deleted_by)->toBe('Analista Pest')
        ->and($fresh->deletion_reason)->toBe('Registro duplicado del prospecto')
        ->and(ProspectAgent::query()->whereKey($this->prospect->getKey())->exists())->toBeFalse()
        ->and(ProspectAgentObservation::query()
            ->where('prospect_agent_id', $this->prospect->getKey())
            ->where('observation', 'like', 'Prospecto eliminado. Motivo: Registro duplicado%')
            ->exists())->toBeTrue();
});

it('no vuelve a eliminar un prospecto ya eliminado', function (): void {
    ProspectAgentDeletion::delete([$this->prospect], 'Primer motivo de eliminación', 'Pest');

    $again = ProspectAgentDeletion::delete([ProspectAgent::withTrashed()->find($this->prospect->getKey())], 'Segundo motivo distinto', 'Otro');

    expect($again)->toBe(0)
        ->and(ProspectAgent::withTrashed()->find($this->prospect->getKey())->deletion_reason)->toBe('Primer motivo de eliminación');
});

it('restaura el prospecto, limpia la marca y deja el motivo anterior en la bitácora', function (): void {
    ProspectAgentDeletion::delete([$this->prospect], 'Se eliminó por error de carga', 'Ana Pest');

    $restored = ProspectAgentDeletion::restore([ProspectAgent::withTrashed()->find($this->prospect->getKey())], 'Luis Pest');

    $fresh = ProspectAgent::query()->find($this->prospect->getKey());

    expect($restored)->toBe(1)
        ->and($fresh)->not->toBeNull()
        ->and($fresh->deleted_by)->toBeNull()
        ->and($fresh->deletion_reason)->toBeNull()
        ->and(ProspectAgentObservation::query()
            ->where('prospect_agent_id', $this->prospect->getKey())
            ->where('created_by', 'Luis Pest')
            ->where('observation', 'like', '%Había sido eliminado por Ana Pest con el motivo: Se eliminó por error de carga%')
            ->exists())->toBeTrue();
});

it('la acción masiva exige el motivo y no elimina sin él', function (): void {
    Livewire::test(ListProspectAgents::class)
        ->selectTableRecords([$this->prospect->getKey()])
        ->mountAction(TestAction::make('deleteWithReason')->table()->bulk())
        ->set('mountedActions.0.data.deletion_reason', 'corto')
        ->callMountedAction()
        ->assertHasErrors();

    expect($this->prospect->fresh()->trashed())->toBeFalse();
});

it('la acción masiva elimina con motivo y el filtro de eliminados lo muestra', function (): void {
    Livewire::test(ListProspectAgents::class)
        ->selectTableRecords([$this->prospect->getKey()])
        ->mountAction(TestAction::make('deleteWithReason')->table()->bulk())
        ->set('mountedActions.0.data.deletion_reason', 'Prospecto cerró operaciones en 2026')
        ->callMountedAction()
        ->assertHasNoErrors()
        ->assertNotified('Prospecto eliminado');

    $deleted = ProspectAgent::withTrashed()->find($this->prospect->getKey());

    expect($deleted->trashed())->toBeTrue()
        ->and($deleted->deletion_reason)->toBe('Prospecto cerró operaciones en 2026');

    Livewire::test(ListProspectAgents::class)
        ->searchTable('Prospecto Pest Eliminación')
        ->assertCanNotSeeTableRecords([$deleted])
        ->filterTable('trashed', false)
        ->assertCanSeeTableRecords([$deleted])
        ->assertSee('Prospecto cerró operaciones en 2026');
});

it('restaura desde la fila de la tabla', function (): void {
    ProspectAgentDeletion::delete([$this->prospect], 'Eliminado para probar restaurar', 'Pest');
    $deleted = ProspectAgent::withTrashed()->find($this->prospect->getKey());

    Livewire::test(ListProspectAgents::class)
        ->filterTable('trashed', false)
        ->callAction(TestAction::make('restoreProspect')->table($deleted))
        ->assertNotified('Prospecto restaurado');

    expect(ProspectAgent::query()->whereKey($this->prospect->getKey())->exists())->toBeTrue();
});

it('Editar elimina con motivo y vuelve al listado', function (): void {
    Livewire::test(EditProspectAgent::class, ['record' => $this->prospect->getKey()])
        ->mountAction('deleteWithReason')
        ->set('mountedActions.0.data.deletion_reason', 'Datos falsos, no responde a ningún canal')
        ->callMountedAction()
        ->assertHasNoErrors()
        ->assertRedirect(ProspectAgentResource::getUrl('index'));

    expect(ProspectAgent::withTrashed()->find($this->prospect->getKey())->trashed())->toBeTrue();
});

it('no deja editar un prospecto eliminado y redirige a Ver', function (): void {
    ProspectAgentDeletion::delete([$this->prospect], 'Eliminado para probar Editar', 'Pest');

    Livewire::test(EditProspectAgent::class, ['record' => $this->prospect->getKey()])
        ->assertRedirect(ProspectAgentResource::getUrl('view', ['record' => $this->prospect->getKey()]));
});

it('Ver muestra el prospecto eliminado con su motivo y solo permite restaurar', function (): void {
    ProspectAgentDeletion::delete([$this->prospect], 'Duplicado del registro 120', 'Ana Pest');

    Livewire::test(ViewProspectAgent::class, ['record' => $this->prospect->getKey()])
        ->assertSee('ELIMINADO')
        ->assertSee('Duplicado del registro 120')
        ->assertActionVisible('restoreProspect')
        ->assertActionHidden('deleteWithReason')
        ->assertActionHidden('notes')
        ->assertActionHidden('tascks')
        ->assertActionHidden('print_pdf');
});
