<?php

declare(strict_types=1);

use App\Filament\Shared\Affiliations\Actions\ListAffiliationAffiliatesAction;
use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Support\Affiliations\AffiliateDocumentsPackage;

uses(Tests\TestCase::class);

/**
 * Los paneles comerciales solo leen: ningún caso de este archivo escribe en base de datos.
 * Los PDF de apoyo se crean en el directorio real de tarjetas y se borran al terminar.
 */
function tarjetaAfiliacionDePrueba(string $filename): string
{
    $directory = AffiliateDocumentsPackage::carnetDirectory();

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $path = $directory.$filename;
    file_put_contents($path, '%PDF-1.4 test');

    return $path;
}

it('registra la acción Listar Afiliados en las seis tablas de afiliaciones de la red comercial', function (string $file) {
    $contents = file_get_contents(base_path($file));

    expect($contents)->toContain('ListAffiliationAffiliatesAction::make()');
})->with([
    'master individual' => 'app/Filament/Master/Resources/Affiliations/Tables/AffiliationsTable.php',
    'master corporativa' => 'app/Filament/Master/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php',
    'general individual' => 'app/Filament/General/Resources/Affiliations/Tables/AffiliationsTable.php',
    'general corporativa' => 'app/Filament/General/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php',
    'agentes individual' => 'app/Filament/Agents/Resources/Affiliations/Tables/AffiliationsTable.php',
    'agentes corporativa' => 'app/Filament/Agents/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php',
]);

it('configura la acción como un modal de solo lectura', function () {
    $action = ListAffiliationAffiliatesAction::make();

    expect($action->getName())->toBe('list_affiliates')
        ->and($action->getLabel())->toBe('Listar Afiliados')
        ->and($action->getModalSubmitAction())->toBeNull()
        ->and($action->getModalCancelActionLabel())->toBe('Cerrar');
});

it('arma el paquete del afiliado con su carnet propio y lo empaqueta en un ZIP', function () {
    $code = 'TEST-PKG-'.bin2hex(random_bytes(3));

    $affiliation = new Affiliation([
        'code' => $code,
        'plan_id' => 1,
        'nro_identificacion_ti' => '11111111',
    ]);

    $affiliate = new Affiliate(['nro_identificacion' => '22222222', 'full_name' => 'ANA PEREZ']);
    $affiliate->id = 4321;

    $carnetPath = tarjetaAfiliacionDePrueba('TAR-'.$code.'-4321.pdf');

    try {
        $package = AffiliateDocumentsPackage::forIndividual($affiliation, $affiliate);

        expect($package->carnetPath)->toBe($carnetPath)
            ->and($package->missingLabels())->not->toContain(AffiliateDocumentsPackage::CARNET_LABEL)
            ->and($package->paths())->toHaveKey('Carnet-22222222.pdf')
            ->and($package->downloadFilename())->toBe('DOCUMENTOS-'.$code.'-22222222.zip');

        $zipPath = $package->buildZip();

        $zip = new ZipArchive;
        $zip->open($zipPath);
        $entries = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entries[] = $zip->getNameIndex($index);
        }
        $zip->close();
        @unlink($zipPath);

        expect($entries)->toContain('Carnet-22222222.pdf');
    } finally {
        @unlink($carnetPath);
    }
});

it('solo atribuye la tarjeta antigua sin id al titular de la afiliación', function () {
    $code = 'TEST-PKG-'.bin2hex(random_bytes(3));
    $legacyPath = tarjetaAfiliacionDePrueba('TAR-'.$code.'.pdf');

    $affiliation = new Affiliation([
        'code' => $code,
        'plan_id' => 1,
        'nro_identificacion_ti' => '11111111',
    ]);

    try {
        $titular = new Affiliate(['nro_identificacion' => '11111111', 'full_name' => 'JUAN PEREZ']);
        $titular->id = 10;

        $familiar = new Affiliate(['nro_identificacion' => '22222222', 'full_name' => 'ANA PEREZ']);
        $familiar->id = 11;

        expect(AffiliateDocumentsPackage::forIndividual($affiliation, $titular)->carnetPath)->toBe($legacyPath);

        $paqueteFamiliar = AffiliateDocumentsPackage::forIndividual($affiliation, $familiar);

        expect($paqueteFamiliar->carnetPath)->toBeNull()
            ->and($paqueteFamiliar->missingLabels())->toContain(AffiliateDocumentsPackage::CARNET_LABEL)
            ->and($paqueteFamiliar->paths())->not->toHaveKey('Carnet-22222222.pdf');
    } finally {
        @unlink($legacyPath);
    }
});

it('no entrega documentación cuando el afiliado no tiene ni carnet ni condicionado', function () {
    $affiliation = new Affiliation([
        'code' => 'TEST-PKG-'.bin2hex(random_bytes(3)),
        'plan_id' => null,
        'nro_identificacion_ti' => '11111111',
    ]);

    $affiliate = new Affiliate(['nro_identificacion' => '33333333', 'full_name' => 'LUIS PEREZ']);
    $affiliate->id = 777;

    $package = AffiliateDocumentsPackage::forIndividual($affiliation, $affiliate);

    expect($package->hasAnyDocument())->toBeFalse()
        ->and($package->missingLabels())->toBe([
            AffiliateDocumentsPackage::CONDICIONADO_LABEL,
            AffiliateDocumentsPackage::CARNET_LABEL,
        ]);

    expect(fn () => $package->buildZip())->toThrow(RuntimeException::class);
});
