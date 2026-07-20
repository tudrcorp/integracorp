<?php

declare(strict_types=1);

use App\Filament\Actions\ImportAction;
use App\Filament\Imports\CorporateQuoteDataImporter;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader as CsvReader;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

uses(Tests\TestCase::class);

it('abre csv iso-8859-1 grandes sin fallar el stream filter de charset', function (): void {
    Storage::fake(FileUploadConfiguration::disk());

    $csv = "Apellido,Nombre,Direccion\n";

    for ($i = 0; $i < 50; $i++) {
        $csv .= "García{$i},José{$i},Calle Núñez {$i}\n";
    }

    $latin1Csv = mb_convert_encoding($csv, 'ISO-8859-1', 'UTF-8');
    $filename = 'livewire-file-meta'.base64_encode('poblacion.csv').'-.csv';

    Storage::disk(FileUploadConfiguration::disk())->put(
        FileUploadConfiguration::path($filename, false),
        $latin1Csv,
    );

    $temporaryFile = TemporaryUploadedFile::createFromLivewire($filename);
    $action = ImportAction::make()->importer(CorporateQuoteDataImporter::class);

    $resource = $action->getUploadedFileStream($temporaryFile);

    expect($resource)->not->toBeFalse();

    $reader = CsvReader::from($resource);
    $reader->setHeaderOffset(0);

    expect($reader->getHeader())->toBe(['Apellido', 'Nombre', 'Direccion'])
        ->and(iterator_count($reader->getRecords()))->toBe(50);
});

it('usa el import action personalizado en cotizaciones corporativas del panel business', function (): void {
    $source = file_get_contents(base_path('app/Filament/Business/Resources/CorporateQuotes/RelationManagers/CorporateQuoteDataRelationManager.php'));

    expect($source)->toContain('use App\Filament\Actions\ImportAction;')
        ->and($source)->not->toContain('use Filament\Actions\ImportAction;');
});
