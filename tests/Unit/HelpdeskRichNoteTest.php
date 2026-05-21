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
        ->and($html)->toContain('helpdesk-notes-feed')
        ->and($html)->toContain('helpdesk-note-card');
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

it('mergeObservation acepta cuerpo HTML', function (): void {
    $out = HelpdeskObservationAppender::mergeObservation('', '<p>Contenido <em>rico</em></p>', 'Ana');

    expect($out)->toContain('[27/03/2026 14:30 · Ana]')
        ->and($out)->toContain('rico');
});
