<?php

declare(strict_types=1);

use App\Enums\ProviderNetworkType;
use App\Filament\Operations\Pages\DashboardRedProveedores;
use App\Filament\Operations\Widgets\ProviderNetwork\ProviderNetworkDetail;
use App\Filament\Operations\Widgets\ProviderNetwork\ProviderNetworkKpiTiles;
use App\Filament\Operations\Widgets\ProviderNetwork\ProviderNetworkMap;
use App\Filament\Operations\Widgets\ProviderNetwork\ProviderNetworkProductivity;
use App\Models\CorporateAlly;
use App\Models\DoctorNurse;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod;
use App\Support\Operations\Providers\ProviderActivityLog;
use App\Support\Operations\Providers\ProviderLocation;
use App\Support\Operations\Providers\ProviderNetworkAnalytics;
use App\Support\Operations\Providers\ProviderNetworkCatalog;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Dashboard de proveedores (Operaciones). Escribe: corre dentro de una transacción que siempre se
 * revierte, sobre proveedores de prueba clonados de filas reales.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    ProviderNetworkAnalytics::flush();

    $this->supplierTemplate = Supplier::query()->first();
    $this->naturalTemplate = DoctorNurse::query()->first();
    $this->allyTemplate = CorporateAlly::query()->first();

    if (! $this->supplierTemplate || ! $this->naturalTemplate || ! $this->allyTemplate) {
        $this->markTestSkipped('Faltan proveedores reales para clonar.');
    }

    $this->actingAs(User::factory()->make(['id' => 999992, 'name' => 'Analista Proveedores Prueba', 'departament' => ['SUPERADMIN'], 'email' => 'prov.prueba@tudrencasa.com']));
});

afterEach(function (): void {
    DB::rollBack();
    ProviderNetworkAnalytics::flush();
});

it('lee el estatus del catálogo, la columna vieja de los naturales y lo que está fuera del catálogo', function (): void {
    expect(ProviderNetworkCatalog::status(ProviderNetworkType::Juridico, ['status_sistema' => ' activo afiliado '])['key'])->toBe('ACTIVO AFILIADO')
        ->and(ProviderNetworkCatalog::status(ProviderNetworkType::Natural, ['status_sistema' => '', 'status_sistena' => 'AFILILIADO'])['key'])->toBe('AFILIADO')
        ->and(ProviderNetworkCatalog::status(ProviderNetworkType::Natural, ['status_sistema' => 'PENDIENTE', 'status_sistena' => 'AFILIADO'])['key'])->toBe('PENDIENTE')
        ->and(ProviderNetworkCatalog::status(ProviderNetworkType::Aliado, ['status_agreement' => 'SIN RESPUESTA DE AFILIACIÓN'])['key'])->toBe('SIN RESPUESTA DE AFILIACION')
        ->and(ProviderNetworkCatalog::status(ProviderNetworkType::Natural, ['status_sistena' => "NO USAR \nCOSTOS ELEVADOS"]))->toBe(['key' => 'OTRO', 'raw' => 'NO USAR COSTOS ELEVADOS'])
        ->and(ProviderNetworkCatalog::status(ProviderNetworkType::Juridico, [])['key'])->toBe('SIN ESTATUS')
        ->and(ProviderNetworkCatalog::agreement(ProviderNetworkType::Juridico, ['status_convenio' => 'Preferencial bajo costo']))->toBe('PREFERENCIAL BAJO COSTO')
        ->and(ProviderNetworkCatalog::agreement(ProviderNetworkType::Natural, ['status_convenio' => 'ACTIVO']))->toBe('SIN TIPO')
        ->and(ProviderNetworkCatalog::hasLetter(ProviderNetworkType::Aliado, []))->toBeNull();
});

it('ubica a los naturales aunque el estado venga escrito a mano', function (): void {
    expect(ProviderLocation::fromStateName(' MERIDA '))->toBe('Mérida')
        ->and(ProviderLocation::fromStateName('MARACAIBO'))->toBe('Zulia')
        ->and(ProviderLocation::fromStateName('VARGAS'))->toBe('La Guaira')
        ->and(ProviderLocation::fromStateName(' NUEVA ESPARTA'))->toBe('Nueva Esparta')
        ->and(ProviderLocation::fromStateName('DEPENDENCIA FEDERAL'))->toBeNull()
        ->and(ProviderLocation::countryName(185))->toBe('Estados Unidos');
});

it('la foto suma lo mismo que la base y se renueva al cambiar un proveedor', function (): void {
    $before = ProviderNetworkAnalytics::snapshot();

    expect($before['totals']['juridico'])->toBe(Supplier::query()->count())
        ->and($before['totals']['natural'])->toBe(DoctorNurse::query()->count())
        ->and($before['totals']['aliado'])->toBe(CorporateAlly::query()->count())
        ->and(array_sum(array_map('array_sum', $before['status'])))->toBe($before['total']);

    $clone = $this->supplierTemplate->replicate();
    $clone->forceFill(['name' => 'CLINICA PRUEBA DASHBOARD '.Str::random(5), 'rif' => 'J-TEST'.random_int(1000, 9999), 'status_sistema' => 'EN PROCESO', 'status_convenio' => 'PREFERENCIAL GEOGRAFICO', 'created_by' => null])->save();

    $after = ProviderNetworkAnalytics::snapshot();

    expect($after['totals']['juridico'])->toBe($before['totals']['juridico'] + 1)
        ->and($after['agreements']['PREFERENCIAL GEOGRAFICO']['juridico'] ?? 0)->toBe(($before['agreements']['PREFERENCIAL GEOGRAFICO']['juridico'] ?? 0) + 1)
        ->and($clone->fresh()->created_by)->toBe('Analista Proveedores Prueba');
});

