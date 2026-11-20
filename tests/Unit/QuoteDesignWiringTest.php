<?php

declare(strict_types=1);

/**
 * Cableado del diseño de cotización: los generadores lo prueban antes que el microservicio y el
 * generador local, regenerar no le pone diseño a una propuesta vieja, y la vista previa va por
 * una ruta firmada con permiso. Lectura de fuente, sin contenedor.
 */
function quoteDesignSource(string $path): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);
}

it('los generadores individual y corporativo prueban el diseño antes que el microservicio', function (string $path, string $scope): void {
    $source = quoteDesignSource($path);

    $single = strpos($source, "self::attemptDesignedProposal(QuoteDocumentLayout::{$scope}, \$record, [\$planId], \$quoteIsNew)");
    $multiple = strpos($source, "self::attemptDesignedProposal(QuoteDocumentLayout::{$scope}, \$record, \$processedPlanIds, \$quoteIsNew)");
    $serviceSingle = strpos($source, 'QuoteServiceAttempt::generate(');
    $serviceMultiple = strpos($source, 'QuoteServiceAttempt::generateMultiple(');

    expect($single)->not->toBeFalse()
        ->and($multiple)->not->toBeFalse()
        ->and($single)->toBeLessThan($serviceSingle)
        ->and($multiple)->toBeLessThan($serviceMultiple)
        ->and($source)->toContain('class_exists(QuoteDesignedProposal::class)')
        ->and($source)->toContain('QuoteDesignedProposal::generate($scope, $record, $planIds, $quoteIsNew)');
})->with([
    'individual' => ['app/Support/IndividualQuotePdfGenerator.php', 'SCOPE_INDIVIDUAL'],
    'corporativa' => ['app/Support/CorporateQuotePdfGenerator.php', 'SCOPE_CORPORATE'],
]);

it('regenerar una propuesta perdida no la trata como cotización nueva', function (string $path, string $call): void {
    $source = quoteDesignSource($path);
    $method = substr($source, (int) strpos($source, 'function regenerateIfMissing'), 400);

    expect($method)->toContain($call);
})->with([
    'individual' => ['app/Support/IndividualQuotePdfGenerator.php', 'self::generateProposal($record, quoteIsNew: false)'],
    'corporativa' => ['app/Support/CorporateQuotePdfGenerator.php', 'self::generateForQuote($record, quoteIsNew: false)'],
]);

it('la vista previa va por una ruta firmada, autenticada y con permiso', function (): void {
    $routes = quoteDesignSource('routes/web.php');
    $controller = quoteDesignSource('app/Http/Controllers/QuoteDesignPreviewController.php');

    expect($routes)->toContain("Route::get('business/quote-designs/preview/{token}', App\\Http\\Controllers\\QuoteDesignPreviewController::class)")
        ->and($routes)->toContain("->middleware(['web', 'auth', 'signed'])\n    ->name('business.quote-designs.preview')")
        ->and($controller)->toContain('abort_unless(QuoteDesignResource::canAccess(), 403)')
        ->and($controller)->toContain('QuoteDesignPreview::path((int) Auth::id(), $token)');
});

it('la página con diseño respeta las reglas de DomPDF del proyecto', function (): void {
    $view = quoteDesignSource('resources/views/documents/propuesta-plan-disenio.blade.php');

    expect($view)->not->toMatch('/display\s*:\s*(flex|grid)/')
        ->and($view)->toContain('table-layout: fixed')
        ->and($view)->toContain("font-family: 'DejaVu Sans', sans-serif; font-weight: bold; font-size: 10pt; line-height: 1; color: {{ \$c('check') }};\">✓</span>");
});

it('el permiso propio está en el editor de permisos de usuarios', function (): void {
    expect(quoteDesignSource('app/Support/Filament/UserFormPermissionOptions.php'))->toContain("'quotedesignresource' => ['disenio-de-cotizacion']");
});
