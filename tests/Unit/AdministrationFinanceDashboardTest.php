<?php

declare(strict_types=1);

use App\Filament\Administration\Pages\Dashboard;
use App\Filament\Administration\Widgets\Dashboard\AdministrationCashFlowChart;
use App\Filament\Administration\Widgets\Dashboard\AdministrationDashboardDetail;
use App\Filament\Administration\Widgets\Dashboard\AdministrationKpiTiles;
use App\Filament\Administration\Widgets\Dashboard\AdministrationOutflowsChart;
use App\Filament\Administration\Widgets\Dashboard\AdministrationPayablesRanking;
use App\Filament\Administration\Widgets\Dashboard\AdministrationPeopleByDepartmentRanking;
use App\Filament\Administration\Widgets\Dashboard\AdministrationPeopleKpiTiles;
use App\Filament\Administration\Widgets\Dashboard\AdministrationRenewalsCard;
use App\Filament\Administration\Widgets\Dashboard\AdministrationSectionHeading;
use App\Filament\Administration\Widgets\Dashboard\AdministrationSectionNav;
use App\Filament\Administration\Widgets\Dashboard\AdministrationSeniorityChart;
use App\Filament\Administration\Widgets\Dashboard\AdministrationTdevRanking;
use App\Filament\Administration\Widgets\Dashboard\AdministrationUpcomingCollectionsChart;
use App\Filament\Administration\Widgets\Dashboard\AdministrationUpcomingDates;
use App\Filament\Widgets\WelcomeUserLiquidGlassWidget;
use App\Models\User;
use App\Support\Administration\Dashboard\AdministrationFinanceMetrics;
use App\Support\Administration\Dashboard\AdministrationPeopleMetrics;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Metrics\Corporate\CorporateMetrics;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Escribe en base de datos: todo ocurre dentro de una transacción que siempre se
 * revierte. Las fechas (2031) no chocan con datos reales.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    CorporateMetrics::flush();
    AdministrationPeopleMetrics::forgetPeople();
    Filament::setCurrentPanel('administration');

    test()->actingAs(User::factory()->make([
        'id' => 999_999_081,
        'name' => 'QA ADMINISTRACION',
        'email' => 'qa.administracion@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'ADMINISTRACION'],
    ]));
});

afterEach(function (): void {
    DB::rollBack();
    CorporateMetrics::flush();
    AdministrationPeopleMetrics::forgetPeople();
});

/**
 * @return array<string, mixed>
 */
function filtrosAdministracionMarzo2031(): array
{
    return ['period' => Period::CUSTOM, 'from' => '2031-03-01', 'to' => '2031-03-31', 'compare' => true];
}

function periodoAdministracionMarzo2031(): Period
{
    return Period::fromFilters(filtrosAdministracionMarzo2031(), CarbonImmutable::parse('2031-04-15'));
}

function sembrarAdministracionMarzo2031(): void
{
    DB::table('sales')->insert([
        'code_agency' => 'QA-ADM', 'owner_code' => 'QA-ADM', 'date_activation' => '2031-03-03', 'invoice_number' => 'QA-ADM-1',
        'persons' => 1, 'affiliation_code' => 'QA-ADM-IND', 'affiliate_full_name' => 'TITULAR ADM', 'type' => 'AFILIACION INDIVIDUAL',
        'total_amount' => 1000, 'created_at' => '2031-03-03 10:00:00', 'updated_at' => '2031-03-03 10:00:00',
    ]);

    DB::table('operation_accounts_payables')->insert([
        'supplier_name' => 'PROVEEDOR QA', 'supplier_rif' => 'J-QA', 'invoice_number' => 'QA-FAC-1', 'invoice_date' => '2031-03-09',
        'invoice_amount' => 150, 'invoice_currency' => 'USD',
        'payment_status' => AdministrationFinanceMetrics::PAYABLE_PENDING, 'invoice_registration_date' => '2031-03-10',
        'created_by' => 'QA', 'created_at' => '2031-03-10 10:00:00', 'updated_at' => '2031-03-10 10:00:00',
    ]);
}

