<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Jobs\NotifyAdministrationOfStorefrontQuotePaymentReceiptJob;

final class StorefrontQuotePaymentReceiptNotifier
{
    public static function notify(int $receiptId): void
    {
        NotifyAdministrationOfStorefrontQuotePaymentReceiptJob::dispatch($receiptId);
    }

    public static function contactPhone(): string
    {
        try {
            $phones = \App\Support\SystemNotificationRecipients::phones(
                \App\Enums\SystemNotificationKey::StorefrontQuotePaymentReceipt
            );
        } catch (\Throwable) {
            return '';
        }

        $raw = (string) ($phones[0] ?? '');
        $normalized = \App\Services\HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($raw);

        if ($normalized !== null) {
            return $normalized;
        }

        return preg_replace('/\D+/', '', $raw) ?: '';
    }
}
