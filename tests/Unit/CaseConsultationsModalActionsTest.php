<?php

declare(strict_types=1);

it('el modal de consultas del caso centraliza historia, consulta inicial y observaciones', function (): void {
    $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/telemedicina/widgets/case-consultations-modal.blade.php');
    $widget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Widgets/TelemedicineCaseTableDash.php');

    expect($blade)
        ->toContain('wire:click="openHistoriaClinicaFromCaseModal(')
        ->and($blade)->toContain('wire:click="openConsultaInicialFromCaseModal(')
        ->and($blade)->toContain('saveObservationFromConsultationsModal')
        ->and($blade)->toContain('consultationsModalObservationDraft')
        ->and($blade)->toContain('showAddObservationInModal');

    expect($widget)
        ->toContain('public function saveObservationFromConsultationsModal(int $caseId): void')
        ->and($widget)->toContain('consultationsModalObservationDraft')
        ->and($widget)->toContain("'showAddObservationInModal'")
        ->and($widget)->toContain("'showConsultaInicialCardInModal'")
        ->and($widget)->toContain('->isEmpty()')
        ->and($widget)->toContain("Action::make('openCaseConsultations')");
});
