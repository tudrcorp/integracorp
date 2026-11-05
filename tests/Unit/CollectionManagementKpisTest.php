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

it('la morosidad es lo vencido entre el total por cobrar, general y por tipo', function (): void {
    expect(CollectionManagementMetrics::delinquencyRate(3767.25, 454440.91))->toBe(0.83)
        ->and(CollectionManagementMetrics::delinquencyRate(50, 200))->toBe(25.0)
        ->and(CollectionManagementMetrics::delinquencyRate(10, 0))->toBe(0.0);

    $tile = CollectionManagementKpis::rateTile([
        'overdue_amount' => 30.0,
        'pending_amount' => 120.0,
        'overdue_by_type' => ['individual' => ['amount' => 30.0], 'corporate' => ['amount' => 0.0]],
        'pending_by_type' => ['individual' => ['amount' => 60.0], 'corporate' => ['amount' => 60.0]],
    ]);

    expect($tile['rate'])->toBe(25.0)
        ->and(array_column($tile['by_type'], 'rate'))->toBe([50.0, 0.0])
        ->and(array_column($tile['by_type'], 'label'))->toBe(['Individuales', 'Corporativas']);
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
