<?php

declare(strict_types=1);

use App\Jobs\RegenerateSupplierReportPdfJob;
use App\Models\Supplier;
use App\Observers\SupplierReportPdfObserver;
use App\Services\SupplierReportPdfService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    Storage::fake(SupplierReportPdfService::STORAGE_DISK);
});

it('sirve el PDF guardado en disco sin volver a generarlo', function (): void {
    $version = SupplierReportPdfService::pdfCacheVersion();
    Storage::disk(SupplierReportPdfService::STORAGE_DISK)
        ->put(SupplierReportPdfService::storedFilePath($version), '%PDF-guardado');

    expect(SupplierReportPdfService::outputBinaryCached())->toBe('%PDF-guardado');
});

it('ignora un archivo guardado que no es un PDF válido', function (): void {
    $version = SupplierReportPdfService::pdfCacheVersion();
    $disk = Storage::disk(SupplierReportPdfService::STORAGE_DISK);
    $disk->put(SupplierReportPdfService::storedFilePath($version), 'basura');

    $binary = SupplierReportPdfService::outputBinaryCached();

    expect($binary)->toStartWith('%PDF')
        ->and($disk->get(SupplierReportPdfService::storedFilePath($version)))->toStartWith('%PDF');
});

it('genera el PDF una sola vez, lo deja en disco y borra las versiones viejas', function (): void {
    $disk = Storage::disk(SupplierReportPdfService::STORAGE_DISK);
    $oldPath = SupplierReportPdfService::storedFilePath('version-vieja');
    $disk->put($oldPath, '%PDF-viejo');

    expect(SupplierReportPdfService::regenerateStoredFile())->toBeTrue()
        ->and(SupplierReportPdfService::regenerateStoredFile())->toBeFalse();

    $currentPath = SupplierReportPdfService::storedFilePath(SupplierReportPdfService::pdfCacheVersion());

    expect($disk->exists($currentPath))->toBeTrue()
        ->and($disk->get($currentPath))->toStartWith('%PDF')
        ->and($disk->exists($oldPath))->toBeFalse()
        ->and(collect($disk->files(SupplierReportPdfService::STORAGE_DIRECTORY))->filter(fn (string $file): bool => str_ends_with($file, '.tmp'))->all())->toBe([]);
});

it('no genera en cola si otro proceso ya tiene el candado', function (): void {
    $lock = Cache::lock('supplier_report_pdf:generate', 300);
    expect($lock->get())->toBeTrue();

    try {
        expect(SupplierReportPdfService::regenerateStoredFile())->toBeFalse()
            ->and(Storage::disk(SupplierReportPdfService::STORAGE_DISK)->files(SupplierReportPdfService::STORAGE_DIRECTORY))->toBe([]);
    } finally {
        $lock->release();
    }
});

it('encola la regeneración en system cuando se guarda o borra un proveedor', function (): void {
    Queue::fake();

    $observer = new SupplierReportPdfObserver;
    $observer->saved(new Supplier);
    $observer->deleted(new Supplier);

    Queue::assertPushedOn('system', RegenerateSupplierReportPdfJob::class);
    Queue::assertPushed(RegenerateSupplierReportPdfJob::class, fn (RegenerateSupplierReportPdfJob $job): bool => $job->delay !== null && $job->afterCommit === true);
});

it('el job es de cola, único hasta procesarse y está programado a diario', function (): void {
    $job = new RegenerateSupplierReportPdfJob;
    $schedule = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php');

    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and($job->timeout)->toBe(300)
        ->and($schedule)->toContain("Schedule::job(new RegenerateSupplierReportPdfJob, 'system')->dailyAt('5:30');")
        ->and($provider)->toContain('Supplier::observe(SupplierReportPdfObserver::class);');
});