it('registra en la bitácora quién cambió el estatus, el convenio y la carta, y lo cuenta como productividad', function (): void {
    $ally = $this->allyTemplate->replicate();
    $ally->forceFill(['company_name' => 'ALIADO PRUEBA '.Str::random(5), 'status_agreement' => 'EN PROCESO', 'created_by' => null, 'updated_by' => null])->save();

    expect($ally->fresh()->created_by)->toBe('Analista Proveedores Prueba');

    $ally->forceFill(['status_agreement' => 'Activo'])->save();

    $natural = $this->naturalTemplate->replicate();
    $natural->forceFill(['name' => 'DRA PRUEBA '.Str::random(5), 'status_sistema' => 'PENDIENTE', 'carta_acceptance' => null, 'status_convenio' => null])->save();
    $natural->forceFill(['carta_acceptance' => 'cartas/prueba.pdf', 'status_convenio' => 'GENERAL'])->save();

    $events = DB::table('provider_activity_events')->where('user_name', 'Analista Proveedores Prueba')->pluck('action')->countBy()->all();

    expect($events['CREADO'] ?? 0)->toBe(2)
        ->and($events[ProviderActivityLog::STATUS] ?? 0)->toBe(1)
        ->and($events[ProviderActivityLog::LETTER] ?? 0)->toBe(1)
        ->and($events[ProviderActivityLog::AGREEMENT] ?? 0)->toBe(1)
        ->and($ally->fresh()->updated_by)->toBe('Analista Proveedores Prueba');

    $productivity = ProviderNetworkAnalytics::productivity(OperationsDashboardPeriod::fromFilters(['period' => OperationsDashboardPeriod::THIS_MONTH, 'compare' => true]));
    $mine = collect($productivity['users'])->firstWhere('user', 'Analista Proveedores Prueba');

    expect($mine['created'])->toBe(2)
        ->and($mine['won'])->toBe(1)
        ->and($mine['letters'])->toBe(1)
        ->and($mine['created_aliado'])->toBe(1);
});

it('el detalle filtra por estado, tipo, preferencial, carta y sin ubicación', function (): void {
    $rows = ProviderNetworkAnalytics::rows();
    $first = collect($rows)->firstWhere('geo', '!=', null);

    expect(collect(ProviderNetworkAnalytics::detail(['geo' => $first['geo'], 'type' => $first['type']]))->every(fn (array $row): bool => $row['geo'] === $first['geo'] && $row['type'] === $first['type']))->toBeTrue()
        ->and(collect(ProviderNetworkAnalytics::detail(['preferential' => true]))->every(fn (array $row): bool => str_starts_with($row['agreement'], 'PREFERENCIAL')))->toBeTrue()
        ->and(collect(ProviderNetworkAnalytics::detail(['letter' => 'active_without']))->every(fn (array $row): bool => $row['letter'] === false && ProviderNetworkCatalog::isActiveStatus($row['status'])))->toBeTrue()
        ->and(ProviderNetworkAnalytics::count(['missing_location' => true]))->toBe(collect($rows)->filter(fn (array $row): bool => $row['geo'] === null && $row['abroad'] === null)->count());
});

it('muestra el dashboard en Operaciones con sus widgets y el detalle al hacer clic', function (): void {
    Filament::setCurrentPanel('operations');

    Livewire::test(DashboardRedProveedores::class)->assertSuccessful()->assertSee('Dashboard de proveedores');
    Livewire::test(ProviderNetworkKpiTiles::class)->assertSuccessful()->assertSee('Proveedores en la red')->assertSee('Preferencial');
    Livewire::test(ProviderNetworkMap::class)->assertSuccessful()->assertSee('Red de proveedores en Venezuela')->assertSee('Fuera de Venezuela');
    Livewire::test(ProviderNetworkProductivity::class)->assertSuccessful()->assertSee('Productividad del equipo de proveedores');

    Livewire::test(ProviderNetworkDetail::class)
        ->call('openDetail', ['type' => 'juridico', 'agreement' => 'GENERAL', 'evil' => 'x'], 'Convenio general · Jurídicos')
        ->assertSet('detailFilters', ['type' => 'juridico', 'agreement' => 'GENERAL'])
        ->assertSee('Convenio general · Jurídicos')
        ->call('closeDetail')
        ->assertSet('detailFilters', null);
});

it('lo niega a un analista de Operaciones sin el permiso', function (): void {
    $this->actingAs(User::factory()->make(['id' => 999991, 'departament' => ['OPERACIONES'], 'email' => 'sinpermiso.prov@tudrencasa.com']));

    expect(DashboardRedProveedores::canAccess())->toBeFalse();
});
