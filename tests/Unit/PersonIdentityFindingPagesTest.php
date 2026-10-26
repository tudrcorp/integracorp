<?php

declare(strict_types=1);

use App\Filament\Business\Resources\PersonIdentityFindings\Pages\ListPersonIdentityFindings;
use App\Filament\Business\Resources\PersonIdentityFindings\Pages\ViewPersonIdentityFinding;
use App\Filament\Business\Resources\PersonIdentityFindings\PersonIdentityFindingResource;
use App\Models\PersonIdentityFinding;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Pantallas del detector de personas duplicadas (Negocios). Solo leen; la transacción revertida
 * protege por si algún render escribiera.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Filament::setCurrentPanel('business');
});

afterEach(function (): void {
    DB::rollBack();
});

it('muestra la lista y el detalle de un caso al usuario con permiso', function (): void {
    $finding = PersonIdentityFinding::query()->whereNotNull('record_b_id')->first() ?? PersonIdentityFinding::query()->first();

    if ($finding === null) {
        $this->markTestSkipped('No hay casos detectados en la base para mostrar.');
    }

    $user = User::factory()->make(['id' => 999998, 'name' => 'Analista de prueba', 'departament' => ['SUPERADMIN'], 'email' => 'analista@tudrencasa.com']);
    $this->actingAs($user);

    Livewire::test(ListPersonIdentityFindings::class)
        ->assertSuccessful()
        ->assertSee('Revisar todas ahora')
        ->assertCanSeeTableRecords([$finding]);

    Livewire::test(ViewPersonIdentityFinding::class, ['record' => $finding->getKey()])
        ->assertSuccessful()
        ->assertSee('Personas duplicadas · '.$finding->kindEnum()?->label(), false)
        ->assertSee($finding->statusLabel())
        ->assertSee('Detectado')
        ->assertSee('Qué se detectó')
        ->assertSee((string) $finding->person_name)
        ->assertSee('Corregir datos')
        ->assertSee('No es la misma persona');
});

it('no deja entrar a un analista de Negocios sin el permiso', function (): void {
    $user = User::factory()->make(['id' => 999997, 'departament' => ['NEGOCIOS'], 'email' => 'sinpermiso@tudrencasa.com']);
    $this->actingAs($user);

    expect(PersonIdentityFindingResource::canAccess())->toBeFalse();
});
