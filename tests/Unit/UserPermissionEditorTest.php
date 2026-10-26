<?php

declare(strict_types=1);

use App\Filament\Business\Pages\UserPermissionsOverview;
use App\Filament\Business\Resources\Users\Pages\EditUser;
use App\Filament\Business\Resources\Users\Schemas\UserForm;
use App\Filament\Forms\Components\PermissionMatrix;
use App\Models\User;
use App\Support\Filament\CommercialNetworkPermissionRegistry;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\UserFormPermissionOptions;
use App\Support\Filament\UserPermissionCatalog;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Escribe en base de datos: todo ocurre dentro de una transacción que siempre se
 * revierte. Usa el catálogo real de permisos (no se crea ninguno).
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Filament::setCurrentPanel('business');
    UserFormPermissionOptions::flush();
    UserPermissionCatalog::flush();
    CommercialNetworkPermissionRegistry::flush();

    test()->actingAs(User::factory()->make([
        'id' => 999_999_073,
        'name' => 'QA PERMISOS',
        'email' => 'qa.permisos@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'NEGOCIOS'],
    ]));
});

afterEach(function (): void {
    DB::rollBack();
    UserFormPermissionOptions::flush();
    UserPermissionCatalog::flush();
    CommercialNetworkPermissionRegistry::flush();
});

/**
 * Los dos primeros permisos de un módulo del catálogo real.
 *
 * @return list<int>
 */
function permisosDelModulo(string $module, int $count = 2): array
{
    $entry = collect(UserPermissionCatalog::modules())->firstWhere('key', $module);

    if ($entry === null) {
        test()->markTestSkipped('La base no tiene permisos del módulo '.$module.'.');
    }

    $ids = collect($entry['groups'])->flatMap(fn (array $group): array => array_column($group['items'], 'id'))->take($count)->values()->all();

    if (count($ids) < $count) {
        test()->markTestSkipped('El módulo '.$module.' no tiene suficientes permisos.');
    }

    return $ids;
}

/**
 * @param  list<string>  $departments
 */
