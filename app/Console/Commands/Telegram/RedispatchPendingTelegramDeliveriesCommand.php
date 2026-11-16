<?php

declare(strict_types=1);

namespace App\Console\Commands\Telegram;

use App\Enums\NotificationDeliveryChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Models\NotificationDelivery;
use App\Support\InternalNotifications\InternalNotificationDispatcher;
use Illuminate\Console\Command;

/**
 * Red de seguridad: vuelve a encolar las entregas por Telegram que siguen en
 * cola después de varios minutos (por ejemplo, si el job se perdió al reiniciar
 * Redis). El job es idempotente, así que encolarlo de más no duplica el mensaje.
 */
class RedispatchPendingTelegramDeliveriesCommand extends Command
{
    protected $signature = 'telegram:redispatch-pending {--minutes=10 : Antigüedad mínima de una entrega pendiente}';

    protected $description = 'Vuelve a encolar entregas por Telegram que quedaron pendientes';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $count = 0;

        NotificationDelivery::query()
            ->where('channel', NotificationDeliveryChannel::Telegram->value)
            ->where('status', NotificationDeliveryStatus::Pending->value)
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('id')
            ->limit(500)
            ->pluck('id')
            ->each(function (int $id) use (&$count): void {
                InternalNotificationDispatcher::queueTelegram($id);
                $count++;
            });

        $this->info('Entregas reencoladas: '.$count.'.');

        return self::SUCCESS;
    }
}
