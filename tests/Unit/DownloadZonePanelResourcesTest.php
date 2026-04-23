<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\DownloadZones\DownloadZoneResource as AdministrationDownloadZoneResource;
use App\Filament\Administration\Resources\DownloadZones\Schemas\DownloadZoneForm as AdministrationDownloadZoneForm;
use App\Filament\Operations\Resources\DownloadZones\DownloadZoneResource as OperationsDownloadZoneResource;
use App\Filament\Operations\Resources\DownloadZones\Schemas\DownloadZoneForm as OperationsDownloadZoneForm;
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
