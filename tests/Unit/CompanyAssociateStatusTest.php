<?php

declare(strict_types=1);

use App\Enums\CompanyAssociateStatus;
use App\Support\Companies\CompanyAssociateRegistrar;
use App\Support\Companies\CompanyAssociateStatusManager;
use App\Support\Companies\CompanyAssociateVoucherIlsUpdater;

it('define los estatus de asociados de nuevos negocios', function (): void {
    expect(CompanyAssociateStatus::cases())->toHaveCount(3)
        ->and(CompanyAssociateStatus::ActivoSinVaucherIls->value)->toBe('ACTIVO-SIN-VAUCHER-ILS')
        ->and(CompanyAssociateStatus::Activo->value)->toBe('ACTIVO')
        ->and(CompanyAssociateStatus::Anulado->value)->toBe('ANULADO')
        ->and(CompanyAssociateStatus::ActivoSinVaucherIls->label())->toBe('Activo sin voucher ILS')
        ->and(CompanyAssociateStatus::Activo->canBeAnnulled())->toBeTrue()
        ->and(CompanyAssociateStatus::ActivoSinVaucherIls->canBeAnnulled())->toBeFalse()
        ->and(CompanyAssociateStatus::Anulado->consumesRegistrationDay())->toBeFalse()
        ->and(CompanyAssociateStatus::options())->toHaveKeys([
            'ACTIVO-SIN-VAUCHER-ILS',
            'ACTIVO',
            'ANULADO',
        ]);
});

it('el registro publico asigna estatus activo sin voucher ils por defecto', function (): void {
    $livewire = file_get_contents(dirname(__DIR__, 2).'/app/Livewire/CompanyAssociateRegistration.php');

    expect($livewire)
        ->toContain('CompanyAssociateStatusManager::defaultStatus()')
        ->toContain("'status' =>");

    expect(CompanyAssociateStatusManager::defaultStatus())
        ->toBe(CompanyAssociateStatus::ActivoSinVaucherIls);
});

it('al guardar voucher ils marca el asociado como activo', function (): void {
    $voucher = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateVoucherIlsUpdater.php');
    $manager = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateStatusManager.php');

    expect($voucher)
        ->toContain('CompanyAssociateStatusManager::markActiveAfterVoucherIls');

    expect($manager)
        ->toContain('markActiveAfterVoucherIls')
        ->toContain('CompanyAssociateStatus::Activo')
        ->toContain('CompanyAssociateStatus::Anulado');

    expect(method_exists(CompanyAssociateVoucherIlsUpdater::class, 'save'))->toBeTrue();
});

it('la anulacion exige razon y solo aplica a asociados activos', function (): void {
    $manager = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateStatusManager.php');
    $actions = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Actions/CompanyAssociatesTableActions.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Tables/CompanyAssociatesTable.php');
    $model = file_get_contents(dirname(__DIR__, 2).'/app/Models/CompanyAssociate.php');
    $registrar = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateRegistrar.php');
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_11_101658_add_status_and_annulment_fields_to_company_associates_table.php');

    expect(CompanyAssociateStatusManager::ANNULMENT_REASON_MIN_LENGTH)->toBe(10);

    expect($manager)
        ->toContain('function annul')
        ->toContain('annulment_reason')
        ->toContain('annulled_at')
        ->toContain('days_returned')
        ->toContain('AUDIT_BUSINESS_COMPANY_ASSOCIATE_ANNULLED')
        ->toContain('canBeAnnulled');

    expect($actions)
        ->toContain('annulAssociate')
        ->toContain('Anular asociado')
        ->toContain('annulment_reason')
        ->toContain('CompanyAssociateStatusManager::annul')
        ->toContain('canBeAnnulled');

    expect($table)
        ->toContain('annulAssociateAction')
        ->toContain("TextColumn::make('status')")
        ->toContain("SelectFilter::make('status')")
        ->toContain('annulment_reason');

    expect($model)
        ->toContain('scopeConsumesRegistrationDay')
        ->toContain('canBeAnnulled')
        ->toContain('isAnnulled')
        ->toContain('CompanyAssociateStatus::class');

    expect($registrar)
        ->toContain('consumesRegistrationDay');

    expect($migration)
        ->toContain('status')
        ->toContain('annulment_reason')
        ->toContain('annulled_at')
        ->toContain('CompanyAssociateStatus::ActivoSinVaucherIls');
});

it('los dias consumidos excluyen asociados anulados', function (): void {
    $registrar = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateRegistrar.php');

    expect($registrar)
        ->toContain('consumesRegistrationDay()')
        ->and(CompanyAssociateRegistrar::DAYS_PER_REGISTRATION)->toBe(1);
});

it('el header de la ficha muestra estatus y razon de anulacion', function (): void {
    $header = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociatePageHeader.php');
    $businessView = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Pages/ViewCompanyAssociate.php');
    $operationsView = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/CompanyAssociates/Pages/ViewCompanyAssociate.php');
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/CompanyAssociates/Schemas/CompanyAssociateInfolist.php');

    expect($header)
        ->toContain('Razón de anulación')
        ->toContain('CompanyAssociateStatus::Anulado')
        ->toContain('annulment_reason');

    expect($businessView)
        ->toContain('CompanyAssociatePageHeader::make');

    expect($operationsView)
        ->toContain('CompanyAssociatePageHeader::make');

    expect($infolist)
        ->toContain('annulmentSection')
        ->toContain('Razón de la anulación')
        ->not->toContain('Resumen del asociado');
});
