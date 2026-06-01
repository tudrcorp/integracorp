<?php

declare(strict_types=1);

namespace App\Services\Telemedicine;

use App\Http\Controllers\NotificationController;
use App\Mail\TelemedicineCaseDocumentMail;
use App\Services\TelemedicineConsultationDocumentsNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class TelemedicineCaseDocumentDeliveryService
{
    public static function notificationMessage(string $documentName): string
    {
        return <<<TEXT
        *Estimado(a) paciente*,

        Le informamos que en este mensaje va adjunto el documento «{$documentName}», generado en el marco de su atención de telemedicina.

        Por favor, revíselo con atención y guárdelo de forma segura. Si tiene alguna duda, no dude en consultarnos.

        Su salud es nuestra prioridad.
        Tu Dr. en Casa
        TEXT;
    }

    public static function publicUrl(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');

        return rtrim((string) config('parameters.PUBLIC_URL'), '/').'/'.$relativePath;
    }

    public static function fileExists(string $relativePath): bool
    {
        $relativePath = ltrim($relativePath, '/');

        if ($relativePath === '') {
            return false;
        }

        return Storage::disk('public')->exists($relativePath);
    }

    public static function sendWhatsApp(string $relativePath, string $documentName, string $phone): bool
    {
        $relativePath = ltrim($relativePath, '/');
        $caption = self::notificationMessage($documentName);

        return NotificationController::sendPublicStorageDocumentWhatsApp(
            $phone,
            $relativePath,
            $caption,
        );
    }

    public static function sendEmail(string $relativePath, string $documentName, string $email, string $patientName): void
    {
        Mail::to(trim($email))
            ->send(new TelemedicineCaseDocumentMail($patientName, $documentName, $relativePath));

        Log::info('TELEMEDICINA: Documento de caso enviado por correo.', [
            'email' => $email,
            'document_name' => $documentName,
            'file_path' => $relativePath,
        ]);
    }

    /**
     * @return array{whatsapp_sent: bool, email_sent: bool}
     */
    public static function send(
        string $relativePath,
        string $documentName,
        string $patientName,
        ?string $phone,
        ?string $email,
    ): array {
        $relativePath = ltrim($relativePath, '/');

        if (! self::fileExists($relativePath)) {
            throw new \RuntimeException('El archivo del documento no está disponible en el servidor.');
        }

        $whatsappSent = false;
        $emailSent = false;

        $normalizedPhone = filled($phone)
            ? TelemedicineConsultationDocumentsNotificationService::normalizePhone((string) $phone)
            : '';

        if ($normalizedPhone !== '') {
            $whatsappSent = self::sendWhatsApp($relativePath, $documentName, $normalizedPhone);
        }

        $normalizedEmail = trim((string) $email);

        if ($normalizedEmail !== '') {
            self::sendEmail($relativePath, $documentName, $normalizedEmail, $patientName);
            $emailSent = true;
        }

        return [
            'whatsapp_sent' => $whatsappSent,
            'email_sent' => $emailSent,
        ];
    }
}
