<?php

declare(strict_types=1);

use App\Services\OperationInventoryProductCodeGenerator;

it('formatea códigos con prefijo TDG y padding de 5 dígitos', function () {
    $generator = new OperationInventoryProductCodeGenerator;

    expect($generator->format(1))->toBe('TDG-00001')
        ->and($generator->format(12))->toBe('TDG-00012')
        ->and($generator->format(99999))->toBe('TDG-99999');
});

it('extrae la secuencia numérica de un código TDG', function () {
    $generator = new OperationInventoryProductCodeGenerator;

    expect($generator->sequenceFromCode('TDG-00001'))->toBe(1)
        ->and($generator->sequenceFromCode('TDG-00042'))->toBe(42)
        ->and($generator->sequenceFromCode('PROD-00001'))->toBe(0)
        ->and($generator->sequenceFromCode('invalid'))->toBe(0);
});

it('incluye los productos de mobiliario en el seeder', function () {
    $path = dirname(__DIR__, 2).'/database/seeders/OperationInventoryProductMobiliarioSeeder.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain("'ALFOMBRAS'")
        ->toContain("'REFRIGERADOR MINI BAR MIKLEXUS DE 80LTS'")
        ->toContain("'TELEFONO REDMI 15'")
        ->toContain("'REGLETA'")
        ->toContain('OperationInventoryProductCodeGenerator')
        ->toContain("'Mobiliario'");
});
