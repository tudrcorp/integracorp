<?php

declare(strict_types=1);

use App\Filament\Administration\Pages\Tema as AdministrationTema;
use App\Filament\Business\Pages\Tema as BusinessTema;
use App\Filament\Shared\Theme\Pages\ThemeSettingsPage;
use App\Models\SystemThemeChange;
use App\Models\User;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Theme\IntegracorpTheme as Theme;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Conexión sqlite `:memory:` propia: este test nunca toca la base real.
 */
beforeEach(function (): void {
    config([
        'cache.default' => 'array',
        'session.driver' => 'array',
        'database.connections.theme_testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false],
    ]);

    $this->previousConnection = config('database.default');
    config()->set('database.default', 'theme_testing');
    DB::purge('theme_testing');
    DB::setDefaultConnection('theme_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite');

    /** La migración real, dos veces: es idempotente. */
    $migration = require base_path('database/migrations/2026_10_30_180000_create_system_theme_changes_table.php');
    $migration->up();
    $migration->up();

    Cache::store('array')->flush();
    session()->flush();
    Filament::setCurrentPanel(Filament::getPanel('business'));
});

afterEach(function (): void {
    DB::purge('theme_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

function themeUser(array $departments = ['SUPERADMIN'], int $id = 900): User
{
    $user = new User(['name' => 'Analista QA', 'email' => 'qa@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => $departments]);
    $user->id = $id;
    $user->setRelation('permissions', new Collection);
    Auth::setUser($user);

    return $user;
}

function themeOnRoute(string $routeName): void
{
    $request = Request::create('/x');
    $request->setRouteResolver(static function () use ($routeName): Route {
        $route = new Route('GET', '/x', []);

        return $route->name($routeName);
    });
    app()->instance('request', $request);
}

function themeCss(): string
{
    return (string) file_get_contents(public_path('brand/integracorp/tema.css'));
}

it('sin cambios rige el tema original y no se carga ninguna hoja', function (): void {
    themeUser(['OPERACIONES']);
    themeOnRoute('filament.business.pages.dashboard');

    expect(Theme::active())->toBe(Theme::ORIGINAL)
        ->and((string) Theme::stylesheet())->toBe('');
});

it('aplicar afecta a todos, queda en el historial y se puede volver atrás', function (): void {
    $admin = themeUser();

    Theme::apply(Theme::BRAND, $admin);
    Theme::apply(Theme::BRAND, $admin);

    themeUser(['OPERACIONES'], 901);
    themeOnRoute('filament.operations.pages.dashboard');
    Filament::setCurrentPanel(Filament::getPanel('operations'));

    expect((string) Theme::stylesheet())->toContain('brand/integracorp/tema.css')
        ->and(SystemThemeChange::query()->count())->toBe(1);

    Theme::apply(Theme::ORIGINAL, $admin);

    expect((string) Theme::stylesheet())->toBe('')
        ->and(SystemThemeChange::query()->latest('id')->first())->theme->toBe(Theme::ORIGINAL)->previous_theme->toBe(Theme::BRAND)->changed_by_user_id->toBe(900);
});

it('rechaza un tema desconocido', function (): void {
    expect(fn () => Theme::apply('rosa', null))->toThrow(InvalidArgumentException::class);
});

it('alcanza a los siete paneles y a ningún otro', function (string $panel, bool $loads): void {
    Theme::apply(Theme::BRAND, null);
    themeUser();
    Filament::setCurrentPanel(Filament::getPanel($panel));
    themeOnRoute("filament.{$panel}.pages.dashboard");

    expect((string) Theme::stylesheet() !== '')->toBe($loads);
})->with([
    ['business', true], ['administration', true], ['marketing', true], ['operations', true], ['telemedicina', true], ['metrics', true], ['projects', true],
    ['agents', false], ['master', false], ['general', false], ['admin', false],
]);

it('nunca toca un login', function (string $route): void {
    Theme::apply(Theme::BRAND, null);
    themeOnRoute($route);

    expect((string) Theme::stylesheet())->toBe('');
})->with(['filament.business.auth.login', 'filament.operations.auth.login', 'filament.business.auth.password-reset.request']);

it('la vista previa la ve solo el SUPERADMIN que la activó', function (): void {
    themeUser();
    themeOnRoute('filament.business.pages.dashboard');
    Theme::startPreview(Theme::BRAND);

    expect((string) Theme::stylesheet())->toContain('tema.css')
        ->and((string) Theme::previewBanner())->toContain('Solo tú la ves')
        ->and(Theme::active())->toBe(Theme::ORIGINAL);

    themeUser(['NEGOCIOS'], 902);

    expect((string) Theme::stylesheet())->toBe('')
        ->and((string) Theme::previewBanner())->toBe('');
});

it('la vista previa del original quita el tema de marca solo para quien la activó', function (): void {
    Theme::apply(Theme::BRAND, null);
    themeUser();
    themeOnRoute('filament.business.pages.dashboard');
    Theme::startPreview(Theme::ORIGINAL);

    expect((string) Theme::stylesheet())->toBe('');
});

it('si falta la tabla rige el original sin romper la pantalla', function (): void {
    Schema::drop('system_theme_changes');
    Cache::store('array')->flush();

    expect(Theme::active())->toBe(Theme::ORIGINAL);
});

it('solo el SUPERADMIN abre la página, y no crea permiso de menú', function (): void {
    themeUser(['NEGOCIOS']);
    expect(BusinessTema::canAccess())->toBeFalse()->and(BusinessTema::shouldRegisterNavigation())->toBeFalse();

    themeUser();
    expect(BusinessTema::canAccess())->toBeTrue()
        ->and(DepartmentNavigationPermissionRegistry::isManagedOutsideNavigationPermissions(AdministrationTema::class))->toBeTrue();
});

it('los siete paneles tienen el ítem Tema', function (): void {
    foreach (Theme::PANELS as $panel) {
        expect(collect(Filament::getPanel($panel)->getPages())->contains(static fn (string $page): bool => is_subclass_of($page, ThemeSettingsPage::class)))->toBeTrue();
    }
});

it('la página muestra el detalle, previsualiza y aplica con confirmación', function (): void {
    themeUser();
    themeOnRoute('filament.business.pages.tema');

    Livewire::test(BusinessTema::class)
        ->assertSee('Tema original')
        ->assertSee('Cormorant Garamond')
        ->assertSee('#D29D45')
        ->assertSee('#4C566A')
        ->assertSee('Todavía nadie ha cambiado el tema')
        ->call('previewTheme', Theme::BRAND)
        ->assertRedirect();

    expect(Theme::preview())->toBe(Theme::BRAND)->and(Theme::active())->toBe(Theme::ORIGINAL);

    Livewire::test(BusinessTema::class)
        ->assertSee('Estás en vista previa')
        ->mountAction('applyTheme', ['theme' => Theme::BRAND])
        ->callMountedAction();

    expect(Theme::active())->toBe(Theme::BRAND)->and(Theme::preview())->toBeNull();
});

it('quien no es SUPERADMIN no puede previsualizar ni aplicar por Livewire', function (): void {
    themeUser(['NEGOCIOS']);

    Livewire::test(BusinessTema::class)->assertForbidden();

    expect(Theme::active())->toBe(Theme::ORIGINAL);
});

it('toda regla de la hoja queda acotada a los siete paneles y sin recursos externos', function (): void {
    $css = (string) preg_replace('~/\*.*?\*/~s', '', themeCss());
    $css = (string) preg_replace('~@import[^;]+;~', '', $css);

    preg_match_all('~([^{}]+)\{[^{}]*\}~', $css, $rules);

    expect($rules[1])->not->toBeEmpty()->and(themeCss())->not->toContain('https://');

    foreach ($rules[1] as $selectors) {
        foreach (preg_split('~,(?![^(]*\))~', $selectors) as $selector) {
            expect($selector)->toContain('fi-panel-');
        }
    }

    foreach (['agents', 'master', 'general'] as $external) {
        expect(themeCss())->not->toContain('fi-panel-'.$external);
    }
});

it('usa colores y familias del manual, conserva estados y deja cifras clínicas en Manrope', function (): void {
    $fonts = (string) file_get_contents(public_path('brand/integracorp/fonts.css'));

    expect(themeCss())->toContain('#0f2233', '#0b1a28', '#d29d45', '#efe9df', "'Cormorant Garamond'", "'Manrope'")
        ->not->toContain('--danger-', '--success-', '--warning-');

    preg_match('~[^}]*stat-value[^{]*\{~', themeCss(), $numbers);
    expect($numbers[0])->not->toContain('fi-panel-telemedicina');

    preg_match_all("~url\('([^']+)'\)~", $fonts, $files);
    expect($files[1])->toHaveCount(5);

    foreach ($files[1] as $file) {
        expect(public_path('brand/integracorp/'.$file))->toBeFile();
    }
});
