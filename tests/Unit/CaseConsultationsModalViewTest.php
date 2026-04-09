<?php

declare(strict_types=1);

it('la vista del modal de consultas del caso prioriza la última consulta y guía la acción Actualizar', function (): void {
    $root = dirname(__DIR__, 2);
    $blade = file_get_contents($root.'/resources/views/filament/telemedicina/widgets/case-consultations-modal.blade.php');
    expect($blade)->toContain('Más reciente')
        ->and($blade)->toContain('Última consulta')
        ->and($blade)->toContain('$showUpdateButton')
        ->and($blade)->toContain('Código del caso')
        ->and($blade)->toContain('Cómo actualizar');

    $widget = file_get_contents($root.'/app/Filament/Telemedicina/Widgets/TelemedicineCaseTableDash.php');
    expect($widget)->toContain("->orderByDesc('id')")
        ->and($widget)->toContain('Consultas del caso ');
});

it('el modal de observaciones del dashboard telemedicina usa ventana y botones tematizados', function (): void {
    $root = dirname(__DIR__, 2);
    $theme = file_get_contents($root.'/resources/css/filament/admin/theme.css');
    expect($theme)->toContain('.fi-telemedicine-observation-modal-window');

    $dash = file_get_contents($root.'/app/Filament/Telemedicina/Widgets/TelemedicineCaseTableDash.php');
    expect($dash)->toContain('fi-telemedicine-observation-modal-window')
        ->and($dash)->toContain("FilamentIosButton::extraClassForFilamentColor('warning')");
});
