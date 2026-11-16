<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\NotificationDeliveries\Pages;

use App\Filament\Business\Resources\NotificationDeliveries\NotificationDeliveryResource;
use Filament\Resources\Pages\ListRecords;

class ListNotificationDeliveries extends ListRecords
{
    protected static string $resource = NotificationDeliveryResource::class;

    public function getSubheading(): ?string
    {
        return 'Cada aviso interno queda registrado antes de salir. Si Telegram no lo entrega, verá debajo el envío de respaldo por WhatsApp o correo y el motivo.';
    }
}
