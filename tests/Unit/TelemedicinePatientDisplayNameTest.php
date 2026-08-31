<?php

declare(strict_types=1);

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\OperationCoordinationService;
use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\TelemedicinePatientDisplayName;

it('usa full_name para el afiliado individual', function (): void {
    $affiliate = new Affiliate(['full_name' => '  MARIA  ELENA  TORRES  ']);

    expect(TelemedicinePatientDisplayName::fromAffiliate($affiliate))->toBe('MARIA ELENA TORRES');
});

it('compone first_name y last_name para el afiliado corporativo', function (): void {
    $affiliate = new AffiliateCorporate([
        'first_name' => 'JUAN CARLOS',
        'last_name' => 'BARRETO GUTIERREZ',
    ]);

    expect(TelemedicinePatientDisplayName::fromAffiliateCorporate($affiliate))->toBe('JUAN CARLOS BARRETO GUTIERREZ');
});

it('usa solo first_name cuando last_name está vacío o nulo', function (): void {
    $emptyLast = new AffiliateCorporate([
        'first_name' => 'ANA PEREZ',
        'last_name' => '',
    ]);
    $nullLast = new AffiliateCorporate([
        'first_name' => 'ANA PEREZ',
        'last_name' => null,
    ]);

    expect(TelemedicinePatientDisplayName::fromAffiliateCorporate($emptyLast))->toBe('ANA PEREZ')
        ->and(TelemedicinePatientDisplayName::fromAffiliateCorporate($nullLast))->toBe('ANA PEREZ');
});

it('cae al full_name del paciente de telemedicina si no hay afiliación vinculada', function (): void {
    $patient = new TelemedicinePatient([
        'full_name' => 'PACIENTE SIN AFILIACION',
        'type_affiliation' => 'NUEVOS NEGOCIOS',
    ]);

    expect(TelemedicinePatientDisplayName::fromPatient($patient))->toBe('PACIENTE SIN AFILIACION');
});

it('usa el nombre persistido de la coordinación cuando el PDF no tiene paciente cargado', function (): void {
    $coord = new OperationCoordinationService([
        'patient' => 'IGNACIO ALEJANDRO RAMOS VASQUEZ',
    ]);

    expect(TelemedicinePatientDisplayName::forCoordination($coord))->toBe('IGNACIO ALEJANDRO RAMOS VASQUEZ');
});

it('la asociación corporativa persiste el nombre compuesto y no solo first_name', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/AssociateAffiliateCorporateWithTelemedicinePatientService.php');

    expect($service)
        ->toContain('TelemedicinePatientDisplayName::fromAffiliateCorporate')
        ->and($service)->not->toContain("'full_name' => \$member->first_name");
});

it('los informes y documentos de telemedicina y operaciones resuelven el nombre canónico', function (): void {
    $files = [
        dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineInformeLargoDataBuilder.php',
        dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseDocumentRegenerationService.php',
        dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseIdentity.php',
        dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicinePatientIdentity.php',
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php',
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/EditTelemedicineConsultationPatient.php',
        dirname(__DIR__, 2).'/resources/views/documents/operation-service-order-pdf.blade.php',
        dirname(__DIR__, 2).'/resources/views/documents/operation-service-order-quote-pdf.blade.php',
        dirname(__DIR__, 2).'/resources/views/documents/operation-service-order-medication-quote-pdf.blade.php',
        dirname(__DIR__, 2).'/resources/views/documents/operation-quote-generator-pdf.blade.php',
    ];

    foreach ($files as $path) {
        expect(file_get_contents($path))->toContain('TelemedicinePatientDisplayName');
    }
});
