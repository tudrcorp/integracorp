<?php

declare(strict_types=1);

it('el aviso a analistas de la pwa corre en cola y genera el pdf de una hoja', function (): void {
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/NotifyAnalystsOfStorefrontIndividualQuoteJob.php');
    $creator = file_get_contents(dirname(__DIR__, 2).'/app/Support/Storefront/StorefrontQuoteCreator.php');

    expect($job)
        ->toContain('implements ShouldBeUnique, ShouldQueue')
        ->toContain('StorefrontQuotePdf::ensure')
        ->toContain('NotificationController::createdIndividualQuote')
        ->toContain('public string $code')
        ->toContain('public string $agentLabel')
        ->and($creator)->toContain('NotifyAnalystsOfStorefrontIndividualQuoteJob::dispatch')
        ->and($creator)->not->toContain('NotificationController::createdIndividualQuote');
});
