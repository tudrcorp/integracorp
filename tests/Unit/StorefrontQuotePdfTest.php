<?php

declare(strict_types=1);

use App\Enums\PlanPricingMode;
use App\Models\Plan;
use App\Support\IndividualQuotePdfLayout;
use App\Support\Storefront\StorefrontQuotePdf;

function storefrontQuotePdfPath(string $path): string
{
    return dirname(__DIR__, 2).'/'.ltrim($path, '/');
}

it('la pwa fuerza la hoja de estructura aunque el plan sea inicial ideal o especial', function (): void {
    $paquete = new Plan(['pricing_mode' => PlanPricingMode::Paquete]);
    $coberturas = new Plan(['pricing_mode' => PlanPricingMode::Coberturas]);
    $inicialSinModo = new Plan;
    $inicialSinModo->id = 1;

    expect(StorefrontQuotePdf::layoutFor($paquete))->toBe(IndividualQuotePdfLayout::EstructuraPaquete)
        ->and(StorefrontQuotePdf::layoutFor($coberturas))->toBe(IndividualQuotePdfLayout::Estructura)
        ->and(StorefrontQuotePdf::layoutFor($inicialSinModo))->toBe(IndividualQuotePdfLayout::EstructuraPaquete)
        ->and(IndividualQuotePdfLayout::resolve(1))->toBe(IndividualQuotePdfLayout::Inicial)
        ->and(IndividualQuotePdfLayout::resolve(2))->toBe(IndividualQuotePdfLayout::Ideal)
        ->and(IndividualQuotePdfLayout::resolve(3))->toBe(IndividualQuotePdfLayout::Especial)
        ->and(IndividualQuotePdfLayout::usesPlanStructure(StorefrontQuotePdf::layoutFor($coberturas)))->toBeTrue();
});

it('el pdf de la pwa es una sola hoja dinamica sin portada ni condiciones extra', function (): void {
    $document = file_get_contents(storefrontQuotePdfPath('resources/views/documents/storefront-quote.blade.php'));
    $fullProposal = file_get_contents(storefrontQuotePdfPath('resources/views/documents/propuesta-economica.blade.php'));
    $creator = file_get_contents(storefrontQuotePdfPath('app/Support/Storefront/StorefrontQuoteCreator.php'));
    $generator = file_get_contents(storefrontQuotePdfPath('app/Support/Storefront/StorefrontQuotePdf.php'));
    $utils = file_get_contents(storefrontQuotePdfPath('app/Http/Controllers/UtilsController.php'));
    $proposal = file_get_contents(storefrontQuotePdfPath('resources/views/livewire/volt/app/quote-proposal.blade.php'));

    expect($document)
        ->toContain('planes-cotizacion-estructura')
        ->and($document)->not->toContain('portada-cotizacion-individual')
        ->and($document)->not->toContain('propuesta-economica-page-2')
        ->and($document)->not->toContain('propuesta-economica-page-4')
        ->and($document)->not->toContain('propuesta-economica-plan-especial')
        ->and($document)->not->toContain('planes-cotizacion-individual')
        ->and($document)->toContain("'compact' => true")
        ->and($document)->toContain("'showConditions' => false")
        ->and($document)->toContain("'storefrontFooter' => true")
        ->and($fullProposal)->toContain('portada-cotizacion-individual')
        ->and($fullProposal)->toContain('propuesta-economica-page-2')
        ->and($fullProposal)->toContain('propuesta-economica-page-4')
        ->and($creator)->toContain('storeDetailsIndividualQuote')
        ->and($creator)->not->toContain('IndividualQuotePdfGenerator')
        ->and($creator)->not->toContain('generateForQuote')
        ->and($generator)->toContain("loadView('documents.storefront-quote'")
        ->and($generator)->not->toContain('propuesta-economica')
        ->and($proposal)->toContain('StorefrontQuotePdf::ensure')
        ->and($utils)->toContain('bool $generatePdf = true');
});

it('el pie de la pwa lleva contacto y qr de metodos de pago', function (): void {
    $pagina = file_get_contents(storefrontQuotePdfPath('resources/views/livewire/planes-cotizacion-estructura.blade.php'));
    $tabla = file_get_contents(storefrontQuotePdfPath('resources/views/livewire/partials/quote-pdf-benefits-table.blade.php'));
    $documento = file_get_contents(storefrontQuotePdfPath('app/Support/Storefront/StorefrontPaymentMethodsDocument.php'));

    expect($pagina)
        ->toContain('storefront-footer')
        ->toContain('storefront-footer-slot')
        ->toContain('page-break-inside: avoid')
        ->toContain('position: absolute')
        ->toContain('text-align: center')
        ->not->toContain('storefront-footer-spacer')
        ->not->toContain('height: 277mm')
        ->toContain('pdf-root')
        ->toContain('Cotización generada por Integracorp-pwa')
        ->toContain('0424-222-0056')
        ->toContain('0424227-1498')
        ->toContain('comercial@tudrencasa.com')
        ->toContain('@tudrencasa')
        ->toContain('storefront-footer__qr')
        ->toContain('Haz clic aquí')
        ->toContain('o escanea')
        ->toContain('title="Descargar métodos de pago"')
        ->and($tabla)->toContain("\$compact ? '5.5pt'")
        ->and($documento)->toContain('pwa-documents')
        ->and($documento)->toContain('Metodos de pago');
});

it('el pdf de la pwa cabe en una sola hoja sin spacer ni altura fija', function (): void {
    $pagina = file_get_contents(storefrontQuotePdfPath('resources/views/livewire/planes-cotizacion-estructura.blade.php'));

    expect($pagina)
        ->toContain('page-break-inside: avoid')
        ->toContain('padding-top: 16mm')
        ->not->toContain('storefront-footer-spacer')
        ->not->toContain('.is-compact .page-frame')
        ->not->toContain('height: 277mm')
        ->not->toContain('height: 54mm')
        ->not->toContain('height: 68mm');
});

it('el numero de control se extrae del codigo de cotizacion', function (): void {
    expect(StorefrontQuotePdf::controlNumber('COT-IND-0099'))->toBe('0099')
        ->and(StorefrontQuotePdf::controlNumber('COT-CORP-12'))->toBe('12')
        ->and(StorefrontQuotePdf::controlNumber('ABC'))->toBe('ABC');
});
