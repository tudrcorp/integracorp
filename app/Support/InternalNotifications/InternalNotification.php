<?php

declare(strict_types=1);

namespace App\Support\InternalNotifications;

use App\Enums\SystemNotificationKey;
use App\Support\Telegram\TelegramText;

/**
 * Un aviso interno listo para repartir: qué tipo es (su clave del Centro de
 * notificaciones), qué dice y si es crítico. Los críticos, si Telegram falla,
 * salen por WhatsApp y además por correo.
 */
final class InternalNotification
{
    /**
     * @param  bool  $bodyIsFullMessage  true cuando $body ya es el mensaje completo con
     *                                   formato de WhatsApp (título incluido), como el de las alertas existentes
     * @param  InternalAttachment|null  $attachment  documento que acompaña al aviso (PDF de un testigo)
     */
    public function __construct(
        public readonly SystemNotificationKey $key,
        public readonly string $title,
        public readonly string $body,
        public readonly bool $critical = false,
        public readonly ?string $actor = null,
        public readonly bool $withDatabaseNotification = true,
        public readonly bool $bodyIsFullMessage = false,
        public readonly ?InternalAttachment $attachment = null,
    ) {}

    /**
     * El mensaje tal como sale por WhatsApp; Telegram lo convierte con TelegramText::fromWhatsApp.
     */
    public function message(): string
    {
        return $this->bodyIsFullMessage ? trim($this->body) : TelegramText::whatsApp($this->title, $this->body);
    }
}
