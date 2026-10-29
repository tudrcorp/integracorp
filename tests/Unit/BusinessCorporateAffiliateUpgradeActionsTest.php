<?php

declare(strict_types=1);

function corporateUpgradeSource(string $relativePath): string
{
    return file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);
}

function corporateAffiliatesManagerSource(): string
{
    return corporateUpgradeSource('app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php');
}

it('expone agregar y quitar upgrade por fila y en lote', function (): void {
    expect(corporateAffiliatesManagerSource())
        ->toContain('$this->addUpgradeWithVoucherAction(fn (): bool => self::userCanManageUpgrades()),')
        ->toContain("Action::make('remove_upgrades')")
        ->toContain('$this->addUpgradeWithVoucherBulkAction(fn (): bool => self::userCanManageUpgrades()),')
        ->toContain("BulkAction::make('remove_upgrade_bulk')")
        ->toContain('use AssignsUpgradesWithIlsVoucher;')
        ->toContain('CorporateAffiliateUpgradeManager::add($this->getOwnerRecord(), $affiliateIds, [$coverageId], null, $vouchers)')
        ->toContain('CorporateAffiliateUpgradeManager::preview($this->getOwnerRecord(), $affiliateIds, $coverageId)')
        ->toContain('CorporateAffiliateUpgradeManager::deactivate($owner, $upgradeIds)');
});

it('las acciones de upgrade se autorizan con el permiso granular, no con is_business_admin', function (): void {
    $source = corporateAffiliatesManagerSource();

    expect(substr_count($source, 'fn (): bool => self::userCanManageUpgrades()'))->toBe(3)
        ->and($source)->toContain('BusinessFilamentActionPermissionRegistry::MANAGE_CORPORATE_AFFILIATE_UPGRADES')
        ->toContain("->hidden(fn (\$record) => \$record->status == 'INACTIVO' || \$record->status == 'EXCLUIDO'),")
        ->not->toContain('Auth::user()->is_business_admin != 1)');
});

it('las acciones históricas siguen reservadas a business admin', function (): void {
    expect(substr_count(corporateAffiliatesManagerSource(), 'self::userIsBusinessAdmin()'))->toBeGreaterThanOrEqual(4);
});

it('la tabla muestra el detalle de upgrades sin N+1', function (): void {
    expect(corporateAffiliatesManagerSource())
        ->toContain('->withActiveUpgradesTotal()')
        ->toContain("'activeUpgrades',")
        ->toContain('->description(fn (AffiliateCorporate $record): ?string => self::upgradesBreakdown($record))')
        ->toContain('->tooltip(fn (AffiliateCorporate $record): ?string => self::upgradesTooltip($record))');
});

it('reasignar plan conserva los upgrades del afiliado', function (): void {
    expect(corporateAffiliatesManagerSource())
        ->toContain("'fee' => CorporateAffiliatePlanSynchronizer::expectedFeeFor(\$record, \$plans),");
});

it('la renovación corporativa recotiza los upgrades por edad al recalcular', function (): void {
    expect(corporateUpgradeSource('app/Jobs/PrepareAffiliationCorporateRenovations.php'))
        ->toContain('->withActiveUpgradesTotal()')
        ->toContain("round(\$amounts['annual_fee'] + UpgradeRenewalRepricer::projectedTotal(\$affiliate, \$today), 2)");

    $accept = corporateUpgradeSource('app/Services/AcceptAffiliationCorporateRenovationsService.php');

    expect(substr_count($accept, "\$this->annualFeeWithUpgrades(\$affiliate, (float) \$amounts['annual_fee'], \$acceptanceDate)"))->toBe(2)
        ->and($accept)->toContain('UpgradeRenewalRepricer::reprice($affiliate, $acceptanceDate)')
        ->and($accept)->toContain('->withActiveUpgradesTotal()');
});

it('el modal toma el upgrade del catálogo: ya no se escriben nombre ni monto a mano', function (): void {
    expect(corporateAffiliatesManagerSource())
        ->toContain('CorporateAffiliateUpgradeManager::activeAssignmentIdsForUpgrades(')
        ->not->toContain("Repeater::make('upgrades')")
        ->not->toContain('catalogPrices()');
});

it('el aviso de cobro corporativo imprime las líneas de upgrade si vienen', function (): void {
    expect(corporateUpgradeSource('resources/views/documents/aviso-de-cobro-corporativo.blade.php'))
        ->toContain("@foreach (\$data['upgrades'] ?? [] as \$upgrade)")
        ->toContain('UPGRADE: {{ $upgrade[\'name\'] }}');
});
