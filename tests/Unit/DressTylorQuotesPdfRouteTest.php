<?php

declare(strict_types=1);

it('usa una ruta de PDF definida para la vista previa de cotizaciones', function () {
    $path = dirname(__DIR__, 2) . '/app/Filament/Business/Resources/DressTylorQuotes/Tables/DressTylorQuotesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("'pdfPreviewUrl' => route('business.dress-tylor-quotes.pdf'")
        ->toContain("'preview' => 1")
        ->toContain("'pdfDownloadUrl' => route('business.dress-tylor-quotes.pdf'");
});

it('la ruta de pdf maneja preview inline por query param', function () {
    $path = dirname(__DIR__, 2) . '/routes/web.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('business/dress-tylor-quotes/{record}/pdf')
        ->toContain("request()->boolean('preview')")
        ->toContain('generateInlinePdfFromQuoteStructure')
        ->toContain('AUDIT_BUSINESS_DRESS_TYLOR_QUOTE_PDF_VIEWED')
        ->toContain('AUDIT_BUSINESS_DRESS_TYLOR_QUOTE_PDF_DOWNLOADED')
        ->toContain('AUDIT_BUSINESS_DRESS_TYLOR_QUOTE_PDF_FAILED')
        ->toContain('SecurityAudit::log');
});
