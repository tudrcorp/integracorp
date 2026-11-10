<?php

declare(strict_types=1);

use App\Filament\Metrics\Pages\Sistema;
use App\Models\User;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * La página solo lee (git e information_schema). Aun así: transacción que
 * siempre se revierte y caché en memoria, para no dejar rastro en la base ni
 * en la caché reales.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    config(['cache.default' => 'array']);
    app('cache')->setDefaultDriver('array');
    Filament::setCurrentPanel('metrics');
});

afterEach(function (): void {
    DB::rollBack();
});

function usuarioMetricasSistemaQa(array $departments): User
{
    return User::factory()->make([
        'id' => 999_999_081,
        'name' => 'QA SISTEMA',
        'email' => 'qa.sistema@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => $departments,
    ]);
}

it('registra el permiso de menú propio de la sección Sistema', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(Sistema::class))->toBe(['metricas-sistema'])
        ->and(DepartmentNavigationPermissionRegistry::moduleFor(Sistema::class))->toBe('METRICAS');
});

it('SUPERADMIN ve la página; primero el esqueleto y luego el reporte', function (): void {
    test()->actingAs(usuarioMetricasSistemaQa(['SUPERADMIN', 'METRICAS']));

    expect(Sistema::canAccess())->toBeTrue();

    $page = Livewire::test(Sistema::class)
        ->assertSuccessful()
        ->assertSee('Leyendo el historial del código');

    $page->call('loadReport')
        ->assertSuccessful()
        ->assertSee('Evolución mes a mes')
        ->assertSee('Trabajo por módulo')
        ->assertSee('Base de datos')
        ->assertSee('Comparativo detallado')
        ->assertSee('Comparativa con el mes anterior');

    $page->set('window', 3)->assertSet('window', 3)->assertSee('Los 3 meses anteriores');
});

it('corrige un mes o una ventana manipulados desde el navegador', function (): void {
    test()->actingAs(usuarioMetricasSistemaQa(['SUPERADMIN', 'METRICAS']));

    Livewire::test(Sistema::class)
        ->set('month', '2999-01')
        ->assertSet('month', now()->format('Y-m'))
        ->set('window', 99)
        ->assertSet('window', 6);
});

it('quien no es SUPERADMIN ni tiene el permiso no entra', function (): void {
    test()->actingAs(usuarioMetricasSistemaQa(['NEGOCIOS']));

    expect(Sistema::canAccess())->toBeFalse();
});
