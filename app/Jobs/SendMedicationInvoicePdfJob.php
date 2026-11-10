<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Controllers\NotificationController;
use App\Mail\MedicationInvoicePdfMail;
use App\Models\OperationMedicationInvoice;
use App\Models\OperationMedicationInvoiceEvent;
use App\Models\User;
use App\Services\MedicationInvoicePdfService;
use App\Support\Operations\MedicationInvoiceIssuer;
use App\Support\Operations\MedicationInvoiceLifecycle;
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
 * Envía por correo o WhatsApp el PDF de una factura de medicamentos. Genera el PDF en el worker
 * con la misma copia por huella que la vista previa. WhatsApp recibe el documento por un enlace
 * firmado que vence a las 48 horas. Cada envío exitoso queda en la bitácora de la factura.
 */
class SendMedicationInvoicePdfJob implements ShouldQueue
{
    use Queueable;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const SHARE_HOURS = 48;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @param  list<string>  $recipients  correos o un teléfono
     */
    public function __construct(
        public string $channel,
        public array $recipients,
        public int $userId,
        public int $invoiceId,
    ) {}

    public function handle(): void
    {
        $user = User::query()->find($this->userId);
        $invoice = OperationMedicationInvoice::query()->with('items')->find($this->invoiceId);

        if (! $invoice instanceof OperationMedicationInvoice) {
            Log::warning('SendMedicationInvoicePdfJob: la factura ya no existe', ['invoice_id' => $this->invoiceId]);

            return;
        }

        if ($invoice->status === OperationMedicationInvoice::STATUS_ANNULLED) {
            Log::info('SendMedicationInvoicePdfJob: la factura se anuló antes del envío', ['invoice_id' => $this->invoiceId]);

            return;
        }

        $document = MedicationInvoicePdfService::document($invoice);
        $total = 'US$ '.number_format((float) $invoice->total_usd, 2, ',', '.');
        $statusLabel = MedicationInvoiceLifecycle::statusLabel($invoice->status);

        if ($this->channel === self::CHANNEL_EMAIL) {
            Mail::to($this->recipients)->send(new MedicationInvoicePdfMail(
                (string) $invoice->invoice_number,
                (string) $invoice->patient_name,
                $total,
                $statusLabel,
                $document['path'],
                $document['filename'],
                $user?->name,
            ));
        } else {
            $share = MedicationInvoicePdfService::shareCopy($document['contents']);
            $url = URL::temporarySignedRoute('medication-invoices.pdf.shared', now()->addHours(self::SHARE_HOURS), ['share' => $share]);
            $sent = NotificationController::sendWhatsAppDocumentUrl(
                $this->recipients[0],
                '🧾 *Factura '.$invoice->invoice_number.' · '.OperationMedicationInvoice::typeLabel($invoice->invoice_type).'*'."\n"
                    .$invoice->patient_name.' · '.$total.' · '.$statusLabel."\n"
                    .'Tu Doctor en Casa · Operaciones'.($user?->name ? "\n".'Enviada por '.$user->name : ''),
                $url,
                $document['filename'],
            );

            if (! $sent) {
                throw new RuntimeException('La API de WhatsApp no confirmó el envío del documento.');
            }
        }

        $channelLabel = $this->channel === self::CHANNEL_EMAIL ? 'correo' : 'WhatsApp';

        OperationMedicationInvoiceEvent::query()->create([
            'operation_medication_invoice_id' => $invoice->getKey(),
            'event' => OperationMedicationInvoiceEvent::EVENT_SENT,
            'description' => 'PDF enviado por '.$channelLabel.' a '.implode(', ', $this->recipients).($user?->name ? ' por '.$user->name : '').'.',
            'payload' => ['channel' => $this->channel, 'recipients' => $this->recipients, 'fingerprint' => $document['fingerprint']],
            'user_id' => $user?->getKey(),
            'user_name' => $user?->name,
        ]);

        SecurityAudit::log('AUDIT_OPERATIONS_MEDICATION_INVOICE_PDF_SENT', MedicationInvoiceIssuer::AUDIT_ROUTE, [
            'operation_medication_invoice_id' => $invoice->getKey(),
            'channel' => $this->channel,
            'recipients' => $this->recipients,
        ], $user);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendMedicationInvoicePdfJob: falló el envío', [
            'channel' => $this->channel,
            'invoice_id' => $this->invoiceId,
            'message' => $exception?->getMessage(),
        ]);

        $user = User::query()->find($this->userId);

        if ($user !== null) {
            Notification::make()
                ->title('No se pudo enviar la factura al paciente')
                ->body('El envío por '.($this->channel === self::CHANNEL_EMAIL ? 'correo' : 'WhatsApp').' a '.implode(', ', $this->recipients).' falló. Revise el destino e intente de nuevo.')
                ->danger()
                ->sendToDatabase($user);
        }
    }
}
