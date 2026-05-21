<?php

declare(strict_types=1);

it('aplica estilos visuales tipo AgentForm master en form e infolist de historia clínica', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineHistoryPatients/Schemas/TelemedicineHistoryPatientForm.php');
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineHistoryPatients/Schemas/TelemedicineHistoryPatientInfolist.php');

    expect($form)
        ->toContain('private const TABS_CONTAINER')
        ->toContain('private const SECTION_CARD')
        ->toContain("Tabs::make('telemedicineHistoryPatientFormTabs')")
        ->toContain("'class' => self::TABS_CONTAINER")
        ->toContain("'class' => self::SECTION_CARD")
        ->and($infolist)
        ->toContain('private const TABS_CONTAINER')
        ->toContain('private const SECTION_CARD')
        ->toContain("Tabs::make('telemedicineHistoryPatientInfolistTabs')")
        ->toContain("'class' => self::TABS_CONTAINER")
        ->toContain("'class' => self::SECTION_CARD");
});
