<?php

declare(strict_types=1);

use App\Filament\Administration\Pages\DashboardGestionCobranza;
use App\Filament\Administration\Widgets\Collections\CollectionManagementKpis;
use App\Models\Sale;
use App\Support\Collections\CollectionManagementMetrics;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Escribe ventas de prueba en 2031 (un año sin datos reales): todo dentro de una transacción
 * que siempre se revierte.
 */
beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

function managementSale(string $type, float $amount, string $createdAt, string $affiliationCode): void
{
    $sale = Sale::query()->forceCreate([
        'code_agency' => 'TDG-100',
        'owner_code' => 'TDG-100',
        'date_activation' => '01/01/2031',
        'invoice_number' => 'PEST-KPI-'.uniqid(),
        'persons' => '1',
        'type' => $type,
        'affiliation_code' => $affiliationCode,
        'total_amount' => $amount,
    ]);
    $sale->forceFill(['created_at' => $createdAt])->saveQuietly();
}

it('lo cobrado sale de los pagos registrados: mes, año, mes a mes e individual frente a corporativo', function (): void {
    managementSale('AFILIACION INDIVIDUAL', 100, '2031-03-02 10:00:00', 'IND-A');
    managementSale('AFILIACION INDIVIDUAL', 50, '2031-03-31 23:59:00', 'IND-A');
    managementSale('AFILIACIÓN CORPORATIVA', 300, '2031-03-15 09:00:00', 'COR-B');
    managementSale('NUEVOS NEGOCIOS', 20, '2031-01-10 09:00:00', 'NN-C');
    managementSale('AFILIACION INDIVIDUAL', 999, '2030-12-31 23:00:00', 'IND-OLD');

    $collected = CollectionManagementMetrics::collected(CarbonImmutable::parse('2031-03-15'));

    expect($collected['month'])->toBe([
        'individual' => ['affiliations' => 1, 'count' => 2, 'amount' => 150.0],
        'corporate' => ['affiliations' => 1, 'count' => 1, 'amount' => 300.0],
    ])
        ->and($collected['year']['corporate'])->toBe(['affiliations' => 2, 'count' => 2, 'amount' => 320.0])
        ->and($collected['year']['individual']['amount'])->toBe(150.0)
        ->and(array_column($collected['months'], 'amount'))->toBe([20.0, 0.0, 450.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0])
        ->and($collected['months'][2]['count'])->toBe(3)
        ->and($collected['months'][0]['label'])->toStartWith('Ene');
});

function managementInstallment(string $code, string $due, float $amount, string $status, string $type = 'AFILIACION INDIVIDUAL'): void
{
    App\Models\Collection::query()->forceCreate([
        'include_date' => '01/01/2031', 'owner_code' => 'TDG-100', 'code_agency' => 'TDG-100',
        'collection_invoice_number' => 'PEST-MOR-'.uniqid(), 'quote_number' => 'PEST-Q', 'persons' => '1',
        'type' => $type, 'affiliation_code' => $code, 'affiliate_status' => 'ACTIVA', 'payment_frequency' => 'MENSUAL',
        'next_payment_date' => $due, 'filter_next_payment_date' => $due, 'total_amount' => $amount, 'status' => $status,
    ]);
}

