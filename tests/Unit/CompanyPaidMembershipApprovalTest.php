<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\CompanyPaidMemberships\CompanyPaidMembershipResource;
use App\Models\Company;
use App\Models\CompanyPaidMembership;
use App\Models\Sale;
use App\Support\Companies\CompanyPaidMembershipApprovalService;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;

it('resuelve referencia de pago multiple y mono-canal', function (): void {
    $multiple = new CompanyPaidMembership([
        'payment_method' => 'MULTIPLE',
        'reference_payment_ves' => 'VES-1',
        'reference_payment_usd' => 'USD-2',
    ]);

    $usd = new CompanyPaidMembership([
        'payment_method' => 'ZELLE',
        'reference_payment_ves' => 'N/A',
        'reference_payment_usd' => 'REF-USD',
    ]);

    $company = new Company;
    $company->id = 42;

    expect(CompanyPaidMembershipApprovalService::resolveReferencePayment($multiple))
        ->toBe('VES-1-USD-2')
        ->and(CompanyPaidMembershipApprovalService::resolveReferencePayment($usd))
        ->toBe('REF-USD')
        ->and(CompanyPaidMembershipApprovalService::affiliationCodeFor($company))
        ->toBe('NN-42');
});

it('expone cola de aprobación en administración con acciones aprobar y rechazar', function (): void {
    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/CompanyPaidMemberships/CompanyPaidMembershipResource.php');
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyPaidMembershipApprovalService.php');
    $migration = glob(dirname(__DIR__, 2).'/database/migrations/*add_company_id_to_sales_table.php');

    expect($migration)->not->toBeEmpty();

    expect($resource)
        ->toContain('CompanyPaidMembershipApprovalService::approve')
        ->toContain('CompanyPaidMembershipApprovalService::reject')
        ->toContain("navigationLabel = 'Comprobantes Nuevos Negocios'")
        ->toContain("navigationGroup = 'ADMINISTRACIÓN'")
        ->toContain('STATUS_PENDING');

    expect($service)
        ->toContain("SALE_TYPE = 'NUEVOS NEGOCIOS'")
        ->toContain('AUDIT_COMPANY_PAYMENT_APPROVED')
        ->toContain('AUDIT_COMPANY_PAYMENT_REJECTED')
        ->toContain('AUDIT_COMPANY_PAYMENT_APPROVE_FAILED')
        ->toContain('createCommissionPlaceholder')
        ->toContain('lockForUpdate')
        ->toContain('company_id');
});

it('registra permiso de navegacion y tipado de ventas para nuevos negocios', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(CompanyPaidMembershipResource::class))
        ->toContain('ventas')
        ->toContain('comprobantes-nuevos-negocios');

    $saleModel = file_get_contents(dirname(__DIR__, 2).'/app/Models/Sale.php');
    $salesTable = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Tables/SalesTable.php');
    $saleInfolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Schemas/SaleInfolist.php');
    $viewSale = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Pages/ViewSale.php');

    expect($saleModel)
        ->toContain("type === 'NUEVOS NEGOCIOS'")
        ->toContain('paidMembershipCompany')
        ->toContain("'company_id'");

    expect($salesTable)
        ->toContain("'NUEVOS NEGOCIOS' => 'warning'")
        ->toContain('paidMembershipCompany()->delete()');

    expect($saleInfolist)
        ->toContain("'NUEVOS NEGOCIOS' => 'warning'")
        ->toContain('CompanyPaidMembership');

    expect($viewSale)
        ->toContain("'NUEVOS NEGOCIOS'")
        ->toContain('paidMembershipCompany.company');
});

it('resuelve recibo de pago de nuevos negocios desde el modelo Sale', function (): void {
    $sale = new Sale(['type' => 'NUEVOS NEGOCIOS']);

    expect($sale->paidReceiptTableName())->toBe('company_paid_memberships');
});

it('servicio de rechazo valida estados terminales en el codigo', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyPaidMembershipApprovalService.php');

    expect($service)
        ->toContain("STATUS_REJECTED = 'RECHAZADO'")
        ->toContain('No se puede rechazar un comprobante ya aprobado')
        ->toContain('El comprobante está rechazado y no puede aprobarse')
        ->toContain('Rechazo: ');
});
