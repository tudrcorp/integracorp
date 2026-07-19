<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate as AdministrationViewAffiliationCorporate;
use App\Filament\Business\Resources\AffiliationCorporates\Concerns\OptimizesAffiliationCorporateInfolistPerformance;
use App\Filament\Business\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate as BusinessViewAffiliationCorporate;
use App\Filament\Business\Resources\AffiliationCorporates\Schemas\AffiliationCorporateInfolist;

it('usa tabs Livewire para renderizar solo el tab activo en el infolist corporativo', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("->livewireProperty('affiliationCorporateInfolistTab')")
        ->toContain('AffiliationCorporateInfolistTab::AFILIACION')
        ->toContain('AffiliationCorporateInfolistTab::PLANES')
        ->toContain('AffiliationCorporateInfolistTab::EXPEDIENTE')
        ->toContain('AffiliationCorporateInfolistTab::OBSERVACIONES')
        ->not->toContain('->persistTab()')
        ->not->toContain("RepeatableEntry::make('corporateAffiliates')")
        ->not->toContain('corporateAffiliates.businessLine')
        ->not->toContain('corporateAffiliates.businessUnit');
});

it('aplica la optimizacion de carga en las vistas business y administracion', function (): void {
    expect(class_uses_recursive(BusinessViewAffiliationCorporate::class))
        ->toContain(OptimizesAffiliationCorporateInfolistPerformance::class);

    expect(class_uses_recursive(AdministrationViewAffiliationCorporate::class))
        ->toContain(OptimizesAffiliationCorporateInfolistPerformance::class);
});

it('no eager-loadea la poblacion de afiliados al abrir la vista', function (): void {
    $businessView = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Pages/ViewAffiliationCorporate.php');
    $adminView = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/Pages/ViewAffiliationCorporate.php');
    $concern = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Concerns/OptimizesAffiliationCorporateInfolistPerformance.php');

    expect($businessView)
        ->not->toContain('corporateAffiliates.businessLine')
        ->not->toContain('corporateAffiliates.businessUnit');

    expect($adminView)
        ->not->toContain('corporateAffiliates');

    expect($concern)
        ->toContain('affiliationCorporateLightRelations')
        ->toContain('affiliationCorporateHeavyRelationsByTab')
        ->toContain('updatedAffiliationCorporateInfolistTab')
        ->not->toContain("'corporateAffiliates")
        ->toContain("'affiliationCorporatePlans.plan'")
        ->toContain("'affiliationCorporateDocuments'")
        ->toContain("'affiliationCorporateObservations.createdBy:id,name,email'");
});

it('sigue configurando el schema del infolist corporativo', function (): void {
    $schema = AffiliationCorporateInfolist::configure(Filament\Schemas\Schema::make());

    expect($schema)->toBeInstanceOf(Filament\Schemas\Schema::class);
});