it('la morosidad del mes es lo que vencía en el mes y sigue sin pagar; en el mes en curso, solo hasta ayer', function (): void {
    $today = CarbonImmutable::parse('2031-03-10');
    managementInstallment('PEST-MOR-A', '2031-02-05', 100, 'PAGADO');
    managementInstallment('PEST-MOR-A', '2031-02-20', 100, 'POR PAGAR');
    managementInstallment('PEST-MOR-B', '2031-02-25', 200, 'POR PAGAR', 'AFILIACIÓN CORPORATIVA');
    managementInstallment('PEST-MOR-C', '2031-02-26', 999, 'ANULADO');
    managementInstallment('PEST-MOR-A', '2031-03-03', 50, 'POR PAGAR');
    managementInstallment('PEST-MOR-A', '2031-03-05', 150, 'PAGADO');
    managementInstallment('PEST-MOR-A', '2031-03-10', 400, 'POR PAGAR');

    $february = CollectionManagementMetrics::monthlyDelinquency(CarbonImmutable::parse('2031-02-01'), $today);
    $march = CollectionManagementMetrics::monthlyDelinquency(CarbonImmutable::parse('2031-03-01'), $today);
    $april = CollectionManagementMetrics::monthlyDelinquency(CarbonImmutable::parse('2031-04-01'), $today);

    expect($february)->toMatchArray(['from' => '2031-02-01', 'to' => '2031-02-28', 'due_amount' => 400.0, 'unpaid_amount' => 300.0, 'rate' => 75.0, 'due_count' => 3, 'unpaid_count' => 2])
        ->and($february['by_type']['individual'])->toBe(['rate' => 50.0, 'due_amount' => 200.0, 'unpaid_amount' => 100.0])
        ->and($february['by_type']['corporate'])->toBe(['rate' => 100.0, 'due_amount' => 200.0, 'unpaid_amount' => 200.0])
        ->and($march)->toMatchArray(['to' => '2031-03-09', 'due_amount' => 200.0, 'unpaid_amount' => 50.0, 'rate' => 25.0])
        ->and($april['to'])->toBeNull()
        ->and($april['rate'])->toBe(0.0);

    $tile = CollectionManagementKpis::rateTile(CarbonImmutable::parse('2031-03-01'), CarbonImmutable::parse('2031-02-01'), $today);

    expect($tile['delta'])->toBe(-50.0)
        ->and($tile['current']['label'])->toBe('Marzo 2031')
        ->and($tile['compare']['label'])->toBe('Febrero 2031')
        ->and(array_column($tile['by_type'], 'compare_rate'))->toBe([50.0, 100.0])
        ->and(CollectionManagementMetrics::delinquencyRate(3767.25, 454440.91))->toBe(0.83)
        ->and(CollectionManagementMetrics::delinquencyRate(10, 0))->toBe(0.0);
});

it('el analista elige el mes y el de comparación; por defecto, el mes en curso contra el anterior', function (): void {
    Filament::setCurrentPanel('administration');
    test()->actingAs(App\Models\User::factory()->make(['id' => 999_999_105, 'name' => 'QA', 'email' => 'qa.mora@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'ADMINISTRACION']]));

    $today = CarbonImmutable::today();
    $component = Livewire\Livewire::test(CollectionManagementKpis::class)
        ->assertSet('rateMonth', $today->format('Y-m'))
        ->assertSet('compareMonth', $today->subMonthNoOverflow()->format('Y-m'))
        ->assertSee('Índice de morosidad')
        ->set('compareMonth', $today->subMonthsNoOverflow(3)->format('Y-m'))
        ->assertSee('vs '.ucfirst($today->subMonthsNoOverflow(3)->locale('es')->translatedFormat('F Y')))
        ->set('rateMonth', 'cualquier-cosa');

    $component->assertSee(ucfirst($today->locale('es')->translatedFormat('F Y')));
    expect(array_keys(CollectionManagementMetrics::delinquencyMonthOptions($today)))->toHaveCount(18);
});

it('las tarjetas de cartera abren Gestión de Cobranza con el filtro de vencidas o de próximo pago', function (): void {
    Filament::setCurrentPanel('administration');

    $tiles = CollectionManagementKpis::withTableLinks([
        ['key' => 'overdue'],
        ['key' => 'due-soon'],
        ['key' => 'due-45'],
    ], CarbonImmutable::parse('2026-11-05'));

    expect(urldecode((string) $tiles[0]['url']))->toContain('filters[status][value]=VENCIDO')
        ->and(urldecode((string) $tiles[1]['url']))->toContain('filters[filter_next_payment_date][desde]=2026-11-05')
        ->and(urldecode((string) $tiles[1]['url']))->toContain('filters[filter_next_payment_date][hasta]=2026-11-12')
        ->and(urldecode((string) $tiles[2]['url']))->toContain('[hasta]=2026-12-20')
        ->and(urldecode((string) $tiles[2]['url']))->toContain('filters[status][value]=POR PAGAR');
});

it('el dashboard usa el permiso de Gestión de Cobranza y va fuera del menú', function (): void {
    $root = dirname(__DIR__, 2);
    $page = (string) file_get_contents($root.'/app/Filament/Administration/Pages/DashboardGestionCobranza.php');

    expect($page)
        ->toContain("protected static string \$routePath = 'dashboard-gestion-cobranza';")
        ->toContain('CollectionManagementKpis::class')
        ->toContain("'period' => null,")
        ->and((string) file_get_contents($root.'/app/Support/Filament/DepartmentNavigationPermissionRegistry.php'))
        ->toContain("\\App\\Filament\\Administration\\Pages\\DashboardGestionCobranza::class => ['gestion-de-cobranza'],")
        ->and(DashboardGestionCobranza::shouldRegisterNavigation())->toBeFalse();
});
