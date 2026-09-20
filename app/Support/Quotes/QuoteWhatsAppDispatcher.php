<?php

declare(strict_types=1);

namespace App\Support\Quotes;

use App\Enums\QuoteWhatsAppNotification;
use App\Jobs\SendQuoteWhatsAppNotificationJob;
use Illuminate\Support\Facades\Auth;

/**
 * Pone en cola un aviso de WhatsApp del módulo de cotizaciones.
 *
 * Siempre `afterCommit()`: los paneles corren con `databaseTransactions()`, así
 * que despachar dentro de la transacción dejaría al worker leyendo una
 * cotización que todavía no existe —o avisando de una que acabó revertida.
 */
final class QuoteWhatsAppDispatcher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function queue(QuoteWhatsAppNotification $notification, array $payload, ?string $url = null): void
    {
        if ($url !== null && $url !== '') {
            $payload['url'] = $url;
        }

        SendQuoteWhatsAppNotificationJob::dispatch(
            $notification,
            $payload,
            Auth::id() !== null ? (int) Auth::id() : null,
        )->afterCommit();
    }
}
