<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Upgrades\UpgradeResource;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\UserFormPermissionOptions;

function upgradeWiringSource(string $relativePath): string
{
    return file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);
}

it('el catálogo vive en Negocios → CONFIGURACIÓN con su permiso de menú', function (): void {
    $resource = upgradeWiringSource('app/Filament/Business/Resources/Upgrades/UpgradeResource.php');

    expect($resource)
        ->toContain("protected static string|UnitEnum|null \$navigationGroup = 'CONFIGURACIÓN';")
        ->toContain("protected static ?string \$navigationLabel = 'Upgrades';")
        ->toContain('use AuthorizesDepartmentNavigation;')
        ->and(DepartmentNavigationPermissionRegistry::slugsFor(UpgradeResource::class))->toBe(['gestionar-upgrades'])
        ->and(DepartmentNavigationPermissionRegistry::isSuperAdminOnly(UpgradeResource::class))->toBeFalse()
        ->and(UserFormPermissionOptions::navToLegacySlugAliases()['upgraderesource'] ?? null)->toBe(['gestionar-upgrades']);
});

it('el catálogo nunca borra: no hay acciones de eliminar y guarda por la persistencia', function (): void {
    $table = upgradeWiringSource('app/Filament/Business/Resources/Upgrades/Tables/UpgradesTable.php');
    $create = upgradeWiringSource('app/Filament/Business/Resources/Upgrades/Pages/CreateUpgrade.php');
    $edit = upgradeWiringSource('app/Filament/Business/Resources/Upgrades/Pages/EditUpgrade.php');

    expect($table)
        ->not->toContain('DeleteAction')
        ->not->toContain('DeleteBulkAction')
        ->toContain('UpgradeCatalogPersistence::changeStatus(')
        ->and($create)->toContain('UpgradeCatalogPersistence::save(null, $data)')
        ->and($edit)->toContain('UpgradeCatalogPersistence::save($record, $data)')
        ->and($edit)->toContain('UpgradeCatalogPersistence::formState($record)')
        ->and($edit)->not->toContain('DeleteAction');
});

it('el formulario valida el solape de rangos en el campo', function (): void {
    expect(upgradeWiringSource('app/Filament/Business/Resources/Upgrades/Schemas/UpgradeForm.php'))
        ->toContain('UpgradeStructureValidator::overlapMessage($ranges)')
        ->toContain("->gte('age_from')");
});

it('registra el permiso para upgrades de afiliados individuales', function (): void {
    $definition = BusinessFilamentActionPermissionRegistry::all()[BusinessFilamentActionPermissionRegistry::MANAGE_INDIVIDUAL_AFFILIATE_UPGRADES] ?? null;

    expect(BusinessFilamentActionPermissionRegistry::MANAGE_INDIVIDUAL_AFFILIATE_UPGRADES)->toBe('gestionar-upgrades-afiliado-individual')
        ->and($definition)->not->toBeNull()
        ->and($definition['modules'])->toBe(['NEGOCIOS'])
        ->and($definition['group'])->toBe('AFILIACIONES');
});

it('los familiares individuales tienen agregar y quitar upgrade, por fila y en lote, con permiso propio y sin N+1', function (): void {
    $source = upgradeWiringSource('app/Filament/Business/Resources/Affiliations/RelationManagers/AffiliatesRelationManager.php');

    expect($source)
        ->toContain("\$this->addUpgradeWithVoucherAction(fn (Affiliate \$record): bool => self::userCanManageUpgrades() && \$record->status !== 'INACTIVO'),")
        ->toContain("Action::make('remove_upgrades')")
        ->toContain('$this->addUpgradeWithVoucherBulkAction(fn (): bool => self::userCanManageUpgrades()),')
        ->toContain("BulkAction::make('remove_upgrade_bulk')")
        ->toContain('use AssignsUpgradesWithIlsVoucher;')
        ->toContain('IndividualAffiliateUpgradeManager::add($this->getOwnerRecord(), $affiliateIds, [$coverageId], null, $vouchers)')
        ->toContain('IndividualAffiliateUpgradeManager::deactivate($owner, $upgradeIds)')
        ->toContain('BusinessFilamentActionPermissionRegistry::MANAGE_INDIVIDUAL_AFFILIATE_UPGRADES')
        ->toContain('->withActiveUpgradesTotal()')
        ->toContain("'activeUpgrades',")
        ->and(substr_count($source, 'self::userCanManageUpgrades()'))->toBe(4);
});

it('todo recálculo de tarifa individual conserva o recotiza los upgrades', function (): void {
    expect(upgradeWiringSource('app/Support/AffiliationAffiliateFeeCalculator.php'))
        ->toContain("\$annualFee = round((float) \$amounts['annual_fee'] + IndividualAffiliateUpgradeManager::activeTotalFor(\$affiliate), 2);")
        ->and(upgradeWiringSource('app/Support/Affiliations/PlanChange/IndividualAffiliationPlanChanger.php'))
        ->toContain('$annual = round((float) $fee->price + IndividualAffiliateUpgradeManager::activeTotalFor($affiliate), 2);')
        ->and(upgradeWiringSource('app/Support/Affiliations/MergeIndividualAffiliationsService.php'))
        ->toContain("round((float) \$amounts['annual_fee'] + IndividualAffiliateUpgradeManager::activeTotalFor(\$affiliate), 2)")
        ->and(upgradeWiringSource('app/Support/Renovations/RenewalFeeProjection.php'))
        ->toContain('UpgradeRenewalRepricer::projectedTotal($affiliate, $referenceDate)')
        ->and(substr_count(upgradeWiringSource('app/Services/AcceptAffiliationRenovationsService.php'), 'UpgradeRenewalRepricer::reprice($affiliate, $acceptanceDate)'))->toBe(2)
        ->and(substr_count(upgradeWiringSource('app/Support/Filament/Renovations/RenovationManualAcceptancePricing.php'), 'UpgradeRenewalRepricer::projectedTotal($affiliate, $referenceDate)'))->toBe(2);
});

it('agregar upgrade siempre pasa por la vista previa y el voucher obligatorio', function (): void {
    $trait = upgradeWiringSource('app/Filament/Business/Concerns/AssignsUpgradesWithIlsVoucher.php');

    expect($trait)
        ->toContain("View::make('filament.business.upgrades.assignment-preview')")
        ->toContain("->statePath('voucher')")
        ->toContain('UpgradeAssignmentPreview::blockingMessage($preview)')
        ->toContain('public const UPGRADE_BULK_LIMIT = 20;')
        ->toContain("FileUpload::make('document_path')")
        ->toContain('UpgradeIlsVoucher::isCodeTaken($value)');
});
