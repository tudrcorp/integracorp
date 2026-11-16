<?php

declare(strict_types=1);

namespace App\Support\InternalNotifications;

/**
 * Documento que acompaña a un aviso interno: la ruta local para subirlo a Telegram
 * y la URL pública para el respaldo por WhatsApp.
 */
final class InternalAttachment
{
    public function __construct(
        public readonly string $path,
        public readonly string $name,
        public readonly ?string $url = null,
    ) {}
}
