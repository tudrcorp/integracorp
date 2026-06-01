<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Controllers\NotificationController;
use App\Mail\TelemedicineConsultationDocumentsMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TelemedicineConsultationDocumentsNotificationService
{
    public const ADDITIONAL_WHATSAPP_PHONE = '04143027250';

    public const EMAIL_CC = 'solrodriguez@tudrencasa.com';

    /**
     * @param  array<int, string>  $pdfFilenames
     */
    public static function notify(
        string $patientPhone,
        ?string $patientEmail,
        string $patientName,
        array $pdfFilenames,
    ): void {
        $existingPdfFilenames = self::filterExistingPdfFilenames($pdfFilenames);

        if ($existingPdfFilenames === []) {
            Log::warning('TELEMEDICINA: No hay PDFs disponibles para enviar al paciente.', [
                'patient_name' => $patientName,
                'requested_files' => $pdfFilenames,
            ]);

            return;
        }

        self::sendWhatsAppDocuments($patientPhone, $existingPdfFilenames);
        self::sendEmailDocuments($patientEmail, $patientName, $existingPdfFilenames);

        Log::info('TELEMEDICINA: Proceso de notificación de documentos completado.', [
            'patient_name' => $patientName,
            'documents_count' => count($existingPdfFilenames),
            'phones' => self::recipientPhones($patientPhone),
            'email' => $patientEmail,
        ]);
    }

    /**
     * @param  array<int, string>  $pdfFilenames
     */
    public static function sendWhatsAppDocuments(string $patientPhone, array $pdfFilenames): void
    {
        $caption = self::notificationMessage();
        $phones = self::recipientPhones($patientPhone);

        foreach ($phones as $phone) {
            foreach ($pdfFilenames as $filename) {
                NotificationController::sendTelemedicineDocumentWhatsApp($phone, $filename, $caption);
            }
        }
    }

    /**
     * @param  array<int, string>  $pdfFilenames
     */
    public static function sendEmailDocuments(?string $patientEmail, string $patientName, array $pdfFilenames): void
    {
        $email = trim((string) $patientEmail);
        $mailable = new TelemedicineConsultationDocumentsMail($patientName, $pdfFilenames);

        if ($email === '') {
            Log::warning('TELEMEDICINA: El paciente no tiene correo electrónico registrado; se envía solo a la copia interna.', [
                'patient_name' => $patientName,
            ]);

            Mail::to(self::EMAIL_CC)->send($mailable);

            return;
        }

        Mail::to($email)
            ->cc(self::EMAIL_CC)
            ->send($mailable);

        Log::info('TELEMEDICINA: Correo con documentos de consulta enviado.', [
            'patient_name' => $patientName,
            'email' => $email,
            'cc' => self::EMAIL_CC,
            'documents_count' => count($pdfFilenames),
        ]);
    }

    public static function notificationMessage(): string
    {
        return <<<'TEXT'
        *Estimado(a) paciente*,

        Le informamos que en este mensaje van adjuntos los informes generados por su consulta médica de telemedicina.

        Por favor, revíselos con atención y guárdelos de forma segura. Si tiene alguna duda sobre las indicaciones, no dude en consultarnos.

        Su salud es nuestra prioridad.
        Tu Dr. en Casa
        TEXT;
    }

    /**
     * @return array<int, string>
     */
    public static function recipientPhones(string $patientPhone): array
    {
        $phones = array_filter([
            HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($patientPhone),
            HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp(self::ADDITIONAL_WHATSAPP_PHONE),
        ]);

        return array_values(array_unique($phones));
    }

    public static function telemedicineDocumentPublicUrl(string $filename): string
    {
        return rtrim((string) config('parameters.PUBLIC_URL'), '/').'/telemedicina-doc/'.$filename;
    }

    /**
     * @param  array<int, string>  $pdfFilenames
     * @return array<int, string>
     */
    public static function filterExistingPdfFilenames(array $pdfFilenames): array
    {
        return array_values(array_filter($pdfFilenames, function (string $filename): bool {
            return is_file(public_path('storage/telemedicina-doc/'.$filename));
        }));
    }

    public static function normalizePhone(string $phone): string
    {
        return HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($phone) ?? '';
    }
}
