<?php

declare(strict_types=1);

use App\Models\AffiliationCorporate;
use App\Models\User;
use App\Support\Affiliations\AffiliationFichaPdfAccess;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Solo lee: los usuarios se arman con make() y todo corre dentro de una
 * transacción que siempre se revierte.
 */
beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

function usuarioFichaQa(array $attributes): User
{
    return User::factory()->make($attributes + [
        'id' => 999_999_091,
        'name' => 'QA FICHA',
        'status' => 'ACTIVO',
    ]);
}

it('deduce el panel por el nombre de la ruta', function (?string $route, string $panel): void {
    expect(AffiliationFichaPdfAccess::panelForRoute($route))->toBe($panel);
})->with([
    ['business.affiliation-corporates.ficha.preview', 'business'],
    ['business.affiliations.ficha.download', 'business'],
    ['administration.affiliations.ficha.preview', 'administration'],
    [null, 'administration'],
]);

it('con acceso a Negocios y al recurso se puede ver la ficha desde Negocios', function (): void {
    $user = usuarioFichaQa(['email' => 'qa.ficha@tudrencasa.com', 'departament' => ['SUPERADMIN', 'NEGOCIOS']]);
    test()->actingAs($user);

    expect(AffiliationFichaPdfAccess::userCanAccess($user, AffiliationFichaPdfAccess::KIND_CORPORATE, 'business.affiliation-corporates.ficha.preview'))->toBeTrue()
        ->and(AffiliationFichaPdfAccess::userCanAccess($user, AffiliationFichaPdfAccess::KIND_INDIVIDUAL, 'business.affiliations.ficha.download'))->toBeTrue();
});

it('un analista de Negocios sin el permiso del menú de afiliaciones no ve la ficha', function (): void {
    $user = usuarioFichaQa(['email' => 'qa.ficha@tudrencasa.com', 'departament' => ['NEGOCIOS']]);
    test()->actingAs($user);

    expect(AffiliationFichaPdfAccess::userCanAccess($user, AffiliationFichaPdfAccess::KIND_CORPORATE, 'business.affiliation-corporates.ficha.preview'))->toBeFalse()
        ->and(AffiliationFichaPdfAccess::userCanAccess($user, AffiliationFichaPdfAccess::KIND_CORPORATE, 'administration.affiliation-corporates.ficha.preview'))->toBeFalse();
});

it('un agente o un usuario sin sesión no pueden ver ninguna ficha', function (): void {
    $agent = usuarioFichaQa(['email' => 'agente.qa@gmail.com', 'is_agent' => true, 'departament' => []]);
    test()->actingAs($agent);

    expect(AffiliationFichaPdfAccess::userCanAccess($agent, AffiliationFichaPdfAccess::KIND_CORPORATE, 'business.affiliation-corporates.ficha.preview'))->toBeFalse()
        ->and(AffiliationFichaPdfAccess::userCanAccess($agent, AffiliationFichaPdfAccess::KIND_INDIVIDUAL, 'administration.affiliations.ficha.download'))->toBeFalse()
        ->and(AffiliationFichaPdfAccess::userCanAccess(null, AffiliationFichaPdfAccess::KIND_CORPORATE, 'business.affiliation-corporates.ficha.preview'))->toBeFalse()
        ->and(AffiliationFichaPdfAccess::userCanAccess($agent, 'otro', 'business.affiliations.ficha.preview'))->toBeFalse();
});

it('responde 403 al agente que pide la ficha corporativa por URL', function (string $route): void {
    $id = AffiliationCorporate::query()->value('id');

    if ($id === null) {
        test()->markTestSkipped('No hay afiliaciones corporativas en la base para probar la ruta.');
    }

    test()->actingAs(usuarioFichaQa(['email' => 'agente.qa@gmail.com', 'is_agent' => true, 'departament' => []]))
        ->get(route($route, ['affiliationCorporate' => $id]))
        ->assertForbidden();
})->with([
    'vista previa de Negocios' => 'business.affiliation-corporates.ficha.preview',
    'descarga de Negocios' => 'business.affiliation-corporates.ficha.download',
    'descarga de Administración' => 'administration.affiliation-corporates.ficha.download',
]);
