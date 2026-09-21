<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Users\Pages\EditUser;
use App\Filament\Business\Resources\Users\Pages\ListUsers;
use App\Filament\Business\Resources\Users\Schemas\UserForm;
use App\Models\User;
use App\Support\Filament\CommercialNetworkPermissionRegistry;
use App\Support\Filament\UserFormPermissionOptions;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * La ficha de usuario necesita el catálogo real de permisos, roles y agencias,
 * así que estos tests corren contra la base de desarrollo **dentro de una
 * transacción que siempre se revierte**. Donde no haya esa base —CI, sqlite en
 * memoria— se saltan en vez de dar un falso rojo.
 */
beforeEach(function (): void {
    /**
     * `phpunit.xml` fuerza `DB_DATABASE=:memory:` para proteger la base de
     * desarrollo, así que la conexión mysql del contenedor apunta a una base
     * inexistente. Aquí se reconstruye desde el `.env` —solo lectura— porque
     * estos tests necesitan el catálogo real de permisos y roles.
     */
    $entorno = credencialesMysqlDelEntorno();

    if ($entorno === null) {
        test()->markTestSkipped('Sin credenciales de base de desarrollo en el .env.');
    }

    config([
        'database.default' => 'mysql',
        'database.connections.mysql.host' => $entorno['DB_HOST'] ?? '127.0.0.1',
        'database.connections.mysql.port' => $entorno['DB_PORT'] ?? '3306',
        'database.connections.mysql.database' => $entorno['DB_DATABASE'] ?? '',
        'database.connections.mysql.username' => $entorno['DB_USERNAME'] ?? '',
        'database.connections.mysql.password' => $entorno['DB_PASSWORD'] ?? '',
    ]);

    DB::purge('mysql');

    try {
        DB::connection('mysql')->getPdo();
    } catch (Throwable) {
        test()->markTestSkipped('Sin base de desarrollo disponible.');
    }

    if (! Schema::connection('mysql')->hasTable('user_permissions')) {
        test()->markTestSkipped('La base no tiene el catálogo de permisos.');
    }

    DB::beginTransaction();
    Filament::setCurrentPanel('business');
    UserFormPermissionOptions::flush();
    CommercialNetworkPermissionRegistry::flush();
});

afterEach(function (): void {
    DB::rollBack();
    UserFormPermissionOptions::flush();
    CommercialNetworkPermissionRegistry::flush();
});

/**
 * Credenciales de la base de desarrollo, leídas del `.env` del proyecto.
 *
 * @return array<string, string>|null
 */
function credencialesMysqlDelEntorno(): ?array
{
    $ruta = base_path('.env');

    if (! is_file($ruta)) {
        return null;
    }

    $valores = [];

    foreach (file($ruta) ?: [] as $linea) {
        if (preg_match('/^(DB_[A-Z_]+)\s*=\s*(.*)$/', trim($linea), $coincidencias) === 1) {
            $valores[$coincidencias[1]] = trim($coincidencias[2], "\"'");
        }
    }

    return ($valores['DB_DATABASE'] ?? '') === '' ? null : $valores;
}

function superAdminDePruebaUi(): User
{
    $user = User::query()
        ->where('status', 'ACTIVO')
        ->where('email', 'like', '%@tudrencasa.com')
        ->get()
        ->first(fn (User $u): bool => is_array($u->departament) && in_array('SUPERADMIN', $u->departament, true));

    if ($user === null) {
        test()->markTestSkipped('No hay un SUPERADMIN activo en esta base.');
    }

    return $user;
}

it('abre la ficha de un usuario con pocas consultas', function (): void {
    $superAdmin = superAdminDePruebaUi();
    $objetivo = User::query()->where('id', '!=', $superAdmin->id)->first();

    $consultas = 0;
    DB::listen(function () use (&$consultas): void {
        $consultas++;
    });

    Livewire::actingAs($superAdmin)
        ->test(EditUser::class, ['record' => $objetivo->id])
        ->assertSuccessful();

    /**
     * Eran 59 antes de memoizar y de unificar la carga de permisos; el
     * margen deja sitio a campos nuevos sin volverse un test frágil.
     */
    expect($consultas)->toBeLessThan(50);
});

it('dibuja solo el módulo que se está configurando', function (): void {
    $superAdmin = superAdminDePruebaUi();

    $componente = Livewire::actingAs($superAdmin)
        ->test(EditUser::class, ['record' => $superAdmin->id])
        ->assertSuccessful();

    $modulos = is_array($superAdmin->departament) ? $superAdmin->departament : [];

    if (count($modulos) < 2) {
        test()->markTestSkipped('El SUPERADMIN de prueba no tiene varios módulos.');
    }

    $casillas = substr_count($componente->html(), 'type="checkbox"');

    /** Con los ocho módulos a la vez eran 180 casillas y 899 KB de HTML. */
    expect($casillas)->toBeLessThan(120)
        ->and($componente->get('data.'.UserForm::MODULE_FOCUS_FIELD))->toBeIn($modulos);
});

it('guardar conserva los permisos de los módulos que no están a la vista', function (): void {
    $superAdmin = superAdminDePruebaUi();

    $objetivo = User::query()
        ->whereHas('permissions')
        ->get()
        ->first(fn (User $u): bool => is_array($u->departament)
            && count($u->departament) >= 2
            && $u->permissions()->distinct()->count('permissions.module') >= 2);

    if ($objetivo === null) {
        test()->markTestSkipped('No hay usuarios con permisos en dos o más módulos.');
    }

    $antes = $objetivo->permissions()->pluck('permissions.id')
        ->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();

    Livewire::actingAs($superAdmin)
        ->test(EditUser::class, ['record' => $objetivo->id])
        ->call('save')
        ->assertHasNoErrors();

    $despues = $objetivo->fresh()->permissions()->pluck('permissions.id')
        ->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();

    /** El formulario dibuja un módulo; los demás viven en el estado del form. */
    expect($despues)->toBe($antes);
});

it('no consulta el listado hasta que la tabla se carga', function (): void {
    $superAdmin = superAdminDePruebaUi();

    $consultas = 0;
    DB::listen(function () use (&$consultas): void {
        $consultas++;
    });

    Livewire::actingAs($superAdmin)->test(ListUsers::class)->assertSuccessful();

    /** Eran 28 consultas y 732 KB antes de diferir la carga. */
    expect($consultas)->toBeLessThan(5);
});

it('el selector de módulo no llega a la tabla de usuarios', function (): void {
    expect(UserForm::MODULE_FOCUS_FIELD)->toBe('permission_module_focus')
        ->and(Schema::hasColumn('users', UserForm::MODULE_FOCUS_FIELD))->toBeFalse();

    foreach ([
        'app/Filament/Business/Resources/Users/Pages/EditUser.php',
        'app/Filament/Business/Resources/Users/Pages/CreateUser.php',
    ] as $pagina) {
        expect((string) file_get_contents(dirname(__DIR__, 2).'/'.$pagina))
            ->toContain("unset(\$data['permissions'], \$data[UserForm::MODULE_FOCUS_FIELD]);");
    }
});

it('la tabla difiere la carga y pagina de diez en diez', function (): void {
    $fuente = (string) file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/Users/Tables/UsersTable.php'
    );

    expect($fuente)
        ->toContain('->deferLoading()')
        ->toContain('->defaultPaginationPageOption(10)');
});
