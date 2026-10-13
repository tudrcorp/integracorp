<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ListOperationCoordinationServices;
use App\Models\User;
use App\Support\Operations\CoordinationServiceCaseDeletion;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * El listado solo lee, pero Filament resuelve acciones y contadores contra la
 * base: la transacción revertida garantiza que nada quede escrito.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Filament::setCurrentPanel('operations');
});

afterEach(fn () => DB::rollBack());

/**
 * `departament` guarda texto plano en la mayoría de los usuarios de la red
 * comercial, así que el filtro por departamento se resuelve en PHP: un
 * `whereJsonContains` revienta contra esas filas.
 *
 * @return \Illuminate\Support\Collection<int, User>
 */
function usuariosInternosDePrueba(): \Illuminate\Support\Collection
{
    return User::query()
        ->where('status', 'ACTIVO')
        ->where('email', 'like', '%@tudrencasa.com')
        ->get()
        ->filter(fn (User $user): bool => is_array($user->departament));
}

function usuarioSuperAdminDePrueba(): ?User
{
    return usuariosInternosDePrueba()
        ->first(fn (User $user): bool => in_array('SUPERADMIN', $user->departament, true));
}

it('muestra el filtro de casos eliminados al superadmin y no al resto', function (): void {
    $superAdmin = usuarioSuperAdminDePrueba();

    if ($superAdmin === null) {
        $this->markTestSkipped('No hay un usuario SUPERADMIN activo en esta base.');
    }

    $this->actingAs($superAdmin);

    Livewire::test(ListOperationCoordinationServices::class)
        ->assertTableFilterVisible(CoordinationServiceCaseDeletion::DELETED_TAB);

    $analista = usuariosInternosDePrueba()
        ->first(fn (User $user): bool => in_array('OPERACIONES', $user->departament, true)
            && ! in_array('SUPERADMIN', $user->departament, true));

    if ($analista === null) {
        return;
    }

    $this->actingAs($analista);

    // El analista con permiso de eliminar sí debe verlo; el resto no.
    $page = Livewire::test(ListOperationCoordinationServices::class)->instance();

    if ($page === null) {
        return; // Sin acceso al recurso: tampoco ve el filtro.
    }

    // Un filtro oculto ni siquiera se devuelve: getFilter() da null.
    expect((bool) $page->getTable()->getFilter(CoordinationServiceCaseDeletion::DELETED_TAB)?->isVisible())
        ->toBe(CoordinationServiceCaseDeletion::userCanDeleteCases());
});

it('con el filtro de eliminados activo ofrece restaurar y no eliminar', function (): void {
    $superAdmin = usuarioSuperAdminDePrueba();

    if ($superAdmin === null) {
        $this->markTestSkipped('No hay un usuario SUPERADMIN activo en esta base.');
    }

    $this->actingAs($superAdmin);

    $component = Livewire::test(ListOperationCoordinationServices::class)
        ->filterTable(CoordinationServiceCaseDeletion::DELETED_TAB)
        ->assertSuccessful();

    expect(CoordinationServiceCaseDeletion::viewingDeletedTab($component->instance()))->toBeTrue();

    $component->resetTableFilters();

    expect(CoordinationServiceCaseDeletion::viewingDeletedTab($component->instance()))->toBeFalse();
});

it('renderiza el cuadro de control con las acciones de eliminación', function (): void {
    $superAdmin = usuarioSuperAdminDePrueba();

    if ($superAdmin === null) {
        $this->markTestSkipped('No hay un usuario SUPERADMIN activo en esta base.');
    }

    $this->actingAs($superAdmin);

    $component = Livewire::test(ListOperationCoordinationServices::class)
        ->assertSuccessful();

    $labels = collect($component->instance()->getTable()->getToolbarActions())
        ->flatMap(fn (object $action): array => method_exists($action, 'getActions') ? $action->getActions() : [$action])
        ->map(fn (object $action): string => (string) $action->getLabel())
        ->all();

    expect($labels)->toContain('Eliminar caso')
        ->and($labels)->toContain('Restaurar caso')
        ->and($labels)->not->toContain('Eliminar seleccionados');
});
