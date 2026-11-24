<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\TelemedicineConsultationDocumentsNotificationService;
use App\Support\Telemedicine\Concerns\LogsTelemedicineJobFailures;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Correo con los documentos de la consulta de telemedicina, aparte del WhatsApp.
 *
 * Va en su propio trabajo para que una falla del correo no reintente el trabajo de los
 * documentos y el paciente no reciba los WhatsApp repetidos.
 *
 * Un rechazo temporal del servidor de correo (código 4xx, por ejemplo el «421 Connection
 * expired» de Gmail cuando cierra una conexión inactiva del worker, o una caída de la
 * conexión) no es un error del sistema: se descarta la conexión vieja y se reintenta más
 * tarde sin reportarlo. Solo se reporta un rechazo definitivo (5xx) o el último intento.
 */
class SendTelemedicineConsultationDocumentsEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, LogsTelemedicineJobFailures, Queueable;

    public int $tries = 4;

    /**
     * Espera entre intentos, en segundos: 1, 5 y 15 minutos.
     *
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    /**
     * @param  array<int, string>  $pdfFilenames
     */
    public function __construct(
        public ?string $patientEmail,
        public string $patientName,
        public array $pdfFilenames,
        public ?int $consultationId = null,
        public ?int $userId = null,
    ) {}

    public function handle(): void
    {
        $this->runWithTelemedicineFailureLogging(function (): void {
            $pdfFilenames = TelemedicineConsultationDocumentsNotificationService::filterExistingPdfFilenames($this->pdfFilenames);

            if ($pdfFilenames === []) {
                Log::warning('TELEMEDICINA: No hay PDFs disponibles para el correo de la consulta.', $this->telemedicineJobFailureContext());

                return;
            }

            try {
                TelemedicineConsultationDocumentsNotificationService::sendEmailDocuments($this->patientEmail, $this->patientName, $pdfFilenames);
            } catch (TransportExceptionInterface $exception) {
                self::discardMailConnection();

                if (! self::isTransient($exception) || $this->attempts() >= $this->tries) {
                    throw $exception;
                }

                $delay = $this->retryDelay();

                Log::warning('TELEMEDICINA: el servidor de correo rechazó temporalmente el envío de los documentos; se reintenta.', [
                    ...$this->telemedicineJobFailureContext(),
                    'smtp_code' => $exception->getCode(),
                    'retry_in_seconds' => $delay,
                    'exception' => $exception,
                ]);

                $this->release($delay);
            }
        }, $this->telemedicineJobFailureContext());
    }

    public function failed(?Throwable $exception): void
    {
        $this->logTelemedicineJobFailure($exception, $this->telemedicineJobFailureContext());

        $user = $this->userId !== null ? User::query()->find($this->userId) : null;

        if ($user === null) {
            return;
        }

        Notification::make()
            ->title('No se pudo enviar el correo de la consulta')
            ->body('El paciente '.$this->patientName.' recibió los documentos por WhatsApp, pero el correo no salió después de varios intentos. Puede reenviarlos desde el caso indicando el correo.')
            ->warning()
            ->sendToDatabase($user);
    }

    /**
     * Rechazo temporal: código SMTP 4xx o caída de la conexión (sin código). Un 5xx es
     * definitivo (dirección inválida, autenticación, límite diario).
     */
    public static function isTransient(Throwable $exception): bool
    {
        $code = (int) $exception->getCode();

        return $code === 0 || ($code >= 400 && $code < 500);
    }

    /**
     * El worker vive horas y reutiliza la conexión SMTP: tras un rechazo se descarta para que
     * el siguiente intento abra una nueva.
     */
    public static function discardMailConnection(): void
    {
        try {
            Mail::purge();
        } catch (Throwable) {
            /** Si no se puede descartar, el siguiente intento lo hará el transporte por su cuenta. */
        }
    }

    private function retryDelay(): int
    {
        return $this->backoff[max(0, min($this->attempts() - 1, count($this->backoff) - 1))];
    }

    /**
     * @return array<string, mixed>
     */
    private function telemedicineJobFailureContext(): array
    {
        return [
            'telemedicine_consultation_id' => $this->consultationId,
            'patient_email' => $this->patientEmail,
            'patient_name' => $this->patientName,
            'documents_count' => count($this->pdfFilenames),
            'user_id' => $this->userId,
        ];
    }
}
