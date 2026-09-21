<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SystemNotificationKey;
use App\Mail\StorefrontQuotePaymentReceiptMail;
use App\Models\IndividualQuotePaymentReceipt;
use App\Models\User;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\SecurityAudit;
use App\Support\Storefront\StorefrontQuotePaymentReceiptNotificationMessage;
use App\Support\SystemNotificationRecipients;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyAdministrationOfStorefrontQuotePaymentReceiptJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    private const AUDIT_ROUTE = 'storefront.quote.payment-receipt';

    public int $tries = 3;

    public int $uniqueFor = 300;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [20, 60, 180];
    }

    public function __construct(
        public int $receiptId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->receiptId;
    }

    public function handle(): void
    {
        $receipt = IndividualQuotePaymentReceipt::query()
            ->with(['quote', 'uploader'])
            ->find($this->receiptId);

        if (! $receipt instanceof IndividualQuotePaymentReceipt || $receipt->quote === null) {
            Log::warning('NotifyAdministrationOfStorefrontQuotePaymentReceiptJob: comprobante no encontrado', [
                'receipt_id' => $this->receiptId,
            ]);

            return;
        }

        $key = SystemNotificationKey::StorefrontQuotePaymentReceipt;

        if (! SystemNotificationRecipients::isActive($key)) {
            SecurityAudit::log('AUDIT_STOREFRONT_QUOTE_RECEIPT_NOTIFICATION_SKIPPED', self::AUDIT_ROUTE, [
                'receipt_id' => $receipt->getKey(),
                'quote_code' => $receipt->quote->code,
                'reason' => 'notification_inactive',
            ]);

            return;
        }

        $emails = SystemNotificationRecipients::emails($key);
        $phones = SystemNotificationRecipients::phones($key);

        if ($emails === [] && $phones === []) {
            SecurityAudit::log('AUDIT_STOREFRONT_QUOTE_RECEIPT_NOTIFICATION_SKIPPED', self::AUDIT_ROUTE, [
                'receipt_id' => $receipt->getKey(),
                'quote_code' => $receipt->quote->code,
                'reason' => 'no_recipients_configured',
            ]);

            return;
        }

        $uploader = $receipt->uploader;
        if ($uploader === null) {
            Log::warning('NotifyAdministrationOfStorefrontQuotePaymentReceiptJob: usuario no encontrado', [
                'receipt_id' => $this->receiptId,
            ]);

            return;
        }

        $emailsSent = $this->sendEmails($receipt, $emails, $uploader);
        $whatsappsQueued = $this->sendWhatsApps($receipt, $phones, $uploader);

        SecurityAudit::log('AUDIT_STOREFRONT_QUOTE_RECEIPT_NOTIFICATION_DISPATCHED', self::AUDIT_ROUTE, [
            'receipt_id' => $receipt->getKey(),
            'quote_code' => $receipt->quote->code,
            'user_id' => $uploader->getKey(),
            'emails_sent' => $emailsSent,
            'whatsapps_queued' => $whatsappsQueued,
            'emails_targeted' => count($emails),
            'phones_targeted' => count($phones),
        ]);
    }

    /**
     * @param  list<string>  $emails
     */
    private function sendEmails(IndividualQuotePaymentReceipt $receipt, array $emails, User $uploader): int
    {
        if ($emails === []) {
            return 0;
        }

        $quote = $receipt->quote;
        $payload = StorefrontQuotePaymentReceiptNotificationMessage::emailPayload($quote, $receipt, $uploader);
        $subject = StorefrontQuotePaymentReceiptNotificationMessage::emailSubject($quote);
        $absolute = $receipt->absolutePath();
        $sent = 0;

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            try {
                Mail::to($email)->send(new StorefrontQuotePaymentReceiptMail(
                    emailPayload: $payload,
                    recipientEmail: $email,
                    subjectLine: $subject,
                    receiptPath: $absolute,
                    receiptFilename: (string) ($receipt->original_name ?: 'comprobante'),
                ));
                $sent++;
            } catch (Throwable $exception) {
                Log::error('NotifyAdministrationOfStorefrontQuotePaymentReceiptJob: error enviando email', [
                    'receipt_id' => $receipt->getKey(),
                    'email' => $email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * @param  list<string>  $phones
     */
    private function sendWhatsApps(IndividualQuotePaymentReceipt $receipt, array $phones, User $uploader): int
    {
        if ($phones === []) {
            return 0;
        }

        $quote = $receipt->quote;
        $body = StorefrontQuotePaymentReceiptNotificationMessage::whatsappBody($quote, $receipt, $uploader);
        $caption = StorefrontQuotePaymentReceiptNotificationMessage::whatsappCaption($quote);
        $publicUrl = $receipt->publicUrl();
        $filename = (string) ($receipt->original_name ?: 'comprobante');
        $queued = 0;

        foreach ($phones as $rawPhone) {
            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

            if ($phone === null) {
                continue;
            }

            $context = [
                'panel' => 'business',
                'source' => self::AUDIT_ROUTE,
                'receipt_id' => $receipt->getKey(),
                'quote_code' => $quote->code,
            ];

            try {
                $jobs = [
                    new SendNotificacionWhatsApp(null, $body, $phone, null, $context),
                ];

                if (is_string($publicUrl) && $publicUrl !== '' && ! str_contains($publicUrl, '.test')) {
                    $jobs[] = StorefrontQuotePaymentReceiptNotificationMessage::isImage($receipt)
                        ? new SendNotificacionWhatsApp(null, $caption, $phone, null, [...$context, 'asset' => 'receipt'], $publicUrl)
                        : new SendNotificacionWhatsAppDocument(null, $caption, $phone, $publicUrl, $filename, [...$context, 'asset' => 'receipt']);
                }

                Bus::chain($jobs)->dispatch();
                $queued++;
            } catch (Throwable $exception) {
                Log::error('NotifyAdministrationOfStorefrontQuotePaymentReceiptJob: error encolando WhatsApp', [
                    'receipt_id' => $receipt->getKey(),
                    'phone' => $phone,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $queued;
    }
}