function usuarioPermisosQa(string $email, array $departments): User
{
    $id = DB::table('users')->insertGetId([
        'name' => 'QA '.strtoupper(strtok($email, '@')),
        'email' => $email,
        'password' => bcrypt('secreto-de-prueba'),
        'status' => 'ACTIVO',
        'departament' => json_encode($departments),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return User::query()->findOrFail($id);
}

it('el catálogo solo trae módulos asignables y marca las acciones especiales', function (): void {
    $modules = collect(UserPermissionCatalog::modules());
    $item = $modules->first()['groups'][0]['items'][0] ?? null;

    expect($modules->pluck('key')->all())->not->toContain('TELEMEDICINA')
        ->not->toContain('SUPERADMIN')
        ->and($modules->every(fn (array $module): bool => $module['total'] > 0 && str_starts_with($module['accent'], '#')))->toBeTrue()
        ->and($item)->toHaveKeys(['id', 'name', 'action'])
        ->and(collect(UserPermissionCatalog::index())->contains(fn (array $entry): bool => $entry['name'] !== ''))->toBeTrue();
});

it('lo que manda el navegador se filtra: solo permisos asignables de módulos asignados', function (): void {
    [$negocios] = permisosDelModulo('NEGOCIOS', 1);
    [$operaciones] = permisosDelModulo('OPERACIONES', 1);

    expect(UserPermissionCatalog::sanitize([$negocios, (string) $operaciones, 'x', 999_999_999, $negocios], ['NEGOCIOS']))->toBe([$negocios])
        ->and(UserPermissionCatalog::sanitize([$negocios, $operaciones], []))->toBe([])
        ->and(UserForm::extractPermissionIdsFromState([
            'departament' => ['NEGOCIOS', 'OPERACIONES'],
            UserForm::PERMISSION_IDS_FIELD => [$negocios, $operaciones],
            CommercialNetworkPermissionRegistry::FIELD_KEY => [999_999_998],
        ]))->toEqualCanonicalizing([$negocios, $operaciones]);
});

it('el editor carga los permisos del usuario y guarda lo agregado y lo quitado, con traza', function (): void {
    [$a, $b] = permisosDelModulo('NEGOCIOS');
    $target = usuarioPermisosQa('qa.editor.permisos@tudrencasa.com', ['NEGOCIOS']);
    $target->permissions()->attach($a, ['created_by' => 'QA', 'updated_by' => 'QA']);

    $component = Livewire::test(EditUser::class, ['record' => $target->getKey()])
        ->assertSuccessful()
        ->assertSee('Copiar permisos de…')
        ->assertSee('Ver por pantalla');

    expect($component->get('data.'.UserForm::PERMISSION_IDS_FIELD))->toBe([$a]);

    $logsBefore = DB::table('logs')->where('action', 'AUDIT_USER_PERMISSIONS_UPDATED')->count();

    $component->set('data.'.UserForm::PERMISSION_IDS_FIELD, [$b, 999_999_999])
        ->call('save')
        ->assertHasNoErrors();

    expect($target->fresh()->permissions()->pluck('permissions.id')->map(fn (mixed $id): int => (int) $id)->all())->toBe([$b])
        ->and(DB::table('logs')->where('action', 'AUDIT_USER_PERMISSIONS_UPDATED')->count())->toBe($logsBefore + 1);
});

it('quitar un módulo quita sus permisos al guardar', function (): void {
    [$negocios] = permisosDelModulo('NEGOCIOS', 1);
    [$operaciones] = permisosDelModulo('OPERACIONES', 1);
    $target = usuarioPermisosQa('qa.modulo.permisos@tudrencasa.com', ['NEGOCIOS', 'OPERACIONES']);
    $target->permissions()->attach([$negocios, $operaciones], ['created_by' => 'QA', 'updated_by' => 'QA']);

    Livewire::test(EditUser::class, ['record' => $target->getKey()])
        ->set('data.departament', ['NEGOCIOS'])
        ->call('save')
        ->assertHasNoErrors();

    expect($target->fresh()->permissions()->pluck('permissions.id')->map(fn (mixed $id): int => (int) $id)->all())->toBe([$negocios]);
});

it('«Copiar permisos de…» carga los de otro usuario y sus módulos sin guardar nada', function (): void {
    [$negocios] = permisosDelModulo('NEGOCIOS', 1);
    [$operaciones] = permisosDelModulo('OPERACIONES', 1);
    $source = usuarioPermisosQa('qa.fuente.permisos@tudrencasa.com', ['OPERACIONES']);
    $source->permissions()->attach($operaciones, ['created_by' => 'QA', 'updated_by' => 'QA']);
    $target = usuarioPermisosQa('qa.destino.permisos@tudrencasa.com', ['NEGOCIOS']);
    $target->permissions()->attach($negocios, ['created_by' => 'QA', 'updated_by' => 'QA']);

    $action = TestAction::make('copyPermissionsFrom')->schemaComponent('permissionsByModule', schema: 'form');

    /** callAction no rellena la modal: se monta, se llena y se ejecuta. */
    $copy = fn ($component, string $mode) => $component
        ->mountAction($action)
        ->set('mountedActions.0.data.source_user_id', $source->getKey())
        ->set('mountedActions.0.data.mode', $mode)
        ->set('mountedActions.0.data.copy_modules', true)
        ->callMountedAction()
        ->assertHasNoErrors();

    $component = $copy(Livewire::test(EditUser::class, ['record' => $target->getKey()]), 'merge')
        ->assertNotified('Permisos cargados de '.$source->name);

    expect($component->get('data.departament'))->toEqualCanonicalizing(['NEGOCIOS', 'OPERACIONES'])
        ->and($component->get('data.'.UserForm::PERMISSION_IDS_FIELD))->toEqualCanonicalizing([$negocios, $operaciones])
        ->and($target->fresh()->permissions()->count())->toBe(1);

    $copy($component, 'replace');

    expect($component->get('data.'.UserForm::PERMISSION_IDS_FIELD))->toBe([$operaciones]);
});

it('el campo resuelve la ruta de los módulos junto a la suya', function (): void {
    $field = PermissionMatrix::make('permission_ids')->departmentsField('departament');

    expect(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/forms/components/permission-matrix.blade.php'))
        ->toContain('wire:ignore')
        ->toContain('$wire.$entangle(@js($statePath))')
        ->toContain('Cambios sin guardar')
        ->toContain('Solo marcados')
        ->toContain('Deshacer todo')
        ->and((fn (): string => $this->departmentsField)->call($field))->toBe('departament');
});

it('permisos por pantalla: solo SUPERADMIN, y da o quita un permiso a varias personas con traza', function (): void {
    [$permission] = permisosDelModulo('OPERACIONES', 1);
    $con = usuarioPermisosQa('qa.con.modulo@tudrencasa.com', ['OPERACIONES']);
    $sin = usuarioPermisosQa('qa.sin.modulo@tudrencasa.com', ['NEGOCIOS']);

    expect(DepartmentNavigationPermissionRegistry::isSuperAdminOnly(UserPermissionsOverview::class))->toBeTrue()
        ->and(UserPermissionsOverview::canAccess())->toBeTrue();

    $page = Livewire::test(UserPermissionsOverview::class)
        ->assertSuccessful()
        ->assertSee('Elige un permiso')
        ->call('selectPermission', 999_999_999)
        ->assertSet('permissionId', null)
        ->call('selectPermission', $permission)
        ->assertSet('permissionId', $permission);

    $page->instance()->grant([$con->id, $sin->id], true);

    expect($con->fresh()->permissions()->whereKey($permission)->exists())->toBeTrue()
        ->and($sin->fresh()->permissions()->whereKey($permission)->exists())->toBeTrue()
        ->and($sin->fresh()->departament)->toContain('OPERACIONES')
        ->and(UserPermissionsOverview::holderCounts()[$permission] ?? 0)->toBeGreaterThanOrEqual(2);

    /** El permiso real lo tienen más personas: se busca a las de prueba. */
    $page->searchTable('qa.')->assertCanSeeTableRecords([$con, $sin]);

    $page->instance()->revoke(new \Illuminate\Database\Eloquent\Collection([$con]));

    expect($con->fresh()->permissions()->whereKey($permission)->exists())->toBeFalse()
        ->and($sin->fresh()->permissions()->whereKey($permission)->exists())->toBeTrue()
        ->and(DB::table('logs')->where('action', 'AUDIT_USER_PERMISSION_REVOKED')->exists())->toBeTrue();
});

it('sin SUPERADMIN no se entra a permisos por pantalla', function (): void {
    test()->actingAs(User::factory()->make([
        'id' => 999_999_074,
        'email' => 'qa.negocios@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS'],
    ]));

    expect(UserPermissionsOverview::canAccess())->toBeFalse();
});

it('la pestaña Módulos rechaza un módulo inventado y exige al menos uno al personal interno', function (): void {
    $target = usuarioPermisosQa('qa.validacion.modulos@tudrencasa.com', ['NEGOCIOS']);

    Livewire::test(EditUser::class, ['record' => $target->getKey()])
        ->assertSee('Paneles de trabajo')
        ->assertSee('Accesos especiales')
        ->set('data.departament', ['NEGOCIOS', 'MODULO-INVENTADO'])
        ->call('save')
        ->assertHasErrors(['data.departament.1'])
        ->set('data.departament', [])
        ->call('save')
        ->assertHasErrors(['data.departament' => 'required']);

    expect($target->fresh()->departament)->toBe(['NEGOCIOS']);
});

it('los roles se guardan desde las tarjetas y los de red comercial cambian las pestañas', function (): void {
    $target = usuarioPermisosQa('qa.roles.usuario@tudrencasa.com', ['NEGOCIOS']);

    Livewire::test(EditUser::class, ['record' => $target->getKey()])
        ->assertSee('Super Administrador de catálogo')
        ->assertSee('No da acceso total')
        ->assertSee('Restringe lo que ve')
        ->assertSee('Otros indicadores')
        ->assertDontSee('Seguimiento de afiliados')
        ->set('data.is_agent', true)
        ->assertSee('Seguimiento de afiliados')
        ->set('data.is_business_admin', true)
        ->set('data.roles_editor', 'ignorado')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $target->fresh();

    expect((bool) $fresh->is_agent)->toBeTrue()
        ->and((bool) $fresh->is_business_admin)->toBeTrue()
        ->and((bool) $fresh->is_superAdmin)->toBeFalse()
        ->and(\Illuminate\Support\Facades\Schema::hasColumn('users', 'roles_editor'))->toBeFalse();
});
