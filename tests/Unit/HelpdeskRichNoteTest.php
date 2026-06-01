<?php

declare(strict_types=1);

use App\Support\HelpdeskNoteHtmlSanitizer;
use App\Support\HelpdeskObservationAppender;
use App\Support\HelpdeskObservationHtmlRenderer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Config::set('app.timezone', 'America/Caracas');
    Carbon::setTestNow(Carbon::parse('2026-03-27 14:30:00', 'America/Caracas'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('sanitiza HTML y elimina scripts', function (): void {
    $clean = HelpdeskNoteHtmlSanitizer::sanitize('<p>Hola</p><script>alert(1)</script>');

    expect($clean)->not->toContain('script')
        ->and($clean)->toContain('Hola');
});

it('renderiza bloque con formato enriquecido', function (): void {
    $obs = "[27/03/2026 14:30 · Ana]\n<p>Texto <strong>negrita</strong> y 😀</p>";
    $html = HelpdeskObservationHtmlRenderer::render($obs);

    expect($html)->toContain('negrita')
        ->and($html)->toContain('😀')
        ->and($html)->toContain('helpdesk-notes-surface')
        ->and($html)->toContain('helpdesk-notes-feed')
        ->and($html)->toContain('helpdesk-note-card');
});

it('renderiza cambio de estado con transicion y motivo estructurados', function (): void {
    $obs = "[01/06/2026 19:12 · Gustavo Camacho]\n"
        .'<p>Estado del ticket actualizado de <strong>EN PROCESO</strong> a <strong>PLANIFICADO</strong>.</p>'
        .'<p><strong>Motivo del cambio (analista asignado):</strong></p>'
        .'<p>El ticket se está planificando.</p>';

    $html = HelpdeskObservationHtmlRenderer::render($obs);

    expect($html)
        ->toContain('helpdesk-note-status-flow')
        ->toContain('helpdesk-note-status-pill')
        ->toContain('helpdesk-note-status-reason')
        ->toContain('EN PROCESO')
        ->toContain('PLANIFICADO')
        ->toContain('El ticket se está planificando');
});

it('renderiza tarjetas con tipo de entrada y avatar', function (): void {
    $obs = "[20/05/2026 22:12 · GUSTAVO CAMACHO]\nEstado del ticket actualizado de **PENDIENTE** a **EN PROCESO**.\n\n"
        ."[20/05/2026 22:15 · GUSTAVO CAMACHO]\nNota manual";

    $html = HelpdeskObservationHtmlRenderer::render($obs);

    expect($html)
        ->toContain('GC')
        ->toContain('Gustavo Camacho')
        ->toContain('helpdesk-note-card__badge--status')
        ->toContain('helpdesk-note-card__badge--note')
        ->and(HelpdeskObservationHtmlRenderer::countEntries($obs))->toBe(2);
});

it('theme css no contiene selectores invalidos del historial de notas', function (): void {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');

    expect($css)->not->toContain('helpdesk-notes-surface .,')
        ->and($css)->toContain('.helpdesk-notes-surface .helpdesk-note-card')
        ->and($css)->toContain('.dark .helpdesk-notes-surface .helpdesk-note-card__badge--note');
});

it('theme css define estilos dark para badges y contenido de notas', function (): void {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');

    expect($css)
        ->toContain('.dark .helpdesk-notes-surface .helpdesk-note-card__badge--priority')
        ->toContain('.dark .helpdesk-notes-surface .helpdesk-note-card__content')
        ->toContain('.dark .fi-helpdesk-notes-modal .helpdesk-notes-surface .helpdesk-note-card__body');
});

it('mergeObservation acepta cuerpo HTML', function (): void {
    $out = HelpdeskObservationAppender::mergeObservation('', '<p>Contenido <em>rico</em></p>', 'Ana');

    expect($out)->toContain('[27/03/2026 14:30 · Ana]')
        ->and($out)->toContain('rico');
});
