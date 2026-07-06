<?php

declare(strict_types=1);

it('expone la accion masiva de pago multiple en afiliaciones corporativas administration', function (): void {
    $root = dirname(__DIR__, 2);
    $table = file_get_contents($root.'/app/Filament/Administration/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/AffiliationCorporateController.php');

    expect($table)
        ->toContain("BulkAction::make('pay_multiple_affiliation_corporates')")
        ->toContain('PAGO MULTIPLE DE AFILIACIONES CORPORATIVAS')
        ->toContain('AffiliationCorporateController::uploadPaymentMultipleAffiliationCorporates')
        ->toContain('AUDIT_ADMIN_AFFILIATION_CORPORATE_BULK_PAYMENT_VOUCHER_UPLOADED')
        ->toContain('paymentTotalPreviewHtml')
        ->toContain("Action::make('upload')")
        ->toContain('base_total_amount')
        ->toContain('payment_adjustment_percentage')
        ->toContain('payment_total_preview')
        ->toContain('applyPaymentTotalPercentageAdjustment')
        ->toContain('Mail::to($info[\'email\'])->send(new UploadPayment($info))')
        ->toContain("Action::make('upload_info_ils')")
        ->toContain("Action::make('change_status')");

    expect($controller)->toContain('function uploadPaymentMultipleAffiliationCorporates');
});
