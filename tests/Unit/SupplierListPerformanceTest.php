<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\Permission;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Filament\UserNavigationAccess;
use App\Support\HelpdeskBusinessScrumRoles;
use App\Support\Operations\SupplierListStatCounts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

$root = dirname(__DIR__, 2);

it('agrupa por estatus de sistema con claves normalizadas', function (): void {
    $query = Supplier::query()->where('suppliers.status_convenio', 'GENERAL');
    DB::enableQueryLog();
    DB::flushQueryLog();

    $counts = SupplierListStatCounts::bySystemStatus($query);

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and(strtolower(DB::getQueryLog()[0]['query']))->toContain('group by')
        ->and(array_sum($counts))->toBe(Supplier::query()->where('status_convenio', 'GENERAL')->count())
        ->and(array_keys($counts))->toBe(array_map(SupplierListStatCounts::statusKey(...), array_map('strval', array_keys($counts))))
        ->and(SupplierListStatCounts::statusKey(' activo afiliado '))->toBe('ACTIVO AFILIADO')
        ->and(SupplierListStatCounts::statusKey(null))->toBe('');
});

it('funciona con la consulta real de la tabla (select suppliers.* y joins)', function (): void {
    $tableQuery = fn () => Supplier::query()
        ->leftJoin('states', 'suppliers.state_id', '=', 'states.id')
        ->leftJoin('cities', 'suppliers.city_id', '=', 'cities.id')
        ->select(['suppliers.*', 'states.definition as supplier_sort_state_definition', 'cities.definition as supplier_sort_city_definition'])
        ->orderBy('suppliers.name');

    $summary = SupplierListStatCounts::summary($tableQuery(), Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());
    $byStatus = SupplierListStatCounts::bySystemStatus($tableQuery());

    expect($summary['total'])->toBe(Supplier::query()->count())
        ->and(array_sum($byStatus))->toBe(Supplier::query()->count());
});

it('resume las tarjetas superiores en una consulta y coincide con los conteos sueltos', function (): void {
    $start = Carbon::now()->startOfMonth();
    $end = Carbon::now()->endOfMonth();
    DB::enableQueryLog();
    DB::flushQueryLog();

    $summary = SupplierListStatCounts::summary(Supplier::query(), $start, $end);

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and($summary['total'])->toBe(Supplier::query()->count())
        ->and($summary['registered_in_month'])->toBe(Supplier::query()->whereBetween('created_at', [$start, $end])->count())
        ->and($summary['not_agreed'])->toBe(Supplier::query()->where('status_convenio', 'NO CONVENIDO')->count())
        ->and($summary['with_acceptance_letter'])->toBe(
            Supplier::query()
                ->where(fn ($q) => $q->where('status_convenio', 'GENERAL')->orWhere('status_convenio', 'like', '%PREFERENCIAL%'))
                ->whereNotNull('carta_acceptance')
                ->where('carta_acceptance', '<>', '')
                ->count()
        );
});

it('los widgets de convenio usan el conteo agrupado y no un count por tarjeta', function () use ($root): void {
    foreach (['StatsOverviewGeneralSupplier.php', 'StatsOverviewPreferencialSupplier.php'] as $file) {
        $source = file_get_contents($root.'/app/Filament/Operations/Resources/Suppliers/Widgets/'.$file);

        expect($source)
            ->toContain('SupplierListStatCounts::bySystemStatus($base)')
            ->not->toContain('->count()')
            ->not->toContain("'apply' =>");
    }

    $byState = file_get_contents($root.'/app/Filament/Operations/Resources/Suppliers/Widgets/SupplierForState.php');

    expect($byState)
        ->toContain("->groupBy('suppliers.state_id', 'suppliers.status_sistema')")
        ->not->toContain('->pluck(')
        ->not->toContain('->exists()');
});

it('no pasa paginación ni orden a los widgets para que no se recalculen al paginar', function (): void {
    $page = new ListSuppliers;

    expect(array_keys($page->getWidgetData()))
        ->toContain('activeTab', 'tableFilters', 'tableSearch', 'tableColumnSearches')
        ->not->toContain('paginators')
        ->not->toContain('tableSort')
        ->not->toContain('tableRecordsPerPage')
        ->not->toContain('tableRecordsCount');
});

it('deja visibles solo las columnas de uso diario', function () use ($root): void {
    $source = file_get_contents($root.'/app/Filament/Operations/Resources/Suppliers/Tables/SuppliersTable.php');

    $visibleByDefault = [];
    preg_match_all("/(?:TextColumn|IconColumn)::make\\('([^']+)'\\)(.*?)(?=(?:TextColumn|IconColumn)::make\\(|\\]\\)\\s*->filters)/s", $source, $matches, PREG_SET_ORDER);

    foreach ($matches as [$all, $name, $body]) {
        if (! str_contains($body, 'isToggledHiddenByDefault: true')) {
            $visibleByDefault[] = $name;
        }
    }

    expect($visibleByDefault)->toBe([
        'name', 'rif', 'status_convenio', 'status_sistema', 'SupplierClasificacion.description',
        'state.definition', 'city.definition', 'personal_phone', 'correo_principal', 'created_by',
    ]);
});

it('lee los permisos de menú de una sola consulta por usuario', function (): void {
    $user = User::query()
        ->whereHas('permissions')
        ->where('departament', 'not like', '%SUPERADMIN%')
        ->first();

    if (! $user instanceof User) {
        $this->markTestSkipped('No hay usuarios con permisos asignados en esta base.');
    }

    $permission = $user->permissions()->first();
    $fresh = User::query()->findOrFail($user->id);

    DB::enableQueryLog();
    DB::flushQueryLog();

    for ($i = 0; $i < 20; $i++) {
        UserNavigationAccess::userHasAnyModulePermission($fresh, (string) $permission->module);
        UserNavigationAccess::userHasAnyPermissionSlug($fresh, (string) $permission->module, [(string) $permission->slug]);
    }

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and(UserNavigationAccess::userHasAnyPermissionSlug($fresh, (string) $permission->module, [(string) $permission->slug]))->toBeTrue()
        ->and(UserNavigationAccess::userHasAnyPermissionSlug($fresh, (string) $permission->module, ['slug-que-no-existe']))->toBeFalse();
});

it('un usuario sin guardar no consulta permisos', function (): void {
    $user = User::factory()->make(['departament' => ['OPERACIONES']]);
    DB::enableQueryLog();
    DB::flushQueryLog();

    expect(UserNavigationAccess::userHasAnyModulePermission($user, 'OPERACIONES'))->toBeFalse()
        ->and(DB::getQueryLog())->toBe([]);

    $user->setRelation('permissions', collect([new Permission(['module' => 'OPERACIONES', 'slug' => 'x'])]));

    expect(UserNavigationAccess::userHasAnyModulePermission($user, 'OPERACIONES'))->toBeTrue();
});

it('consulta el colaborador RRHH una sola vez por usuario', function (): void {
    $user = User::query()->whereKey(DB::table('rrhh_colaboradors')->whereNotNull('user_id')->value('user_id'))->first()
        ?? User::query()->first();

    DB::enableQueryLog();
    DB::flushQueryLog();

    $first = HelpdeskBusinessScrumRoles::colaboradorForUser($user);
    HelpdeskBusinessScrumRoles::colaboradorForUser($user);
    HelpdeskBusinessScrumRoles::colaboradorForUser($user);

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and(HelpdeskBusinessScrumRoles::colaboradorForUser($user)?->getKey())->toBe($first?->getKey())
        ->and(HelpdeskBusinessScrumRoles::colaboradorForUser(User::factory()->make()))->toBeNull();
});
