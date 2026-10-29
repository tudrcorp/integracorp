<?php

declare(strict_types=1);

use App\Filament\Forms\Components\DateRangePicker;
use App\Filament\Operations\Resources\OperationAccountsPayables\Pages\ListOperationAccountsPayables;
use App\Models\OperationAccountsPayable;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

it('normaliza el rango: ordena, completa un solo día y descarta fechas inválidas', function (mixed $state, ?string $from, ?string $until): void {
    $range = DateRangePicker::normalize($state);

    expect($range['from']?->toDateString())->toBe($from)
        ->and($range['until']?->toDateString())->toBe($until);
})->with([
    'rango normal' => [['from' => '2026-07-01', 'until' => '2026-07-17'], '2026-07-01', '2026-07-17'],
    'rango invertido' => [['from' => '2026-07-17', 'until' => '2026-07-01'], '2026-07-01', '2026-07-17'],
    'solo inicio' => [['from' => '2026-07-01', 'until' => null], '2026-07-01', '2026-07-01'],
    'solo fin' => [['from' => null, 'until' => '2026-07-17'], '2026-07-17', '2026-07-17'],
    'vacío' => [['from' => null, 'until' => null], null, null],
    'sin estado' => [null, null, null],
    'fecha imposible' => [['from' => '2026-02-30', 'until' => '2026-03-01'], '2026-03-01', '2026-03-01'],
    'texto basura' => [['from' => "2026-07-01' OR 1=1", 'until' => ['x']], null, null],
    'formato ajeno' => [['from' => '01/07/2026', 'until' => '2026-07-17'], '2026-07-17', '2026-07-17'],
]);

it('describe el rango para el indicador del filtro', function (): void {
    expect(DateRangePicker::describe(['from' => '2026-07-01', 'until' => '2026-07-17']))->toBe('01/07/2026 – 17/07/2026')
        ->and(DateRangePicker::describe(['from' => '2026-07-01', 'until' => null]))->toBe('01/07/2026')
        ->and(DateRangePicker::describe(['from' => null, 'until' => null]))->toBeNull();
});

it('filtra las facturas por fecha de registro, con los extremos incluidos', function (): void {
    $usuario = User::factory()->create([
        'email' => 'qa.cxp.rango.registro@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]);

    $antes = OperationAccountsPayable::factory()->create(['invoice_registration_date' => '2026-06-30']);
    $inicio = OperationAccountsPayable::factory()->create(['invoice_registration_date' => '2026-07-01']);
    $medio = OperationAccountsPayable::factory()->create(['invoice_registration_date' => '2026-07-10']);
    $fin = OperationAccountsPayable::factory()->create(['invoice_registration_date' => '2026-07-17']);
    $despues = OperationAccountsPayable::factory()->create(['invoice_registration_date' => '2026-07-18']);
    $ids = collect([$antes, $inicio, $medio, $fin, $despues])->map->getKey()->all();

    $this->actingAs($usuario);
    Filament::setCurrentPanel('operations');

    $visibles = fn (array $range): array => Livewire::test(ListOperationAccountsPayables::class)
        ->filterTable('invoice_registration_range', ['range' => $range])
        ->instance()
        ->getFilteredTableQuery()
        ->whereKey($ids)
        ->orderBy('id')
        ->pluck('id')
        ->map(fn (mixed $id): int => (int) $id)
        ->all();

    Livewire::test(ListOperationAccountsPayables::class)
        ->assertSee('Fecha de registro de la factura')
        ->assertSee('Cualquier fecha')
        ->assertSee('Mes anterior')
        ->assertSeeHtml('wire:click="applyTableFilters" x-on:click="close()"');

    expect($visibles(['from' => '2026-07-01', 'until' => '2026-07-17']))->toBe([$inicio->getKey(), $medio->getKey(), $fin->getKey()])
        ->and($visibles(['from' => '2026-07-17', 'until' => '2026-07-01']))->toBe([$inicio->getKey(), $medio->getKey(), $fin->getKey()])
        ->and($visibles(['from' => '2026-07-10', 'until' => null]))->toBe([$medio->getKey()])
        ->and($visibles(['from' => null, 'until' => null]))->toBe($ids)
        ->and($visibles(['from' => 'no-es-fecha', 'until' => null]))->toBe($ids);
});

it('muestra el selector de rango en los filtros y lo arma sin ir al servidor', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationAccountsPayables/Tables/OperationAccountsPayablesTable.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/forms/components/date-range-picker.blade.php');

    expect($source)
        ->toContain("Filter::make('invoice_registration_range')")
        ->toContain("DateRangePicker::make('range')")
        ->toContain("whereBetween('invoice_registration_date'")
        ->toContain("Filter::make('invoice_period')");

    expect($view)
        ->toContain('isOptimisticallyLive: false')
        ->toContain('$getStatePath()')
        ->not->toContain('$statePath')
        ->toContain('wire:ignore')
        ->toContain("preset('previousMonth')")
        ->toContain('Cualquier fecha')
        ->not->toContain('wire:model.live');
});