it('los egresos suman comisiones, costo médico y facturas de proveedores, y el resultado los descuenta', function (): void {
    sembrarAdministracionMarzo2031();
    $period = periodoAdministracionMarzo2031();

    $kpis = AdministrationFinanceMetrics::kpis($period)['current'];

    expect($kpis['income'])->toBe(1000.0)
        ->and($kpis['supplier_invoices'])->toBe(150.0)
        ->and($kpis['outflows'])->toBe(150.0)
        ->and($kpis['net'])->toBe(850.0)
        ->and(AdministrationFinanceMetrics::outflowComposition($period))->toBe(['Facturas de proveedores' => 150.0])
        ->and(array_sum(AdministrationFinanceMetrics::cashFlow($period)['outflows']))->toBe(150.0)
        ->and(AdministrationFinanceMetrics::payables()['ranking']['rows'][0]['label'] ?? null)->toBeString();
});

it('interpreta las fechas de RRHH guardadas como texto y calcula plantilla, costo y antigüedad', function (): void {
    $before = AdministrationPeopleMetrics::people()->count();

    DB::table('rrhh_colaboradors')->insert([
        'fullName' => 'COLABORADORA QA', 'status' => 'activo', 'sexo' => 'Femenino', 'sueldo' => 500,
        'fechaIngreso' => '10/03/2031', 'fechaNacimiento' => '15/04/1990', 'created_at' => now(), 'updated_at' => now(),
    ]);
    AdministrationPeopleMetrics::forgetPeople();
    CorporateMetrics::flush();

    $people = AdministrationPeopleMetrics::people();
    $qa = $people->firstWhere('name', 'COLABORADORA QA');
    $summary = AdministrationPeopleMetrics::summary(periodoAdministracionMarzo2031());

    expect($people->count())->toBe($before + 1)
        ->and($qa['hired']?->toDateString())->toBe('2031-03-10')
        ->and($qa['birth']?->toDateString())->toBe('1990-04-15')
        ->and($qa['department'])->toBe('Sin departamento')
        ->and($summary['hires'])->toBe(1)
        ->and($summary['payroll'])->toBeGreaterThanOrEqual(500.0);
});

it('los montos de RRHH solo se ven con el permiso propio', function (): void {
    expect(BusinessFilamentActionPermissionRegistry::all())->toHaveKey(BusinessFilamentActionPermissionRegistry::VIEW_HR_AMOUNTS)
        ->and(BusinessFilamentActionPermissionRegistry::modulesForSlug(BusinessFilamentActionPermissionRegistry::VIEW_HR_AMOUNTS))->toBe(['ADMINISTRACION']);

    Livewire::test(AdministrationPeopleKpiTiles::class, ['pageFilters' => filtrosAdministracionMarzo2031()])
        ->assertSee('Costo mensual')
        ->assertSee('Sueldo promedio');

    test()->actingAs(User::factory()->make([
        'id' => 999_999_082,
        'email' => 'qa.analista@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['ADMINISTRACION'],
    ]));

    expect(AdministrationPeopleMetrics::canSeeAmounts())->toBeFalse();

    Livewire::test(AdministrationPeopleKpiTiles::class, ['pageFilters' => filtrosAdministracionMarzo2031()])
        ->assertSee('Plantilla activa')
        ->assertDontSee('Costo mensual')
        ->assertDontSee('Sueldo promedio');

    Livewire::test(AdministrationPeopleByDepartmentRanking::class, ['pageFilters' => filtrosAdministracionMarzo2031()])
        ->assertDontSee('/mes');
});

