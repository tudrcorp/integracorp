<?php

declare(strict_types=1);

it('reemplaza el AccountWidget por el widget de bienvenida en el panel de telemedicina', function (): void {
    $panel = file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/TelemedicinaPanelProvider.php');

    expect($panel)
        ->toContain('WelcomeDoctorWidget::class')
        ->not->toContain('AccountWidget::class');
});

it('define el widget de bienvenida del doctor con vista propia y ancho completo', function (): void {
    $widget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Widgets/WelcomeDoctorWidget.php');

    expect($widget)
        ->toContain("protected string \$view = 'filament.telemedicina.widgets.welcome-doctor-widget'")
        ->toContain("protected int|string|array \$columnSpan = 'full'")
        ->toContain('Buenos días')
        ->toContain('Buenas tardes')
        ->toContain('Buenas noches')
        ->toContain('Filament::getUserAvatarUrl')
        ->toContain('Filament::getUserName');
});

it('la vista del widget mantiene el logout y aplica estilo ios', function (): void {
    $view = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/filament/telemedicina/widgets/welcome-doctor-widget.blade.php'
    );

    expect($view)
        ->toContain('filament()->getLogoutUrl()')
        ->toContain('@csrf')
        ->toContain('Salir')
        ->toContain("FilamentIosButton::extraClassForFilamentColor('danger')")
        ->toContain('$greeting')
        ->toContain('$name');
});
