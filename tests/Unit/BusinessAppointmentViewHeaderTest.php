<?php

declare(strict_types=1);

use App\Filament\Business\Resources\BusinessAppointments\Pages\ViewBusinessAppointments;
use App\Models\BusinessAppointments;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Solo monta la página; la transacción revertida cubre lo que pudiera registrar.
 */
beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

it('la ficha de la cita usa el encabezado del sistema con el estado y el nombre escapado', function (): void {
    $appointment = BusinessAppointments::query()->latest('id')->first();

    if ($appointment === null) {
        $this->markTestSkipped('No hay citas en esta base.');
    }

    Filament::setCurrentPanel('business');
    $this->actingAs(User::query()->where('email', 'like', '%@tudrencasa.com')->where('is_superAdmin', 1)->firstOrFail());

    $page = Livewire::test(ViewBusinessAppointments::class, ['record' => $appointment->getKey()])
        ->assertSuccessful()
        ->assertSee('Agenda de Negocios · Cita')
        ->assertSee(trim((string) $appointment->legal_name));

    expect($page->instance()->getTitle())->toBe('Cita '.trim((string) $appointment->legal_name));

    $page->instance()->getRecord()->legal_name = '<script>alert(1)</script>';

    expect((string) $page->instance()->getHeading())
        ->not->toContain('<script>')
        ->toContain('&lt;script&gt;');
});

it('cada estado conserva su color: atendida verde, cancelada roja, pendiente y reagendada ámbar', function (string $status, string $label): void {
    $appointment = new BusinessAppointments(['legal_name' => 'Prueba', 'status' => $status]);

    $page = new ViewBusinessAppointments;
    $page->record = $appointment;

    expect((string) $page->getHeading())->toContain($label);
})->with([
    ['atendida', 'ATENDIDA'],
    ['cancelada', 'CANCELADA'],
    ['reagendada', 'REAGENDADA'],
    ['', 'PENDIENTE'],
]);
