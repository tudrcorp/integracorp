<?php

declare(strict_types=1);

it('registra el widget de bienvenida liquid glass en los paneles internos', function (string $provider): void {
    $panel = file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/'.$provider);

    expect($panel)
        ->toContain('WelcomeUserLiquidGlassWidget::class')
        ->not->toContain('AccountWidget::class');
})->with([
    'operations' => 'OperationsPanelProvider.php',
    'business' => 'BusinessPanelProvider.php',
    'administration' => 'AdministrationPanelProvider.php',
    'marketing' => 'MarketingPanelProvider.php',
    'projects' => 'ProjectsPanelProvider.php',
]);

it('define el widget compartido de bienvenida con vista propia y ancho completo', function (): void {
    $widget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Widgets/WelcomeUserLiquidGlassWidget.php');

    expect($widget)
        ->toContain('protected static bool $isDiscovered = false')
        ->toContain("protected string \$view = 'filament.widgets.welcome-user-liquid-glass'")
        ->toContain("protected int|string|array \$columnSpan = 'full'")
        ->toContain('Buenos días')
        ->toContain('Buenas tardes')
        ->toContain('Buenas noches')
        ->toContain('Filament::getUserAvatarUrl')
        ->toContain('Filament::getUserName')
        ->toContain('RrhhColaborador')
        ->toContain("'business'")
        ->toContain("'administration'")
        ->toContain("'marketing'")
        ->toContain("'projects'")
        ->toContain("'operations'");
});

it('la vista del widget aplica liquid glass compacto, logout y estilo ios', function (): void {
    $view = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/filament/widgets/welcome-user-liquid-glass.blade.php'
    );

    expect($view)
        ->toContain('fi-welcome-liquid-glass-shell')
        ->toContain('fi-welcome-liquid-glass')
        ->toContain('filament()->getLogoutUrl()')
        ->toContain('@csrf')
        ->toContain('Salir')
        ->toContain("FilamentIosButton::extraClassForFilamentColor('danger')")
        ->toContain('$greeting')
        ->toContain('$name')
        ->toContain('$role');
});

it('el theme incluye estilos liquid glass compactos para el widget compartido', function (): void {
    $theme = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');

    expect($theme)
        ->toContain('.fi-welcome-liquid-glass-shell')
        ->toContain('max-width: 28rem')
        ->toContain('.fi-welcome-liquid-glass')
        ->toContain('backdrop-filter: blur(28px) saturate(185%)')
        ->toContain('fi-welcome-orb-drift');
});
