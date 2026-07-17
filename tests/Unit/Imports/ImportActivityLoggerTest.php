<?php

declare(strict_types=1);

use App\Listeners\LogFilamentImportActivity;
use App\Support\Imports\ImportActivityLogger;
use Filament\Actions\Imports\Events\ImportChunkProcessed;
use Filament\Actions\Imports\Events\ImportCompleted;
use Filament\Actions\Imports\Events\ImportStarted;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;

uses(Tests\TestCase::class);

it('registra el canal imports en la configuracion de logging', function (): void {
    expect(config('logging.channels.imports.driver'))->toBe('daily')
        ->and(config('logging.channels.imports.path'))->toBe(storage_path('logs/imports.log'));
});

it('registra listeners de importacion en el service provider', function (): void {
    $provider = file_get_contents(base_path('app/Providers/AppServiceProvider.php'));

    expect($provider)
        ->toContain(LogFilamentImportActivity::class)
        ->toContain(ImportStarted::class)
        ->toContain(ImportChunkProcessed::class)
        ->toContain(ImportCompleted::class)
        ->toContain(JobFailed::class)
        ->and(Event::hasListeners(ImportStarted::class))->toBeTrue()
        ->and(Event::hasListeners(ImportCompleted::class))->toBeTrue();
});

it('escribe resumen de importacion incompleta en el canal imports', function (): void {
    $logPath = storage_path('logs/testing-imports.log');

    if (is_file($logPath)) {
        unlink($logPath);
    }

    config([
        'logging.channels.imports' => [
            'driver' => 'single',
            'path' => $logPath,
            'level' => 'debug',
            'replace_placeholders' => true,
        ],
    ]);

    app()->forgetInstance('log');

    $failedRowsRelation = Mockery::mock(HasMany::class);
    $failedRowsRelation->shouldReceive('count')->andReturn(10);
    $failedRowsRelation->shouldReceive('whereNotNull')->with('validation_error')->andReturnSelf();
    $failedRowsRelation->shouldReceive('pluck')->with('validation_error')->andReturn(collect([
        'No se pudo interpretar la fecha de nacimiento [21-01-1990].',
        'No se pudo interpretar la fecha de nacimiento [21-01-1990].',
        'The age field must be a number.',
    ]));

    $import = Mockery::mock(Import::class)->makePartial();
    $import->shouldReceive('getKey')->andReturn(99);
    $import->shouldReceive('refresh')->andReturnSelf();
    $import->shouldReceive('getFailedRowsCount')->andReturn(40);
    $import->shouldReceive('failedRows')->andReturn($failedRowsRelation);

    $import->importer = 'App\\Filament\\Imports\\CorporateQuoteDataImporter';
    $import->file_name = 'poblacion.csv';
    $import->total_rows = 100;
    $import->processed_rows = 70;
    $import->successful_rows = 60;
    $import->completed_at = now();

    app(ImportActivityLogger::class)->logCompleted($import, [
        'corporate_quote_id' => 193,
    ]);

    expect(is_file($logPath))->toBeTrue();

    $contents = file_get_contents($logPath);

    expect($contents)
        ->toContain('filas sin procesar')
        ->toContain('"import_id":99')
        ->toContain('"unprocessed_rows":30')
        ->toContain('"successful_rows":60')
        ->toContain('"corporate_quote_id":193');

    unlink($logPath);
});