it('el Escritorio conserva la bienvenida arriba y arma sus secciones con la barra de accesos', function (): void {
    $widgets = (new Dashboard)->getWidgets();
    $anchors = collect($widgets)
        ->filter(fn ($widget): bool => $widget instanceof WidgetConfiguration)
        ->map(fn (WidgetConfiguration $widget): string => $widget->getProperties()['anchor'])
        ->values()
        ->all();

    expect($widgets[0])->toBe(WelcomeUserLiquidGlassWidget::class)
        ->and($widgets[1])->toBe(AdministrationSectionNav::class)
        ->and($anchors)->toBe(array_column(Dashboard::SECTIONS, 'anchor'))
        ->and(file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/AdministrationPanelProvider.php'))->toContain('Administración · Finanzas de la empresa');

    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('Administración')
        ->assertSee('corp-header-filters', false)
        ->assertSee('Modo presentación');

    Livewire::test(AdministrationSectionNav::class)->assertSee('Flujo de caja')->assertSee('RRHH');
    Livewire::test(AdministrationSectionHeading::class, ['anchor' => 'admin-rrhh', 'title' => 'RRHH', 'description' => 'Plantilla', 'icon' => 'heroicon-o-identification'])
        ->assertSee('id="admin-rrhh"', false);
});

it('la barra de secciones flota debajo de la barra superior con aire y no tapa el título al saltar', function (): void {
    $html = Livewire::test(AdministrationSectionNav::class)->html();

    expect($html)
        ->toContain('sticky top-20')
        ->not->toContain('top-16')
        ->toContain("document.querySelector('.fi-topbar-ctn')")
        ->toContain('gap: 12')
        ->toContain('new ResizeObserver')
        ->toContain('this.stickyTop + this.$root.offsetHeight + 16')
        ->toContain('this.resizer?.disconnect()')
        ->not->toContain('{{--');
});

it('cada widget del Escritorio se renderiza con datos', function (): void {
    sembrarAdministracionMarzo2031();
    $filters = filtrosAdministracionMarzo2031();

    Livewire::test(AdministrationKpiTiles::class, ['pageFilters' => $filters])
        ->assertSee('Egresos')
        ->assertSee('Cobranza esperada')
        ->assertSee('US$ 850');
    Livewire::test(AdministrationOutflowsChart::class, ['pageFilters' => $filters])->assertSee('Facturas de proveedores');
    Livewire::test(AdministrationPayablesRanking::class, ['pageFilters' => $filters])->assertSee('Facturas de proveedores por pagar');
    Livewire::test(AdministrationRenewalsCard::class, ['pageFilters' => $filters])->assertSee('Tasa de renovación');
    Livewire::test(AdministrationUpcomingDates::class, ['pageFilters' => $filters])->assertSee('Próximas fechas');
    Livewire::test(AdministrationTdevRanking::class, ['pageFilters' => $filters])->assertSee('No hay vouchers TDEV en este período.');

    foreach ([AdministrationCashFlowChart::class, AdministrationUpcomingCollectionsChart::class, AdministrationSeniorityChart::class] as $chart) {
        Livewire::test($chart, ['pageFilters' => $filters])->assertSuccessful();
    }

    Livewire::test(AdministrationDashboardDetail::class, ['pageFilters' => $filters])
        ->call('openDetail', 'sale_type', 'Afiliación individual')
        ->assertSee('TITULAR ADM');
});

it('en pantalla grande cada fila del Escritorio llena las 12 columnas', function (): void {
    $used = 0;

    foreach ((new Dashboard)->getWidgets() as $widget) {
        $class = $widget instanceof WidgetConfiguration ? $widget->widget : $widget;

        if ($class === AdministrationDashboardDetail::class) {
            continue;
        }

        $span = (fn (): int|string|array => $this->columnSpan)->call(new $class);
        $xl = is_array($span) ? ($span['xl'] ?? $span['default'] ?? 12) : $span;
        $xl = $xl === 'full' ? 12 : (int) $xl;

        if ($used + $xl > 12) {
            expect($used)->toBe(12, 'fila incompleta antes de '.class_basename($class));
            $used = 0;
        }

        $used = ($used + $xl) % 12;
    }

    expect($used)->toBe(0);
});
