<?php

declare(strict_types=1);

it('incluye mejoras de UX en las tablas de cotizaciones del panel business', function (): void {
    $individualPath = __DIR__.'/../../app/Filament/Business/Resources/IndividualQuotes/Tables/IndividualQuotesTable.php';
    $corporatePath = __DIR__.'/../../app/Filament/Business/Resources/CorporateQuotes/Tables/CorporateQuotesTable.php';

    $individual = file_get_contents($individualPath);
    $corporate = file_get_contents($corporatePath);

    expect($individual)->not->toBeFalse()
        ->toContain('emptyStateHeading')
        ->toContain('paginationPageOptions')
        ->toContain('deferFilters(false)')
        ->toContain('modifyQueryUsing')
        ->toContain('SecurityAudit::log')
        ->toContain('AUDIT_BUSINESS_INDIVIDUAL_QUOTE_FORWARD_SENT')
        ->toContain('AUDIT_BUSINESS_INDIVIDUAL_QUOTE_PDF_DOWNLOADED')
        ->toContain('AUDIT_BUSINESS_INDIVIDUAL_QUOTE_STATUS_UPDATED')
        ->toContain("Action::make('preview')")
        ->toContain("->label('Vista Previa')")
        ->toContain('IndividualQuotePdf::previewUrl')
        ->not->toContain('dd($th)');

    expect($corporate)->not->toBeFalse()
        ->toContain('emptyStateHeading')
        ->toContain('paginationPageOptions')
        ->toContain('deferFilters(false)')
        ->toContain('modifyQueryUsing')
        ->toContain('SecurityAudit::log')
        ->toContain('AUDIT_BUSINESS_CORPORATE_QUOTE_DATA_UPLOADED')
        ->toContain('AUDIT_BUSINESS_CORPORATE_QUOTE_FORWARD_SENT')
        ->toContain('AUDIT_BUSINESS_CORPORATE_QUOTE_PDF_DOWNLOADED')
        ->toContain('AUDIT_BUSINESS_CORPORATE_QUOTE_INTERACTIVE_LINK_EMAIL_SENT')
        ->toContain('AUDIT_BUSINESS_CORPORATE_QUOTE_OBSERVATION_ADDED')
        ->not->toContain('dd($th)');
});
