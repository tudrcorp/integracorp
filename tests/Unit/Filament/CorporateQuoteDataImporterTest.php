<?php

declare(strict_types=1);

use App\Support\Imports\CorporateQuoteBirthDateParser;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;

uses(Tests\TestCase::class);

it('parsea fechas de nacimiento en formatos comunes de excel', function (string $raw, string $expected): void {
    $date = app(CorporateQuoteBirthDateParser::class)->parse($raw);

    expect($date->format('Y-m-d'))->toBe($expected);
})->with([
    'slash' => ['21/01/1990', '1990-01-21'],
    'dash' => ['21-01-1990', '1990-01-21'],
    'iso' => ['1990-01-21', '1990-01-21'],
]);

it('lanza error claro cuando la fecha de nacimiento no es interpretable', function (): void {
    app(CorporateQuoteBirthDateParser::class)->parse('fecha-invalida');
})->throws(RowImportFailedException::class, 'No se pudo interpretar la fecha de nacimiento');

it('usa el parser de fechas y el logger en el importer de poblacion', function (): void {
    $source = file_get_contents(base_path('app/Filament/Imports/CorporateQuoteDataImporter.php'));

    expect($source)
        ->toContain('CorporateQuoteBirthDateParser')
        ->toContain('ImportActivityLogger')
        ->toContain('RowImportFailedException')
        ->toContain('storage/logs/imports.log');
});
