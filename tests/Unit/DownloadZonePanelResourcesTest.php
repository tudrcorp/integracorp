<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\DownloadZones\DownloadZoneResource as AdministrationDownloadZoneResource;
use App\Filament\Administration\Resources\DownloadZones\Schemas\DownloadZoneForm as AdministrationDownloadZoneForm;
use App\Filament\Agents\Resources\DownloadZones\DownloadZoneResource as AgentsDownloadZoneResource;
use App\Filament\General\Resources\DownloadZones\DownloadZoneResource as GeneralDownloadZoneResource;
use App\Filament\Master\Resources\DownloadZones\DownloadZoneResource as MasterDownloadZoneResource;
use App\Filament\Operations\Resources\DownloadZones\DownloadZoneResource as OperationsDownloadZoneResource;
use App\Filament\Operations\Resources\DownloadZones\Schemas\DownloadZoneForm as OperationsDownloadZoneForm;
use App\Support\DownloadZoneDocumentDownloader;
use Filament\Schemas\Schema;

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
