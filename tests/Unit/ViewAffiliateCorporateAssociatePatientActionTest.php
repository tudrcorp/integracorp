<?php

declare(strict_types=1);

it('ViewAffiliateCorporate expone acción de asociar afiliado como paciente con confirmación e estilos iOS', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AffiliateCorporates/Pages/ViewAffiliateCorporate.php');
    expect($page)->not->toBeFalse();

    expect($page)
        ->toContain("Action::make('associate_as_patient')")
        ->toContain('->requiresConfirmation()')
        ->toContain('AssociateAffiliateCorporateWithTelemedicinePatientService::run')
        ->toContain('Asociar a Pacientes')
        ->toContain('Sí, asociar')
        ->toContain('ticket-btn-ios')
        ->toContain('ticket-btn-ios-gray')
        ->toContain('modalSubmitAction')
        ->toContain('modalCancelAction');
});

it('AssociateAffiliateCorporateWithTelemedicinePatientService valida afiliación y estado activo', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/AssociateAffiliateCorporateWithTelemedicinePatientService.php');
    expect($service)->not->toBeFalse();

    expect($service)
        ->toContain('if ($member->affiliationCorporate === null)')
        ->toContain("if (\$member->status !== 'ACTIVO')")
        ->toContain("TelemedicinePatient::updateOrCreate(['email' => \$emailKey], \$attributes)")
        ->toContain("'type_affiliation' => 'CORPORATIVO'")
        ->toContain("'supplier_id' => Auth::user()?->supplier_id");
});
