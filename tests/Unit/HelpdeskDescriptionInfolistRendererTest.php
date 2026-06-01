<?php

declare(strict_types=1);

use App\Support\HelpdeskDescriptionInfolistRenderer;
use App\Support\HelpdeskPlainText;

it('renderiza descripcion html sanitizada en tarjeta', function (): void {
    $html = HelpdeskDescriptionInfolistRenderer::format('<p>Error al <strong>cargar</strong> reporte</p>')->toHtml();

    expect($html)
        ->toContain('fi-helpdesk-description-card__prose')
        ->toContain('<strong>cargar</strong>');
});

it('muestra estado vacio cuando no hay descripcion', function (): void {
    expect(HelpdeskDescriptionInfolistRenderer::format(null)->toHtml())
        ->toContain('fi-helpdesk-description-card__empty');
});

it('resume caracteres de la descripcion en texto plano', function (): void {
    expect(HelpdeskDescriptionInfolistRenderer::characterSummary('<p>Hola mundo</p>'))
        ->toBe('10 caracteres')
        ->and(HelpdeskPlainText::fromHtml('<p>Hola</p>'))->toBe('Hola');
});

it('infolist helpdesk usa tarjeta de descripcion mejorada', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskInfolistSchema.php');

    expect($contents)
        ->toContain('fi-helpdesk-description-card')
        ->toContain('HelpdeskDescriptionInfolistRenderer::format')
        ->toContain('Descripción del caso');
});
