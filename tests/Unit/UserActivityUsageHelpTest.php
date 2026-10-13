<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('el ícono de ayuda explica la fórmula del uso real', function (): void {
    $html = view('filament.business.pages.partials.user-activity-usage-help')->render();

    // @js escapa el HTML para el atributo: se decodifica para leer el texto.
    preg_match("/content: '(.*?)', theme/s", $html, $match);
    $tooltip = json_decode('"'.str_replace("\\'", "'", $match[1] ?? '').'"', true);

    expect($html)
        ->toContain('type="button"')
        ->toContain('aria-label="¿Cómo se calcula el uso real?"')
        ->toContain('x-on:click.stop')
        ->toContain('allowHTML: true')
        ->not->toContain('<strong>');

    expect($tooltip)
        ->toBeString()
        ->toContain('minutos activos ÷ minutos conectados')
        ->toContain('en los últimos '.intdiv(App\Support\UserActivity\UserActivityTracker::idleAfterSeconds(), 60).' min')
        ->toContain('Mide el uso del sistema, no la productividad.');
});

it('la ayuda aparece en las dos tarjetas y en los dos encabezados de uso real', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/pages/user-activity-monitor.blade.php');

    expect(substr_count($view, 'user-activity-usage-help'))->toBe(4)
        ->and($view)
        ->toContain('⚡ Uso real hoy @include(')
        ->toContain('<th class="num">Uso real @include(')
        ->toContain("\$th('usage', 'Uso real', true, ' '.view('filament.business.pages.partials.user-activity-usage-help')->render())")
        ->toContain('.uam-help-btn::before');
});
