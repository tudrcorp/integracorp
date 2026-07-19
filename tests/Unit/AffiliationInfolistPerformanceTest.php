<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\Affiliations\Pages\ViewAffiliation as AdministrationViewAffiliation;
use App\Filament\Business\Resources\Affiliations\Concerns\OptimizesAffiliationInfolistPerformance;
use App\Filament\Business\Resources\Affiliations\Pages\ViewAffiliation as BusinessViewAffiliation;
use App\Filament\Business\Resources\Affiliations\Schemas\AffiliationInfolist;
use Filament\Schemas\Schema;

it('usa tabs Livewire para renderizar solo el tab activo en el infolist individual', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("->livewireProperty('affiliationInfolistTab')")
        ->toContain('AffiliationInfolistTab::RESUMEN')
        ->toContain('AffiliationInfolistTab::AFILIADOS')
        ->toContain('AffiliationInfolistTab::RENOVACIONES')
        ->toContain('AffiliationInfolistTab::EXPEDIENTE')
        ->toContain('AffiliationInfolistTab::OBSERVACIONES')
        ->not->toContain('->persistTab()');
});

it('aplica la optimizacion de carga en las vistas business y administracion', function (): void {
    expect(class_uses_recursive(BusinessViewAffiliation::class))
        ->toContain(OptimizesAffiliationInfolistPerformance::class);

    expect(class_uses_recursive(AdministrationViewAffiliation::class))
        ->toContain(OptimizesAffiliationInfolistPerformance::class);
});

it('diferre las relaciones pesadas al abrir cada tab del infolist individual', function (): void {
    $businessView = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Pages/ViewAffiliation.php');
    $adminView = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Pages/ViewAffiliation.php');
    $concern = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Concerns/OptimizesAffiliationInfolistPerformance.php');

    expect($businessView)
        ->toContain('OptimizesAffiliationInfolistPerformance')
        ->not->toContain('protected function resolveRecord')
        ->not->toContain("'affiliates.businessLine:id,definition'")
        ->not->toContain("'renovationHistories.plan'");

    expect($adminView)
        ->toContain('OptimizesAffiliationInfolistPerformance')
        ->not->toContain('protected function resolveRecord')
        ->not->toContain("'renovationHistories.previousPlan'");

    expect($concern)
        ->toContain('affiliationLightRelations')
        ->toContain('affiliationHeavyRelationsByTab')
        ->toContain('updatedAffiliationInfolistTab')
        ->toContain('loadMissing')
        ->toContain('loadCount(\'renovationHistories\')')
        ->toContain("'affiliates.businessLine:id,definition'")
        ->toContain("'affiliationDocuments'")
        ->toContain("'affiliationObservations.createdBy:id,name,email'")
        ->toContain("'renovationHistories.plan'");
});

it('sigue configurando el schema del infolist individual', function (): void {
    $schema = AffiliationInfolist::configure(Schema::make());

    expect($schema)->toBeInstanceOf(Schema::class);
});
