<?php

declare(strict_types=1);

/**
 * Las cajas grises del informe médico (motivo, enfermedad actual, antecedentes,
 * impresión diagnóstica, las del seguimiento y la de observaciones) eran un <div> al 100 % con
 * padding y borde: DomPDF los suma al ancho y la caja se salía por la derecha
 * del margen. Ahora son una tabla de una celda con el relleno en la celda.
 */
function informeMedicoProseBoxPartial(): string
{
    return (string) file_get_contents(
        dirname(__DIR__, 2).'/resources/views/documents/partials/informe-medico-homologado.blade.php'
    );
}

it('ninguna caja gris del informe medico es un div', function (): void {
    expect(informeMedicoProseBoxPartial())->not->toContain('<div class="prose-box">');
});

it('cada caja gris es una tabla de una celda', function (): void {
    expect(substr_count(informeMedicoProseBoxPartial(), '<table class="prose-box"><tr><td>{{ '))->toBe(8);
});

it('el padding y el borde van en la celda, y la tabla usa ancho fijo', function (): void {
    $source = informeMedicoProseBoxPartial();

    preg_match('/table\.prose-box \{(.*?)\}/s', $source, $table);
    preg_match('/table\.prose-box td \{(.*?)\}/s', $source, $cell);

    expect($table[1] ?? '')
        ->toContain('width: 100%')
        ->toContain('table-layout: fixed')
        ->not->toContain('padding')
        ->not->toContain('border:');

    expect($cell[1] ?? '')
        ->toContain('padding: 4px 6px')
        ->toContain('border: 1px solid #e5e7eb')
        ->toContain('white-space: pre-wrap');
});

it('la celda no deja espacios en blanco alrededor del texto (pre-wrap los imprimiria)', function (): void {
    expect(informeMedicoProseBoxPartial())
        ->not->toMatch('/<td>\s+\{\{/')
        ->not->toMatch('/\}\}\s+<\/td><\/tr><\/table>/');
});
