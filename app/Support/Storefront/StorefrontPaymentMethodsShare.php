<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Jobs\SendStorefrontPaymentMethodsShareJob;

/**
 * Destinatarios y cola de envío del PDF de métodos de pago.
 *
 * @phpstan-type Recipient array{channel: string, value: string}
 */
final class StorefrontPaymentMethodsShare
{
    public const MAX_RECIPIENTS = 8;

    /**
     * @return list<Recipient>
     */
    public static function seed(): array
    {
        return [StorefrontQuoteShare::emptyRecipient(StorefrontQuoteShare::CHANNEL_WHATSAPP)];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<Recipient>
     */
    public static function normalize(array $rows): array
    {
        return StorefrontQuoteShare::normalize($rows);
    }

    /**
     * @param  list<Recipient>  $recipients
     */
    public static function queue(array $recipients): int
    {
        $count = 0;

        foreach ($recipients as $row) {
            SendStorefrontPaymentMethodsShareJob::dispatch(
                $row['channel'],
                $row['value'],
            );
            $count++;
        }

        return $count;
    }
}
