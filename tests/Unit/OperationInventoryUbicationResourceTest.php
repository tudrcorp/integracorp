<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationInventoryUbications\OperationInventoryUbicationResource;
use App\Models\OperationInventoryUbication;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\UserFormPermissionOptions;

it('configura el resource de almacenes en el grupo de inventario', function () {
    expect(OperationInventoryUbicationResource::getNavigationLabel())->toBe('Almacenes')
        ->and(OperationInventoryUbicationResource::getNavigationGroup())->toBe('INVENTARIO DIAGNOMOVIL')
        ->and(OperationInventoryUbicationResource::getNavigationSort())->toBe(1)
        ->and(OperationInventoryUbicationResource::getModelLabel())->toBe('Almacén')
        ->and(OperationInventoryUbicationResource::getPluralModelLabel())->toBe('Almacenes');
});

it('registra el permiso de navegación almacenes', function () {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(
        OperationInventoryUbicationResource::class
    ))->toBe(['almacenes']);

    $aliases = UserFormPermissionOptions::navToLegacySlugAliases();

    expect($aliases['operationinventoryubicationresource'] ?? null)->toBe(['almacenes']);
});

it('configura OperationInventoryUbicationsTable con UX de catálogo', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryUbications/Tables/OperationInventoryUbicationsTable.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain("->heading('Almacenes'")
        ->toContain("->defaultSort('name'")
        ->toContain("TextColumn::make('name')")
        ->toContain("TextColumn::make('state.definition')")
        ->toContain("TextColumn::make('address')")
        ->toContain("TextColumn::make('is_active')")
        ->toContain("->label('Estatus')")
        ->toContain("SelectFilter::make('state_id'")
        ->toContain("TernaryFilter::make('is_active'")
        ->toContain("ViewAction::make()->label('Ver')");

    $form = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryUbications/Schemas/OperationInventoryUbicationForm.php'
    );
    $infolist = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryUbications/Schemas/OperationInventoryUbicationInfolist.php'
    );

    expect($form)
        ->toContain('SECTION_CARD')
        ->toContain('INNER_CARD')
        ->toContain('TABS_CONTAINER')
        ->toContain("Tabs::make('operationInventoryUbicationFormTabs')")
        ->toContain("Tab::make('Información principal')")
        ->toContain("Fieldset::make('Identificación del almacén')")
        ->toContain("Fieldset::make('Ubicación')")
        ->toContain("Fieldset::make('Estatus')")
        ->toContain("Select::make('state_id')")
        ->toContain("->label('Estado')")
        ->toContain("Select::make('is_active')")
        ->toContain("->label('Estatus')")
        ->toContain('prefixIcon')
        ->toContain("'Activo'")
        ->toContain("'Inactivo'");

    expect($infolist)
        ->toContain("TextEntry::make('state.definition')")
        ->toContain("->label('Estado')")
        ->toContain("TextEntry::make('is_active')")
        ->toContain("->label('Estatus')");
});

it('usa AuthorizesDepartmentNavigation en el resource de almacenes', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationInventoryUbications/OperationInventoryUbicationResource.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain('AuthorizesDepartmentNavigation');
});

it('define fillable, cast y relación del modelo de almacenes', function () {
    $model = new OperationInventoryUbication;
    $path = dirname(__DIR__, 2).'/app/Models/OperationInventoryUbication.php';
    $contents = file_get_contents($path);

    expect($model->getFillable())->toEqualCanonicalizing([
        'name',
        'address',
        'state_id',
        'is_active',
        'created_by',
    ])
        ->and($model->getCasts())->toHaveKey('is_active', 'boolean')
        ->and($contents)->toContain('function state(): BelongsTo')
        ->and($contents)->toContain('function operationInventories(): HasMany');
});
