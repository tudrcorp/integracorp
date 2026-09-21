<?php

declare(strict_types=1);

use App\Support\PlanGenerators\PlanGeneratorPreviewBuilder;

uses(Tests\TestCase::class);

/**
 * La celda de la matriz de beneficios tiene tres estados excluyentes. El que
 * cambió es el del beneficio con tope: antes mostraba el check y el monto
 * debajo, y el check sobraba porque el monto ya dice que está cubierto.
 *
 * La regla vive en el builder y no en las plantillas porque la misma celda se
 * pinta en dos superficies: el PDF de la cotización y la vista previa del panel.
 */
it('muestra solo el monto cuando el beneficio tiene tope', function (): void {
    expect(PlanGeneratorPreviewBuilder::benefitCellDisplay(true, 1000))->toBe('amount')
        ->and(PlanGeneratorPreviewBuilder::benefitCellDisplay(true, '1000.00'))->toBe('amount')
        ->and(PlanGeneratorPreviewBuilder::benefitCellDisplay(true, 0.5))->toBe('amount');
});

it('muestra el check cuando el beneficio está incluido sin tope', function (): void {
    expect(PlanGeneratorPreviewBuilder::benefitCellDisplay(true, null))->toBe('check')
        ->and(PlanGeneratorPreviewBuilder::benefitCellDisplay(true, ''))->toBe('check');
});

it('trata un límite en cero como sin tope y no como monto', function (): void {
    // Un US$ 0.00 impreso en la cotización no significaría nada para el cliente.
    expect(PlanGeneratorPreviewBuilder::benefitCellDisplay(true, 0))->toBe('check')
        ->and(PlanGeneratorPreviewBuilder::benefitCellDisplay(true, 0.0))->toBe('check')
        ->and(PlanGeneratorPreviewBuilder::benefitCellDisplay(true, '0'))->toBe('check')
        ->and(PlanGeneratorPreviewBuilder::benefitCellDisplay(true, '0.00'))->toBe('check');
});

it('muestra el guion cuando el beneficio no está incluido', function (): void {
    expect(PlanGeneratorPreviewBuilder::benefitCellDisplay(false, null))->toBe('dash')
        // Un monto cargado no incluye el beneficio si la casilla está desmarcada.
        ->and(PlanGeneratorPreviewBuilder::benefitCellDisplay(false, 1000))->toBe('dash');
});

it('nunca dibuja el check junto al monto en el PDF ni en la vista previa', function (): void {
    $pdf = (string) file_get_contents(
        dirname(__DIR__, 2).'/resources/views/documents/partials/plan-generator-plan-body.blade.php',
    );
    $preview = (string) file_get_contents(
        dirname(__DIR__, 2).'/resources/views/filament/business/plan-generators/partials/benefit-cell-status-preview.blade.php',
    );

    foreach (['pdf' => $pdf, 'preview' => $preview] as $plantilla) {
        expect($plantilla)
            ->toContain('benefitCellDisplay')
            // Los tres estados son ramas excluyentes de un mismo if.
            ->toContain("\$display === 'amount'")
            ->toContain("\$display === 'check'");
    }

    // El bloque viejo pintaba el check y colgaba el monto debajo con un <br>.
    expect($pdf)->not->toContain('<br><span class="amount">');
});
