<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Business\Resources\Affiliations\AffiliationResource;
use Filament\Facades\Filament;

uses(Tests\TestCase::class);

dataset('fichas de afiliación', [
    'individual' => ['Affiliations/Pages/ViewAffiliation.php', 'AffiliationResource', 'CertificateGenerator'],
    'corporativa' => ['AffiliationCorporates/Pages/ViewAffiliationCorporate.php', 'AffiliationCorporateResource', 'CorporateCertificateGenerator'],
]);

it('la ficha individual lleva «Generar certificado» dentro del menú Acciones, primero', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Pages/ViewAffiliation.php');

    $back = strpos($page, "Action::make('back')");
    $menu = strpos($page, 'FilamentIosActionsMenu::make([');
    $shortcut = strpos($page, "Action::make('generateCertificate')");

    expect($shortcut)->toBeInt()
        ->and($menu)->toBeGreaterThan($back)
        ->and($shortcut)->toBeGreaterThan($menu)
        ->and(substr_count(substr($page, $menu, $shortcut - $menu), 'Action::make('))->toBe(0)
        ->and($page)
        ->toContain("->label('Generar certificado')")
        ->toContain("AffiliationResource::getUrl('certificate-generator', ['afiliacion' => \$record->getKey()])");
});

it('la ficha corporativa lleva «Generar certificado» y «Actualizar población» dentro del menú Acciones, primero', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Pages/ViewAffiliationCorporate.php');

    $menu = strpos($page, 'FilamentIosActionsMenu::make([');
    $certificate = strpos($page, "Action::make('generateCertificate')");
    $population = strpos($page, "Action::make('updatePopulation')");
    $documents = strpos($page, "Action::make('attachDocuments')");

    expect($menu)->toBeInt()
        ->and($certificate)->toBeGreaterThan($menu)
        ->and($population)->toBeGreaterThan($certificate)
        ->and($documents)->toBeGreaterThan($population)
        ->and($page)
        ->toContain("->label('Generar certificado')")
        ->toContain("->label('Actualizar población')")
        ->toContain("AffiliationCorporateResource::getUrl('certificate-generator', ['afiliacion' => \$record->getKey()])");
});

it('el botón solo aparece con permiso del generador y si la afiliación es certificable', function (string $file, string $resource, string $generator): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/'.$file);

    expect($page)
        ->toContain($generator.'::canAccess()')
        ->toContain($generator.'::CERTIFIABLE_STATUSES');
})->with('fichas de afiliación');

it('los generadores precargan la afiliación que llega en ?afiliacion=', function (string $file, string $resource, string $generator): void {
    $folder = str_contains($file, 'Corporate') ? 'AffiliationCorporates' : 'Affiliations';
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/'.$folder.'/Pages/'.$generator.'.php');

    expect($source)
        ->toContain("request()->query('afiliacion', 0)")
        ->toContain('self::certifiableQuery()');
})->with('fichas de afiliación');

it('la URL del atajo lleva el ID de la afiliación al generador', function (): void {
    Filament::setCurrentPanel('business');

    expect(AffiliationResource::getUrl('certificate-generator', ['afiliacion' => 19]))
        ->toEndWith('/business/affiliations/generador-certificado?afiliacion=19')
        ->and(AffiliationCorporateResource::getUrl('certificate-generator', ['afiliacion' => 54]))
        ->toEndWith('/business/affiliation-corporates/generador-certificado?afiliacion=54');
});
