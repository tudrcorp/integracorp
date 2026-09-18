<?php

declare(strict_types=1);

use App\Filament\Operations\Pages\DashboardProveedor;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierCasesByServiceTypeChart;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierDashboardStatsOverview;
use App\Filament\Operations\Widgets\SupplierDashboard\SupplierIdentityHeader;
use App\Models\Permission;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\UserFormPermissionOptions;
use App\Support\Operations\SupplierDashboardMetrics;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Auth::forgetUser();
    SupplierDashboardMetrics::flush();
});

afterEach(function (): void {
    Auth::forgetUser();
    SupplierDashboardMetrics::flush();
});

/**
 * @param  list<string>  $permissionSlugs
 */
function supplierAnalystUser(
    bool $gestionIntegracorp = true,
    array $permissionSlugs = ['dashboard-proveedor'],
    ?int $supplierId = 15,
    ?int $doctorId = null,
): User {
    $user = new User([
        'name' => 'Analista ATENMEDI',
        'email' => 'analista@atenmedi.com',
        'status' => 'ACTIVO',
        'departament' => ['OPERACIONES'],
        'supplier_id' => $supplierId,
        'is_proveedor_amd' => $doctorId === null,
        'doctor_id' => $doctorId,
    ]);

    if ($supplierId !== null) {
        $user->setRelation('supplier', new Supplier(['gestion_integracorp' => $gestionIntegracorp]));
    }

    $user->setRelation('permissions', collect($permissionSlugs)->map(
        fn (string $slug): Permission => new Permission(['slug' => $slug, 'module' => 'OPERACIONES', 'name' => $slug])
    ));

    Auth::setUser($user);
    SupplierDashboardMetrics::flush();

    return $user;
}

it('habilita el dashboard solo para analistas de proveedor con gestión Integracorp activa', function (): void {
    expect(SupplierDashboardMetrics::isAvailableFor(null))->toBeFalse();

    $analyst = supplierAnalystUser();
    expect(SupplierDashboardMetrics::isAvailableFor($analyst))->toBeTrue();

    $sinGestion = supplierAnalystUser(gestionIntegracorp: false);
    expect(SupplierDashboardMetrics::isAvailableFor($sinGestion))->toBeFalse();

    $sinProveedor = supplierAnalystUser(supplierId: null);
    expect(SupplierDashboardMetrics::isAvailableFor($sinProveedor))->toBeFalse();
});

it('muestra el Dashboard del Proveedor al analista con el permiso asignado', function (): void {
    supplierAnalystUser();

    expect(DashboardProveedor::canAccess())->toBeTrue()
        ->and(DashboardProveedor::shouldRegisterNavigation())->toBeTrue();
});

it('oculta el Dashboard del Proveedor al analista sin el permiso', function (): void {
    supplierAnalystUser(permissionSlugs: ['servicios-medicos']);

    expect(DashboardProveedor::canAccess())->toBeFalse()
        ->and(DashboardProveedor::shouldRegisterNavigation())->toBeFalse();
});

it('oculta el Dashboard del Proveedor si el proveedor tiene la gestión apagada', function (): void {
    supplierAnalystUser(gestionIntegracorp: false);

    expect(DashboardProveedor::canAccess())->toBeFalse();
});

it('oculta el Dashboard del Proveedor a un analista interno de TDG', function (): void {
    $interno = new User([
        'name' => 'Analista TDG',
        'email' => 'analista@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['OPERACIONES', 'SUPERADMIN'],
    ]);
    $interno->setRelation('permissions', collect([
        new Permission(['slug' => 'dashboard-proveedor', 'module' => 'OPERACIONES', 'name' => 'Dashboard Proveedor']),
    ]));
    Auth::setUser($interno);
    SupplierDashboardMetrics::flush();

    expect(DashboardProveedor::canAccess())->toBeFalse();
});

it('no entrega métricas cuando no hay proveedor en sesión', function (): void {
    expect(SupplierDashboardMetrics::supplier())->toBeNull()
        ->and(SupplierDashboardMetrics::supplierName())->toBeNull()
        ->and(SupplierDashboardMetrics::analystsCount())->toBe(0)
        ->and(SupplierDashboardMetrics::doctorsCount())->toBe(0)
        ->and(SupplierDashboardMetrics::followUpCasesCount())->toBe(0)
        ->and(SupplierDashboardMetrics::medicalDischargeCasesCount())->toBe(0)
        ->and(SupplierDashboardMetrics::casesByServiceType())->toBe([]);
});

