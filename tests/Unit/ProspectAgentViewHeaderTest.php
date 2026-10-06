<?php

declare(strict_types=1);

use App\Filament\Business\Resources\ProspectAgents\Pages\ViewProspectAgent;
use App\Models\ProspectAgent;
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

it('la ficha del prospecto usa el encabezado del sistema con la etapa legible y el nombre escapado', function (): void {
    $prospect = ProspectAgent::query()->whereNotNull('status')->latest('id')->first();

    if ($prospect === null) {
        $this->markTestSkipped('No hay prospectos en esta base.');
    }

    Filament::setCurrentPanel('business');
    $this->actingAs(User::query()->where('email', 'like', '%@tudrencasa.com')->where('is_superAdmin', 1)->firstOrFail());

    $page = Livewire::test(ViewProspectAgent::class, ['record' => $prospect->getKey()])
        ->assertSuccessful()
        ->assertSee('Capacitación · Prospecto')
        ->assertSee(mb_strtoupper(App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels::statusLabel($prospect->status)));

    expect($page->instance()->getTitle())->toBe('Prospecto '.trim((string) $prospect->name));

    // El nombre viaja por RecordPageHeader, que lo escapa: antes se concatenaba crudo en el HTML.
    $page->instance()->getRecord()->name = '<script>alert(1)</script>';

    expect((string) $page->instance()->getHeading())
        ->not->toContain('<script>')
        ->toContain('&lt;script&gt;');
});
