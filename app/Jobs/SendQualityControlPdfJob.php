<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\QualityControlAffiliateType;
use App\Http\Controllers\NotificationController;
use App\Mail\QualityControlPdfMail;
use App\Models\QualityControlSurvey;
use App\Models\User;
use App\Services\QualityControlPdfService;
use App\Support\SecurityAudit;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Throwable;

/**
 * Envía por correo o WhatsApp el PDF de Quality Control (ficha de una encuesta o informe
 * de un listado). Genera el PDF en el worker con la misma copia por huella que la vista
 * previa. WhatsApp recibe el documento por un enlace firmado que vence a las 48 horas.
 */
class SendQualityControlPdfJob implements ShouldQueue
{
    use Queueable;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const SHARE_HOURS = 48;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @param  list<string>  $recipients  correos o un teléfono
     * @param  list<int>  $reportIds  encuestas del informe (vacío si es una ficha)
     */
    public function __construct(
        public string $channel,
        public array $recipients,
        public int $userId,
        public string $affiliateType,
        public ?int $surveyId = null,
        public array $reportIds = [],
        public string $reportLabel = '',
    ) {}

    public function handle(): void
    {
        $user = User::query()->find($this->userId);
        $document = $this->document();

        if ($document === null) {
            Log::warning('SendQualityControlPdfJob: la encuesta ya no existe', ['survey_id' => $this->surveyId]);

            return;
        }

        if ($this->channel === self::CHANNEL_EMAIL) {
            Mail::to($this->recipients)->send(new QualityControlPdfMail($document['title'], $document['path'], $document['filename'], $user?->name));
        } else {
            $share = QualityControlPdfService::shareCopy($document['contents']);
            $url = URL::temporarySignedRoute('quality-control.pdf.shared', now()->addHours(self::SHARE_HOURS), ['share' => $share]);
            $sent = NotificationController::sendWhatsAppDocumentUrl(
                $this->recipients[0],
                '📄 *'.$document['title'].'*'."\n".'Tu Doctor en Casa · Operaciones'.($user?->name ? "\n".'Enviado por '.$user->name : ''),
                $url,
                $document['filename'],
            );

            if (! $sent) {
                throw new RuntimeException('La API de WhatsApp no confirmó el envío del documento.');
            }
        }

        SecurityAudit::log('AUDIT_QUALITY_CONTROL_PDF_SENT', 'operations.quality-control.pdf.send', [
            'channel' => $this->channel,
            'recipients' => $this->recipients,
            'survey_id' => $this->surveyId,
            'report_surveys' => count($this->reportIds),
            'affiliate_type' => $this->affiliateType,
        ], $user);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendQualityControlPdfJob: falló el envío', [
            'channel' => $this->channel,
            'survey_id' => $this->surveyId,
            'message' => $exception?->getMessage(),
        ]);

        $user = User::query()->find($this->userId);

        if ($user !== null) {
            Notification::make()
                ->title('No se pudo enviar el PDF de Quality Control')
                ->body('El envío por '.($this->channel === self::CHANNEL_EMAIL ? 'correo' : 'WhatsApp').' a '.implode(', ', $this->recipients).' falló. Revise el destino e intente de nuevo.')
                ->danger()
                ->sendToDatabase($user);
        }
    }

    /**
     * @return array{path: string, contents: string, filename: string, title: string}|null
     */
    private function document(): ?array
    {
        $type = QualityControlAffiliateType::from($this->affiliateType);

        if ($this->surveyId !== null) {
            $survey = QualityControlSurvey::query()->find($this->surveyId);

            if (! $survey instanceof QualityControlSurvey) {
                return null;
            }

            return [...QualityControlPdfService::surveyDocument($survey), 'title' => 'Encuesta de Quality Control de '.$survey->patient_name];
        }

        return [...QualityControlPdfService::reportDocument($type, $this->reportIds, $this->reportLabel), 'title' => 'Informe de Quality Control '.$type->label()];
    }
}