it('define las seis barras del gráfico en el orden pedido', function (): void {
    expect(SupplierDashboardMetrics::SERVICE_TYPE_LABELS)->toBe([
        'Telemedicina',
        'AMD',
        'Medicamentos',
        'Laboratorios',
        'Estudios/Imágenes',
        'Especialistas',
    ]);
});

it('arma la página con la cabecera, los stats y el gráfico', function (): void {
    $page = new DashboardProveedor;

    expect($page->getWidgets())->toBe([
        SupplierIdentityHeader::class,
        SupplierDashboardStatsOverview::class,
        SupplierCasesByServiceTypeChart::class,
    ])->and($page->getColumns())->toBe(1);
});

it('mantiene los widgets del proveedor fuera del descubrimiento automático', function (): void {
    foreach ([SupplierIdentityHeader::class, SupplierDashboardStatsOverview::class, SupplierCasesByServiceTypeChart::class] as $widget) {
        $property = (new ReflectionClass($widget))->getProperty('isDiscovered');
        $property->setAccessible(true);

        expect($property->getValue())->toBeFalse();
    }
});

it('registra el permiso del Dashboard del Proveedor en el módulo OPERACIONES', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(DashboardProveedor::class))->toBe(['dashboard-proveedor'])
        ->and(DepartmentNavigationPermissionRegistry::moduleFor(DashboardProveedor::class))->toBe('OPERACIONES')
        ->and(UserFormPermissionOptions::navToLegacySlugAliases())->toHaveKey('dashboardproveedor');
});

it('expone la página en su propia ruta del panel de operaciones', function (): void {
    expect(Route::has('filament.operations.pages.dashboard-proveedor'))->toBeTrue()
        ->and(DashboardProveedor::getNavigationLabel())->toBe('Dashboard Proveedor');
});

it('resuelve todos los iconos declarados en los stats del proveedor', function (): void {
    $widget = new SupplierDashboardStatsOverview;

    $method = (new ReflectionClass($widget))->getMethod('getStats');
    $method->setAccessible(true);

    /** @var list<\Filament\Widgets\StatsOverviewWidget\Stat> $stats */
    $stats = $method->invoke($widget);

    expect($stats)->toHaveCount(4);

    foreach ($stats as $stat) {
        foreach ([$stat->getIcon(), $stat->getDescriptionIcon()] as $icon) {
            expect($icon)->toBeString();

            expect(fn () => svg($icon))->not->toThrow(\BladeUI\Icons\Exceptions\SvgNotFound::class);
        }
    }
});

it('renderiza el gráfico con un contenedor alto y no con los 150px por defecto', function (): void {
    \Filament\Facades\Filament::setCurrentPanel('operations');

    $html = Livewire::test(SupplierCasesByServiceTypeChart::class)->html();

    expect($html)
        ->toContain('tdg-chart-tall')
        ->toContain('.tdg-chart-tall .fi-wi-chart-canvas-ctn')
        ->toContain('height: 26rem')
        ->toContain('Total de casos por tipo de servicio');
});

it('resalta el nombre del proveedor en la cabecera del dashboard', function (): void {
    $html = view('filament.operations.widgets.partials.supplier-dashboard-identity-card', [
        'supplierName' => 'CORPORACION VMC, C.A. (ATENMEDI)',
        'analystName' => 'Analista ATENMEDI I',
        'date' => 'Viernes, 18 de septiembre de 2026',
        'totalCases' => 187,
    ])->render();

    expect($html)
        ->toContain('CORPORACION VMC, C.A. (ATENMEDI)')
        ->toContain('Espacio operativo del proveedor')
        ->toContain('Gestión Integracorp activa')
        ->toContain('187 casos gestionados')
        ->toContain('Acceso auditado')
        ->toContain('Analista ATENMEDI I');
});

it('cuenta un caso en cada tipo de servicio que consumió', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/SupplierDashboardMetrics.php');

    expect($source)
        ->toContain('->distinct()')
        ->toContain("->count('telemedicine_case_id')")
        ->toContain("where('supplier_id', self::supplierId())");
});
