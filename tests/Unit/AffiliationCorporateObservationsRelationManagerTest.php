<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\AffiliationCorporates\AffiliationCorporateResource as AdministrationAffiliationCorporateResource;
use App\Filament\Administration\Resources\AffiliationCorporates\RelationManagers\StatusLogCorporateAffiliationsRelationManager as AdministrationStatusLogCorporateAffiliationsRelationManager;
use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource as BusinessAffiliationCorporateResource;
use App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\StatusLogCorporateAffiliationsRelationManager as BusinessStatusLogCorporateAffiliationsRelationManager;

it('incluye notas y observaciones en afiliaciones corporativas business', function (): void {
    expect(BusinessAffiliationCorporateResource::getRelations())
        ->toContain(BusinessStatusLogCorporateAffiliationsRelationManager::class);

    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/StatusLogCorporateAffiliationsRelationManager.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("protected static ?string \$title = 'Notas y/o Observaciones'")
        ->and($contents)->toContain("->label('Agregar nota')")
        ->and($contents)->toContain("'AGREGO OBSERVACION'")
        ->and($contents)->toContain('AUDIT_BUSINESS_AFFILIATION_CORPORATE_OBSERVATION_ADDED')
        ->and($contents)->not->toContain('EditAction::make()')
        ->and($contents)->toContain('DeleteAction::make()')
        ->and($contents)->not->toContain("TextColumn::make('action')");
});

it('incluye notas y observaciones en afiliaciones corporativas administration', function (): void {
    expect(AdministrationAffiliationCorporateResource::getRelations())
        ->toContain(AdministrationStatusLogCorporateAffiliationsRelationManager::class);

    $path = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/RelationManagers/StatusLogCorporateAffiliationsRelationManager.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("protected static ?string \$title = 'Notas y/o Observaciones'")
        ->and($contents)->toContain("->label('Agregar nota')")
        ->and($contents)->toContain("'AGREGO OBSERVACION'")
        ->and($contents)->toContain('AUDIT_ADMIN_AFFILIATION_CORPORATE_STATUS_UPDATED')
        ->and($contents)->not->toContain('EditAction::make()')
        ->and($contents)->toContain('DeleteAction::make()')
        ->and($contents)->not->toContain("TextColumn::make('action')");
});
