<?php

declare(strict_types=1);

use App\Filament\Imports\CorporateQuoteDataImporter;
use App\Filament\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;

uses(Tests\TestCase::class);

it('usa un job de importacion con timeout e intentos compatibles con redis', function (): void {
    $job = (new ReflectionClass(ImportCsv::class))->newInstanceWithoutConstructor();

    expect($job->tries)->toBe(50)
        ->and($job->timeout)->toBe(600)
        ->and($job->maxExceptions)->toBe(3);
});

it('desactiva WithoutOverlapping en el importer de poblacion corporativa', function (): void {
    $importer = new CorporateQuoteDataImporter(
        import: new Import,
        columnMap: [],
        options: ['corporate_quote_id' => 1],
    );

    expect($importer->getJobMiddleware())->toBe([]);
});

it('configura redis retry_after por encima del timeout del job de importacion', function (): void {
    expect((int) config('queue.connections.redis.retry_after'))
        ->toBeGreaterThanOrEqual(900);
});

it('enlaza el job personalizado en la accion de importar poblacion', function (): void {
    $source = file_get_contents(base_path('app/Filament/Business/Resources/CorporateQuotes/RelationManagers/CorporateQuoteDataRelationManager.php'));

    expect($source)
        ->toContain('use App\Filament\Imports\Jobs\ImportCsv;')
        ->toContain('->job(ImportCsv::class)');
});
