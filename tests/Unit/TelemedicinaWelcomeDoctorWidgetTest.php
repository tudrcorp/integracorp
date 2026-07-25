<?php

declare(strict_types=1);

it('reemplaza el AccountWidget por el widget de bienvenida en el panel de telemedicina', function (): void {
    $panel = file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/TelemedicinaPanelProvider.php');

    expect($panel)
        ->toContain('WelcomeDoctorWidget::class')
        ->not->toContain('AccountWidget::class');
});

it('define el widget de bienvenida del doctor con la vista liquid glass compartida', function (): void {
    $widget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Widgets/WelcomeDoctorWidget.php');

    expect($widget)
        ->toContain('protected static bool $isDiscovered = false')
        ->toContain("protected string \$view = 'filament.widgets.welcome-user-liquid-glass'")
        ->toContain("protected int|string|array \$columnSpan = 'full'")
        ->toContain('Buenos días')
        ->toContain('Buenas tardes')
        ->toContain('Buenas noches')
        ->toContain('Filament::getUserAvatarUrl')
        ->toContain('Filament::getUserName')
        ->toContain('TelemedicineDoctor')
        ->toContain('Panel de Telemedicina');
});

it('la vista compartida mantiene logout, liquid glass y estilo ios', function (): void {
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
