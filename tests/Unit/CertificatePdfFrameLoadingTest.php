<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('el visor del PDF muestra un aviso de carga que se retira al cargar el documento', function (): void {
    $html = view('filament.business.resources.affiliations.pages.partials.certificate-pdf-frame', [
        'src' => '/business/certificados-afiliacion/1/pdf',
    ])->render();

    expect($html)
        ->toContain('x-on:load="loaded = true"')
        ->toContain('x-show="! loaded"')
        ->toContain('role="status"')
        ->toContain('Preparando el PDF…')
        ->toContain('/business/certificados-afiliacion/1/pdf#view=FitH')
        ->toContain('Está tardando más de lo normal.')
        ->toContain('Abrir el PDF en otra pestaña')
        ->not->toContain('loading="lazy"');
});

it('las dos vistas previas del generador usan el visor con aviso de carga', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/resources/affiliations/pages/certificate-generator.blade.php');

    expect(substr_count($view, "partials.certificate-pdf-frame'"))->toBe(2)
        ->and($view)->not->toContain('<iframe');
});
