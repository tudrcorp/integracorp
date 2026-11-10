<?php

declare(strict_types=1);

namespace App\Support\Affiliations\AffiliateFicha;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Aviso en el panel al analista que pidió enviar una ficha: el envío corre en
 * cola, así que el resultado le llega como notificación de base de datos.
 * Un fallo al avisar nunca debe hacer fallar (y reintentar) un envío ya hecho.
 */
final class AffiliateFichaNotifier
{
    public static function sent(?User $user, string $body): void
    {
        self::notify($user, 'Ficha del afiliado enviada', $body, success: true);
    }

    public static function failed(?User $user, string $body): void
    {
        self::notify($user, 'No se pudo enviar la ficha del afiliado', $body, success: false);
    }

    private static function notify(?User $user, string $title, string $body, bool $success): void
    {
        if ($user === null) {
            return;
        }

        try {
            $notification = Notification::make()->title($title)->body($body);
            ($success ? $notification->success() : $notification->danger())->sendToDatabase($user);
        } catch (Throwable $exception) {
            Log::warning('Ficha del afiliado: no se pudo avisar al analista.', [
                'user_id' => $user->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
