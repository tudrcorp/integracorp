<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\DownloadZones\DownloadZoneResource as AdministrationDownloadZoneResource;
use App\Filament\Administration\Resources\DownloadZones\Schemas\DownloadZoneForm as AdministrationDownloadZoneForm;
use App\Filament\Agents\Resources\DownloadZones\DownloadZoneResource as AgentsDownloadZoneResource;
use App\Filament\Business\Resources\DownloadZones\Schemas\DownloadZoneForm as BusinessDownloadZoneForm;
use App\Filament\General\Resources\DownloadZones\DownloadZoneResource as GeneralDownloadZoneResource;
use App\Filament\Master\Resources\DownloadZones\DownloadZoneResource as MasterDownloadZoneResource;
use App\Filament\Operations\Resources\DownloadZones\DownloadZoneResource as OperationsDownloadZoneResource;
use App\Filament\Operations\Resources\DownloadZones\Schemas\DownloadZoneForm as OperationsDownloadZoneForm;
use App\Support\DownloadZoneDocumentDownloader;
use App\Support\Filament\DownloadZoneUploadedAtField;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

uses(Tests\TestCase::class);

it('expone recurso y formulario de zona de descarga en operaciones', function (): void {
    expect(class_exists(OperationsDownloadZoneResource::class))->toBeTrue();
    $schema = Schema::make();
    expect(OperationsDownloadZoneForm::configure($schema))->toBeInstanceOf(Schema::class);
});

it('expone recurso y formulario de zona de descarga en administración', function (): void {
    expect(class_exists(AdministrationDownloadZoneResource::class))->toBeTrue();
    $schema = Schema::make();
    expect(AdministrationDownloadZoneForm::configure($schema))->toBeInstanceOf(Schema::class);
});

it('CreateDownloadZone redirige al índice tras crear en paneles con alta', function (string $panel): void {
    $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/DownloadZones/Pages/CreateDownloadZone.php";
    $src = file_get_contents($path);

    expect($src)->toContain('protected function getRedirectUrl(): string')
        ->and($src)->toContain("::getUrl('index')");
})->with(['Business', 'Operations', 'Administration', 'Marketing']);

it('expone recurso de documentos en paneles agente, master y general solo lectura', function (): void {
    expect(class_exists(AgentsDownloadZoneResource::class))->toBeTrue();
    expect(class_exists(MasterDownloadZoneResource::class))->toBeTrue();
    expect(class_exists(GeneralDownloadZoneResource::class))->toBeTrue();
    expect(AgentsDownloadZoneResource::canCreate())->toBeFalse();
    expect(MasterDownloadZoneResource::canCreate())->toBeFalse();
    expect(GeneralDownloadZoneResource::canCreate())->toBeFalse();
    expect(class_exists(DownloadZoneDocumentDownloader::class))->toBeTrue();
});

it('formatea la fecha y hora de subida del documento', function (): void {
    expect(DownloadZoneUploadedAtField::format(null))->toBe('—')
        ->and(DownloadZoneUploadedAtField::format(Carbon::parse('2026-03-15 14:30:00', config('app.timezone'))))
        ->toBe('15/03/2026 14:30');
});

it('muestra la fecha de subida en el formulario de negocios al editar', function (): void {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/DownloadZones/Schemas/DownloadZoneForm.php'
    );

    expect($source)->toContain('DownloadZoneUploadedAtField::make()')
        ->and(BusinessDownloadZoneForm::configure(Schema::make()))->toBeInstanceOf(Schema::class)
        ->and(DownloadZoneUploadedAtField::make()->getName())->toBe('document_uploaded_at');
});
