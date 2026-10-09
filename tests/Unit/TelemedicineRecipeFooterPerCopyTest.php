<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('pone un pie con los datos de la empresa debajo del original y otro debajo de la copia', function (): void {
    $html = view('documents.medicamentos', ['data' => [
        'medicationsArr' => [['medicines' => 'PROBIOTAL (TABLETAS)', 'indications' => '1 TABLETA CADA 12 HORAS']],
        'doctor_name' => 'MÉDICO QA',
        'code_mpps' => '000000',
    ]])->render();

    expect(substr_count($html, 'class="footer-fixed footer-fixed-'))->toBe(2)
        ->and($html)->toContain('footer-fixed footer-fixed-original')
        ->and($html)->toContain('footer-fixed footer-fixed-copy')
        ->and(substr_count($html, 'RIF.: J-50358368-1'))->toBe(2)
        ->and(substr_count($html, 'Dirección Comercial: Av. Francisco de Miranda'))->toBe(2)
        ->and(substr_count($html, 'Correo: 24H@tudrencasa.com'))->toBe(2);
});

it('alinea cada pie con su firma: mismo ancho y mismo margen lateral', function (): void {
    $template = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/partials/telemedicine-recipe-homologado.blade.php');

    expect($template)
        ->toMatch('/\.footer-fixed\s*\{[^}]*position:\s*fixed;[^}]*width:\s*128mm;/s')
        ->toMatch('/\.doctor-signature\s*\{[^}]*width:\s*128mm;/s')
        ->toContain('.footer-fixed-original { left: 14mm; }')
        ->toContain('.doctor-signature-original { left: 14mm; }')
        ->toContain('.footer-fixed-copy { right: 14mm; left: auto; }')
        ->toContain('.doctor-signature-copy { right: 14mm; left: auto; }')
        ->not->toMatch('/\.footer-fixed\s*\{[^}]*left:\s*12mm;/s');
});
