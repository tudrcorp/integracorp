<?php

declare(strict_types=1);

use App\Support\HelpdeskTicketQuickSummary;

$errorReportHtml = '<p><strong>Reporte automático desde la página de error.</strong></p>'
    .'<p>JOSé LEON recibió el <strong>error 500 (Error del servidor)</strong> en <strong>Comisiones Generadas · listado</strong>, panel Agentes.</p>'
    .'<p><strong>Lo que dice el usuario</strong></p>'
    .'<p>Quería ver mis comisiones</p>'
    .'<p><strong>Diagnóstico</strong></p>'
    .'<p>Falla de código: <strong>Illuminate\View\ViewException</strong>: SQLSTATE[42S22]: Column not found</p>'
    .'<ul><li><strong>Huella:</strong> abc123</li></ul>'
    .'<p><strong>Qué pasó</strong></p>'
    .'<ul><li><strong>Error:</strong> 500 — Error del servidor</li></ul>';

it('reduce un reporte automatico de error a que paso, comentario y causa', function () use ($errorReportHtml): void {
    $summary = HelpdeskTicketQuickSummary::fromDescription($errorReportHtml, 'JOSé LEON', 'José Leon');

    expect($summary['kind'])->toBe('error_report')
        ->and($summary['error_code'])->toBe('500')
        ->and($summary['summary'])->toBe('José Leon recibió el error 500 (Error del servidor) en Comisiones Generadas · listado, panel Agentes.')
        ->and($summary['note'])->toBe('Quería ver mis comisiones')
        ->and($summary['cause'])->toBe('Illuminate\View\ViewException: SQLSTATE[42S22]: Column not found');
});

it('no pega las frases de parrafos distintos', function (): void {
    $summary = HelpdeskTicketQuickSummary::fromDescription('<p>Primera idea.</p><p>Segunda idea</p><ul><li>Punto uno</li></ul>');

    expect($summary['kind'])->toBe('plain')
        ->and($summary['text'])->toBe("Primera idea.\nSegunda idea\n• Punto uno");
});

it('acepta texto plano y descripciones vacias', function (): void {
    expect(HelpdeskTicketQuickSummary::fromDescription('Crear ficha técnica de la agencia')['text'])->toBe('Crear ficha técnica de la agencia')
        ->and(HelpdeskTicketQuickSummary::fromDescription(null)['kind'])->toBe('empty')
        ->and(HelpdeskTicketQuickSummary::fromDescription('   <p> </p> ')['kind'])->toBe('empty');
});

it('marca la ausencia de comentario y no inventa causa', function (): void {
    $html = '<p><strong>Reporte automático desde la página de error.</strong></p>'
        .'<p>Ana recibió el <strong>error 403 (Sin acceso)</strong> en <strong>Afiliaciones</strong>.</p>'
        .'<p><strong>Lo que dice el usuario</strong></p><p><em>No agregó comentarios.</em></p>';

    $summary = HelpdeskTicketQuickSummary::fromDescription($html);

    expect($summary['note'])->toBeNull()
        ->and($summary['cause'])->toBeNull()
        ->and($summary['error_code'])->toBe('403');
});

it('acorta descripciones largas y no deja etiquetas ni entidades', function (): void {
    $summary = HelpdeskTicketQuickSummary::fromDescription('<p>'.str_repeat('Texto &amp; más ', 60).'</p>');

    expect(mb_strlen((string) $summary['text']))->toBeLessThanOrEqual(323)
        ->and($summary['text'])->not->toContain('&amp;')
        ->and($summary['text'])->not->toContain('<');
});

it('normaliza nombres y estatus para leerlos sin mayusculas', function (): void {
    expect(HelpdeskTicketQuickSummary::displayName('JOSé  LEON'))->toBe('José Leon')
        ->and(HelpdeskTicketQuickSummary::displayName('  '))->toBeNull()
        ->and(HelpdeskTicketQuickSummary::sentenceCase('PENDIENTE POR INICIAR'))->toBe('Pendiente por iniciar');
});

it('usa la vista compilada por el tema, aviso persistente y acciones segun el estatus', function (): void {
    $root = dirname(__DIR__, 2);
    $ticker = file_get_contents($root.'/app/Livewire/BusinessHelpdeskTicketsTicker.php');

    expect($ticker)
        ->toContain("view('filament.helpdesks.ticket-quick-summary'")
        ->toContain('->persistent()')
        ->toContain("if (\$status === 'PENDIENTE POR INICIAR')")
        ->toContain("HelpdeskResource::getUrl('view'")
        ->not->toContain('strip_tags');

    expect(file_get_contents($root.'/resources/css/filament/admin/theme.css'))
        ->toContain("@source '../../../../resources/views/filament/**/*';");
});
