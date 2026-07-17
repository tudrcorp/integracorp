<?php

declare(strict_types=1);

use App\Support\Imports\CorporateQuoteBirthDateParser;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;

uses(Tests\TestCase::class);

it('parsea fechas de nacimiento en formatos comunes de excel', function (string $raw, string $expected): void {
    $date = app(CorporateQuoteBirthDateParser::class)->parse($raw);

    expect($date->format('Y-m-d'))->toBe($expected);
})->with([
    'slash padded' => ['21/01/1990', '1990-01-21'],
    'slash unpadded' => ['1/1/1990', '1990-01-01'],
    'slash mixed day' => ['21/1/1990', '1990-01-21'],
    'slash mixed month' => ['01/1/1990', '1990-01-01'],
    'slash mixed both' => ['1/01/1990', '1990-01-01'],
    'dash' => ['21-01-1990', '1990-01-21'],
    'dash unpadded' => ['1-1-1990', '1990-01-01'],
    'dots' => ['21.01.1990', '1990-01-21'],
    'iso' => ['1990-01-21', '1990-01-21'],
    'us month day' => ['01/21/1990', '1990-01-21'],
    'with time' => ['21/01/1990 00:00:00', '1990-01-21'],
    'with time unpadded hour' => ['21/01/1990 0:00:00', '1990-01-21'],
    'two digit year' => ['21/1/90', '1990-01-21'],
    'excel serial' => ['32894', '1990-01-21'],
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

it('permite reintentos largos para importaciones masivas en cola', function (): void {
    $source = file_get_contents(base_path('app/Filament/Imports/CorporateQuoteDataImporter.php'));

    expect($source)
        ->toContain('addHours(6)')
        ->toContain('expireAfter(7200)')
        ->not->toContain('addMinutes(10)');
});

it('configura delimitador punto y coma para el csv de poblacion corporativa', function (): void {
    $source = file_get_contents(base_path('app/Filament/Business/Resources/CorporateQuotes/RelationManagers/CorporateQuoteDataRelationManager.php'));

    expect($source)
        ->toContain("->csvDelimiter(';')")
        ->toContain('->chunkSize(100)');
});
