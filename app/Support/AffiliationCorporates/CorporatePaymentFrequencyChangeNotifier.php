<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Jobs\NotifyAdministrationOfCorporatePaymentFrequencyChangeJob;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Encola el aviso a Administración. Nunca rompe el flujo que lo llama: el
 * cambio ya quedó guardado y registrado; si la cola falla, queda en el log y
 * el registro sigue visible en Administración.
 */
final class CorporatePaymentFrequencyChangeNotifier
{
    /**
     * @param  list<int>  $changeIds
     */
    public static function applied(array $changeIds): void
    {
        self::dispatch($changeIds, CorporatePaymentFrequencyChangeNotificationMessage::EVENT_APPLIED);
    }

    public static function reversed(int $changeId): void
    {
        self::dispatch([$changeId], CorporatePaymentFrequencyChangeNotificationMessage::EVENT_REVERSED);
    }

    /**
     * @param  list<int>  $changeIds
     */
    private static function dispatch(array $changeIds, string $event): void
    {
        $changeIds = array_values(array_unique(array_filter(array_map('intval', $changeIds), fn (int $id): bool => $id > 0)));

        if ($changeIds === []) {
            return;
        }

        try {
            NotifyAdministrationOfCorporatePaymentFrequencyChangeJob::dispatch($changeIds, $event)->afterCommit();
        } catch (Throwable $throwable) {
            Log::error('CAMBIO-FRECUENCIA-PAGO: no se pudo encolar el aviso a Administración.', [
                'change_ids' => $changeIds,
                'event' => $event,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
