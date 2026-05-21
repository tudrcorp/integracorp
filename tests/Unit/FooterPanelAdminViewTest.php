<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('renderiza el pie de panel con estructura accesible y marca', function (): void {
    $html = view('footer-panel-admin')->render();
    $themeCss = file_get_contents(resource_path('css/filament/admin/theme.css'));

    expect($html)
        ->toContain('role="contentinfo"')
        ->toContain('fi-panel-footer')
        ->toContain('TuDrGroup')
        ->toContain('IntegraCorp')
        ->toContain('fi-panel-footer__version')
        ->toContain('v1.0')
        ->toContain((string) date('Y'))
        ->and($html)->not->toContain('bg-white border-t border-gray-200');

    expect($themeCss)
        ->toContain('.fi-panel-footer {')
        ->toContain('dark:border-white/10')
        ->toContain('dark:bg-gray-950/70');
});

it('usa la versión configurada del panel cuando existe', function (): void {
    config(['app.panel_version' => '2.4.1']);

    $html = view('footer-panel-admin')->render();

    expect($html)->toContain('v2.4.1');
});
