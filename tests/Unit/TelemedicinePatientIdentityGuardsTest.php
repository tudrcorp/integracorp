<?php

declare(strict_types=1);

it('los servicios de asociación usan resolver por cédula y no updateOrCreate por email', function (): void {
    $corporate = file_get_contents(dirname(__DIR__, 2).'/app/Services/AssociateAffiliateCorporateWithTelemedicinePatientService.php');
    $individual = file_get_contents(dirname(__DIR__, 2).'/app/Services/AssociateAffiliateWithTelemedicinePatientService.php');
    $company = file_get_contents(dirname(__DIR__, 2).'/app/Services/AssociateCompanyAssociateWithTelemedicinePatientService.php');

    expect($corporate)->not->toBeFalse()
        ->and($individual)->not->toBeFalse()
        ->and($company)->not->toBeFalse();

    foreach ([$corporate, $individual, $company] as $service) {
        expect($service)
            ->toContain('TelemedicinePatientAssociationResolver::upsertByDocument')
            ->not->toContain("updateOrCreate(['email'")
            ->not->toContain('updateOrCreate(["email"');
    }
});

it('CreateTelemedicineConsultationPatient fuerza identidad del paciente del caso', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');

    expect($page)->not->toBeFalse()
        ->and($page)->toContain('TelemedicinePatientIdentity::enforceConsultationIdentity')
        ->and($page)->toContain('La identidad de la sesión no coincide con el paciente del caso');
});

it('el observer bloquea cédulas duplicadas al guardar', function (): void {
    $observer = file_get_contents(dirname(__DIR__, 2).'/app/Observers/TelemedicinePatientObserver.php');

    expect($observer)->not->toBeFalse()
        ->and($observer)->toContain('function saving')
        ->and($observer)->toContain('TelemedicinePatientIdentity::assertDocumentIsAvailable')
        ->and($observer)->toContain('TelemedicinePatientIdentity::normalizeDocument');
});

it('existe comando de remediación por identidad desplazada', function (): void {
    $command = file_get_contents(dirname(__DIR__, 2).'/app/Console/Commands/Telemedicine/RemediateSharedEmailPatientIdentityCommand.php');

    expect($command)->not->toBeFalse()
        ->and($command)->toContain('telemedicine:remediate-shared-email-patient-identity')
        ->and($command)->toContain('--apply')
        ->and($command)->toContain('resolveOrCreatePatientForDocument')
        ->and($command)->toContain('AssociateAffiliateWithTelemedicinePatientService::run')
        ->and($command)->toContain('Affiliate::query()');
});

it('la auditoría diaria de identidad queda programada', function (): void {
    $console = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');

    expect($console)->not->toBeFalse()
        ->and($console)->toContain("Schedule::command('telemedicine:audit-patient-case-identity')");
});
