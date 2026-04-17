<?php

declare(strict_types=1);

it('registra hooks de auditoría para cotizador y cotizaciones', function (): void {
    $providerPath = dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php';
    $contents = file_get_contents($providerPath);

    expect($contents)
        ->toContain('registerQuoteResourcesSecurityAudits')
        ->and($contents)->toContain('CorporateQuoteRequest::created')
        ->and($contents)->toContain('CorporateQuoteRequest::updated')
        ->and($contents)->toContain('CorporateQuoteRequest::deleted')
        ->and($contents)->toContain('IndividualQuote::created')
        ->and($contents)->toContain('IndividualQuote::updated')
        ->and($contents)->toContain('IndividualQuote::deleted')
        ->and($contents)->toContain('CorporateQuote::created')
        ->and($contents)->toContain('CorporateQuote::updated')
        ->and($contents)->toContain('CorporateQuote::deleted')
        ->and($contents)->toContain('DressTylorQuote::created')
        ->and($contents)->toContain('DressTylorQuote::updated')
        ->and($contents)->toContain('DressTylorQuote::deleted')
        ->and($contents)->toContain("quoteAction('COTIZADOR_CREATED')")
        ->and($contents)->toContain("quoteAction('INDIVIDUAL_QUOTE_CREATED')")
        ->and($contents)->toContain("quoteAction('CORPORATE_QUOTE_CREATED')")
        ->and($contents)->toContain("quoteAction('DRESS_TYLOR_QUOTE_CREATED')");
});

it('expone categoría de cotizador y cotizaciones en trazas de seguridad', function (): void {
    $tablePath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/SystemAuditTraces/Tables/SystemAuditTracesTable.php';
    $contents = file_get_contents($tablePath);

    expect($contents)
        ->toContain("'quotes' => 'Cotizador y Cotizaciones'")
        ->and($contents)->toContain('AUDIT_%_COTIZADOR_%')
        ->and($contents)->toContain('AUDIT_%_INDIVIDUAL_QUOTE_%')
        ->and($contents)->toContain('AUDIT_%_CORPORATE_QUOTE_%')
        ->and($contents)->toContain('AUDIT_%_DRESS_TYLOR_QUOTE_%');
});
