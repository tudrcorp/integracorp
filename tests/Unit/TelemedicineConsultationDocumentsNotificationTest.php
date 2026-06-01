<?php

declare(strict_types=1);

use App\Services\TelemedicineConsultationDocumentsNotificationService;

it('TelemedicineConsultationDocumentsNotificationService incluye telefono adicional 04143027250', function (): void {
    $phones = TelemedicineConsultationDocumentsNotificationService::recipientPhones('04141234567');

    expect($phones)
        ->toContain('+584141234567')
        ->toContain('+584143027250');
});

it('TelemedicineConsultationDocumentsNotificationService usa URL publica de produccion para documentos', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Services/TelemedicineConsultationDocumentsNotificationService.php');

    expect($contents)
        ->toContain("rtrim((string) config('parameters.PUBLIC_URL'), '/').'/telemedicina-doc/'")
        ->not->toContain('integracorp.test');
});

it('TelemedicineConsultationDocumentsNotificationService informa adjuntos de informes medicos en whatsapp', function (): void {
    expect(TelemedicineConsultationDocumentsNotificationService::notificationMessage())
        ->toContain('adjuntos los informes generados por su consulta médica de telemedicina');
});

it('TelemedicineConsultationDocumentsMail usa copia a solrodriguez@tudrencasa.com', function (): void {
    $serviceContents = file_get_contents(dirname(__DIR__, 2).'/app/Services/TelemedicineConsultationDocumentsNotificationService.php');
    $mailContents = file_get_contents(dirname(__DIR__, 2).'/app/Mail/TelemedicineConsultationDocumentsMail.php');
    $viewContents = file_get_contents(dirname(__DIR__, 2).'/resources/views/mails/telemedicine-consultation-documents.blade.php');

    expect($serviceContents)
        ->toContain("public const EMAIL_CC = 'solrodriguez@tudrencasa.com';")
        ->toContain('->cc(self::EMAIL_CC)');

    expect($mailContents)->toContain('TelemedicineConsultationDocumentsMail');

    expect($viewContents)
        ->toContain('adjuntos los informes generados por su consulta médica de telemedicina');
});

it('jobs de generacion de PDF de telemedicina usan el trait Batchable', function (): void {
    $jobFiles = [
        'GeneratePdfInformeMedicoCorto.php',
        'GeneratePdfInformeMedicoLargo.php',
        'GeneratePdfMedicamentos.php',
        'GeneratePdfLaboratorio.php',
        'GeneratePdfImagenologia.php',
        'GeneratePdfEspecialista.php',
    ];

    foreach ($jobFiles as $jobFile) {
        $contents = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/'.$jobFile);

        expect($contents)
            ->toContain('use Illuminate\Bus\Batchable;')
            ->toContain('use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;');
    }
});

it('CreateTelemedicineConsultationPatient encadena generacion de PDFs con envio al paciente', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');

    expect($contents)
        ->toContain('Bus::batch($pdfJobs)')
        ->toContain('SendTelemedicineConsultationDocuments::dispatch')
        ->toContain("new GeneratePdfMedicamentos(\$dataMedicamentos, Auth::user(), 'medicamentos')")
        ->toContain("new GeneratePdfLaboratorio(\$dataLaboratorios, Auth::user(), 'laboratorios')")
        ->toContain("new GeneratePdfImagenologia(\$dataEstudios, Auth::user(), 'imagenologia')")
        ->toContain("new GeneratePdfEspecialista(\$dataEspecialistas, Auth::user(), 'especialista')");
});

it('NotificationController envia documentos de telemedicina al telefono indicado', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/NotificationController.php');

    expect($contents)
        ->toContain('public static function sendTelemedicineDocumentWhatsApp(string $phone, string $namePdf, string $caption): bool')
        ->toContain('HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($phone)')
        ->toContain('TelemedicineConsultationDocumentsNotificationService::telemedicineDocumentPublicUrl($namePdf)')
        ->toContain('whatsAppApiResponseSucceeded')
        ->not->toContain("'to' => '04127018390',");
});

it('TelemedicineConsultationDocumentsMail se envia de forma sincrona', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Mail/TelemedicineConsultationDocumentsMail.php');

    expect($contents)
        ->toContain('class TelemedicineConsultationDocumentsMail extends Mailable')
        ->not->toContain('implements ShouldQueue');
});
