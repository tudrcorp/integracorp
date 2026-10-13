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

it('la ficha muestra «Generar certificado» junto a Volver, fuera del menú de acciones', function (string $file, string $resource, string $generator): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/'.$file);

    $back = strpos($page, "Action::make('back')");
    $shortcut = strpos($page, "Action::make('generateCertificate')");
    $menu = strpos($page, 'FilamentIosActionsMenu::make([');

    expect($shortcut)->toBeInt()
        ->and($shortcut)->toBeGreaterThan($back)
        ->and($shortcut)->toBeLessThan($menu)
        ->and($page)
        ->toContain("->label('Generar certificado')")
        ->toContain($resource."::getUrl('certificate-generator', ['afiliacion' => \$record->getKey()])");
})->with('fichas de afiliación');

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
