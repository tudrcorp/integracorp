<?php

declare(strict_types=1);

it('agrupa las acciones de cabecera de la consulta en un menú como en Negocios', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');

    $backPosition = strpos($source, "Action::make('back_dashboard')");
    $menuPosition = strpos($source, 'FilamentIosActionsMenu::make([');
    $previewPosition = strpos($source, "Action::make('preview_lab_imaging_results')");

    expect($source)
        ->toContain('use App\Support\Filament\FilamentIosActionsMenu;')
        ->toContain('FilamentIosActionsMenu::make([')
        ->toContain("Action::make('back_dashboard')")
        ->toContain("->label('Dashboard')")
        ->toContain("Action::make('preview_lab_imaging_results')")
        ->toContain("Action::make('autorizar_fuera_de_limite')")
        ->toContain("Action::make('create_history')")
        ->toContain("Action::make('edit_history')")
        ->toContain("Action::make('view_history')")
        ->toContain("Action::make('consultation_history')")
        ->toContain("Action::make('consultation_history_case')")
        ->and(substr_count($source, 'FilamentIosButton::extraClassForFilamentColor'))->toBe(1)
        ->and($backPosition)->toBeInt()->toBeLessThan($menuPosition)
        ->and($menuPosition)->toBeInt()->toBeLessThan($previewPosition);
});
