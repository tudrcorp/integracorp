<?php

declare(strict_types=1);

it('ViewAffiliate expone acción de asociar afiliado como paciente con confirmación', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Affiliates/Pages/ViewAffiliate.php');
    expect($page)->not->toBeFalse();

    expect($page)
        ->toContain("Action::make('associate_as_patient')")
        ->toContain('->requiresConfirmation()')
        ->toContain('AssociateAffiliateWithTelemedicinePatientService::run')
        ->toContain('Asociar a Pacientes')
        ->toContain('Sí, asociar')
        ->toContain('ticket-btn-ios')
        ->toContain('ticket-btn-ios-gray')
        ->toContain('modalSubmitAction')
        ->toContain('modalCancelAction');
});

it('AssociateAffiliateWithTelemedicinePatientService valida afiliación y estado activo', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/AssociateAffiliateWithTelemedicinePatientService.php');
    expect($service)->not->toBeFalse();

    expect($service)
        ->toContain('if ($affiliate->affiliation === null)')
        ->toContain("if (\$affiliate->status !== 'ACTIVO')")
        ->toContain("TelemedicinePatient::updateOrCreate(['email' => \$emailTitular], \$attributes)")
        ->toContain("'type_affiliation' => 'INDIVIDUAL'")
        ->toContain("'supplier_id' => Auth::user()?->supplier_id");
});
