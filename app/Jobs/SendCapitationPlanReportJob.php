<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Controllers\NotificationController;
use App\Mail\CapitationPlanReportMail;
use App\Models\User;
use App\Services\CapitationPlanPdfService;
use App\Support\SecurityAudit;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía el PDF de uno o varios planes de capitado por un solo canal (correo o WhatsApp).
 *
 * Un job por canal: si WhatsApp falla y se reintenta, el correo no se vuelve a enviar.
 * Al terminar avisa al analista por la campana del panel, con éxito o con el motivo del fallo.
 */
class SendCapitationPlanReportJob implements ShouldQueue
{
    use Queueable;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * @param  list<int>  $planIds
     */
    public function __construct(
        public array $planIds,
        public string $channel,
        public string $recipient,
        public ?string $message,
        public ?int $requestedByUserId,
    ) {
        $this->onQueue((string) config('affiliate-card.documents_queue', 'documents'));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        $plans = CapitationPlanPdfService::plans($this->planIds);

        if ($plans->isEmpty()) {
            Log::warning('SendCapitationPlanReportJob: los planes ya no existen', ['ids' => $this->planIds]);
            $this->notifyRequester(false, 'Los planes seleccionados ya no existen; no se envió nada.');

            return;
        }

        $title = CapitationPlanPdfService::title($plans);

        if ($this->channel === self::CHANNEL_EMAIL) {
            Mail::to($this->recipient)->send(new CapitationPlanReportMail(
                documentTitle: $title,
                planNames: $plans->pluck('name')->map(fn (mixed $name): string => (string) $name)->all(),
                customMessage: $this->message,
                attachmentPath: CapitationPlanPdfService::path($plans),
                attachmentName: CapitationPlanPdfService::filename($plans),
            ));

            $this->audit(true, $plans->modelKeys());
            $this->notifyRequester(true, $title.' enviado por correo a '.$this->recipient.'.');

            return;
        }

        $relative = CapitationPlanPdfService::publishForWhatsApp($plans);
        $sent = NotificationController::sendWhatsAppDocument(
            $this->recipient,
            CapitationPlanPdfService::whatsAppCaption($plans, $this->message),
            $relative,
            CapitationPlanPdfService::filename($plans),
        );

        $this->audit($sent, $plans->modelKeys());
        $this->notifyRequester(
            $sent,
            $sent
                ? $title.' enviado por WhatsApp al '.$this->recipient.'.'
                : 'No se pudo enviar por WhatsApp al '.$this->recipient.'. Verifique el número e intente de nuevo.',
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendCapitationPlanReportJob: falló el envío', [
            'ids' => $this->planIds,
            'channel' => $this->channel,
            'recipient' => $this->recipient,
            'error' => $exception?->getMessage(),
        ]);

        $this->audit(false, $this->planIds);
        $this->notifyRequester(false, 'No se pudo enviar '.($this->channel === self::CHANNEL_EMAIL ? 'el correo' : 'el WhatsApp').' a '.$this->recipient.'. Intente de nuevo o avise a sistemas.');
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    private function audit(bool $sent, array $ids): void
    {
        SecurityAudit::log(
            'AUDIT_CAPITATION_PLAN_REPORT_'.strtoupper($this->channel).($sent ? '_SENT' : '_FAILED'),
            'business.capitation-plans.send',
            [
                'capitation_plan_ids' => array_values($ids),
                'channel' => $this->channel,
                'recipient' => $this->recipient,
            ],
            $this->requester(),
        );
    }

    private function notifyRequester(bool $success, string $body): void
    {
        $user = $this->requester();

        if (! $user instanceof User) {
            return;
        }

        try {
            Notification::make()
                ->title($success ? 'Plan de capitado enviado' : 'Envío de plan de capitado fallido')
                ->body($body)
                ->icon($success ? 'heroicon-o-paper-airplane' : 'heroicon-o-exclamation-triangle')
                ->color($success ? 'success' : 'danger')
                ->sendToDatabase($user);
        } catch (Throwable $exception) {
            Log::warning('SendCapitationPlanReportJob: no se pudo avisar al analista', ['error' => $exception->getMessage()]);
        }
    }

    private function requester(): ?User
    {
        return $this->requestedByUserId !== null ? User::query()->find($this->requestedByUserId) : null;
    }
}
