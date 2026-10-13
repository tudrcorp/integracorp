<?php

declare(strict_types=1);

use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\TelemedicinePatientDeliveryEmail;

uses(Tests\TestCase::class);

/**
 * Solo lectura: los modelos no se guardan.
 */
it('usa solo el correo propio del paciente', function (): void {
    $patient = new TelemedicinePatient([
        'email' => ' paciente@example.com ',
        'email_contact' => 'proveedoresdenegocio@mapfre.com.ve',
    ]);

    expect(TelemedicinePatientDeliveryEmail::for($patient))->toBe('paciente@example.com');
});

it('no cae al correo de contacto de la empresa cuando el paciente no tiene correo propio', function (?string $own): void {
    $patient = new TelemedicinePatient([
        'email' => $own,
        'email_contact' => 'proveedoresdenegocio@mapfre.com.ve',
    ]);

    expect(TelemedicinePatientDeliveryEmail::for($patient))->toBeNull()
        ->and(TelemedicinePatientDeliveryEmail::for($patient->toArray()))->toBeNull();
})->with([
    'nulo' => [null],
    'vacío' => [''],
    'solo espacios' => ['   '],
    'con formato inválido' => ['no-es-un-correo'],
]);

it('acepta paciente nulo y arreglos sin correo', function (): void {
    expect(TelemedicinePatientDeliveryEmail::for(null))->toBeNull()
        ->and(TelemedicinePatientDeliveryEmail::for([]))->toBeNull()
        ->and(TelemedicinePatientDeliveryEmail::for(['email' => 'a@example.com']))->toBe('a@example.com');
});

it('los tres envíos de telemedicina ya no usan email_contact como respaldo', function (): void {
    $root = dirname(__DIR__, 2);
    $files = [
        '/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php',
        '/app/Support/Telemedicine/TelemedicineCaseDocumentsCatalog.php',
        '/app/Support/Operations/TelemedicineCaseBitacora.php',
    ];

    foreach ($files as $file) {
        $source = (string) file_get_contents($root.$file);

        expect($source)
            ->toContain('TelemedicinePatientDeliveryEmail::for(')
            ->not->toMatch('/email\s*(\?\?|\?:)\s*\$?[\w>\-\?\[\]\'\(\)\$]*email_contact/');
    }

    expect((string) file_get_contents($root.$files[0]))->not->toContain("\$patient['email_contact']")
        ->and((string) file_get_contents($root.$files[1]))->not->toContain('?->email_contact')
        ->and((string) file_get_contents($root.$files[2]))->not->toContain('$patient?->email_contact');
});

it('el aviso de la consulta no afirma que se envió el correo si el paciente no tiene uno', function (): void {
    $job = (string) file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendTelemedicineConsultationDocuments.php');
    $action = (string) file_get_contents(dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseDocumentSendAction.php');

    expect($job)
        ->toContain('filled($this->patientEmail)')
        ->toContain('no tiene correo propio registrado')
        ->and($action)->toContain('El paciente no tiene correo propio registrado');
});
