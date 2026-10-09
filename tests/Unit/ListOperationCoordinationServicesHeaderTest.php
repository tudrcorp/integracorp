<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ListOperationCoordinationServices;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    Filament::setCurrentPanel('operations');
});

afterEach(fn () => DB::rollBack());

it('usa el encabezado compartido de Operaciones con los conteos de las pestañas', function (): void {
    $page = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Pages/ListOperationCoordinationServices.php'
    );

    expect($page)
        ->toContain("view('filament.operations.partials.list-header'")
        ->toContain('$counts = $this->tabCounts();')
        ->toContain("'total' => \$counts['todas']")
        ->toContain("\$counts['pendiente']")
        ->toContain("\$counts['en_gestion']")
        ->toContain("\$counts['pendiente_resultados']")
        ->toContain("\$counts['novedad_admon']");
});

it('pinta título, descripción y resumen por estado en la página', function (): void {
    test()->actingAs(User::factory()->make([
        'id' => 999_996,
        'email' => 'qa.header@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
        'supplier_id' => null,
    ]));

    Livewire::test(ListOperationCoordinationServices::class)
        ->assertOk()
        ->assertSee('Operaciones · Coordinación de servicios')
        ->assertSee('Cuadro de control de servicios médicos')
        ->assertSee('Filtre por estado con las pestañas');
});
