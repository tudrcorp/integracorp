<?php

declare(strict_types=1);

namespace App\Support\AffiliateCard;

use App\Models\User;
use App\Support\Companies\CompanyAssociateDocumentsBellAlert;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AffiliateCarnetEmailNotifier
{
    public static function queuedBody(string $affiliationCode, int $queued, int $skipped): string
    {
        $body = 'Los carnets de la afiliación '.$affiliationCode.' se envían en segundo plano ('.$queued.' correo(s)).';
        $body .= ' Puede cerrar esta ventana y seguir trabajando. Le avisaremos aquí cuando termine.';

        if ($skipped > 0) {
            $body .= ' Se omitieron '.$skipped.' afiliado(s) sin correo o sin documentos.';
        }

        return $body;
    }

    public static function notifyQueued(int $userId, string $affiliationCode, int $queued, int $skipped): void
    {
        self::deliver(
            $userId,
            Notification::make()
                ->info()
                ->title('Envío de carnets en segundo plano')
                ->body(self::queuedBody($affiliationCode, $queued, $skipped)),
        );
    }

    public static function notifyCompletion(
        int $userId,
        string $affiliationCode,
        int $sent,
        int $failed,
        int $skipped,
    ): void {
        if ($failed > 0) {
            self::deliver(
                $userId,
                Notification::make()
                    ->danger()
                    ->title('Error al enviar carnets')
                    ->body(self::failureBody($affiliationCode, $sent, $failed, $skipped)),
            );

            return;
        }

        self::deliver(
            $userId,
            Notification::make()
                ->success()
                ->title('Carnets enviados')
                ->body(self::successBody($affiliationCode, $sent, $skipped)),
        );
    }

    public static function notifyImmediateFailure(int $userId, string $title, string $body): void
    {
        self::deliver(
            $userId,
            Notification::make()
                ->danger()
                ->title($title)
                ->body($body),
        );
    }

    private static function successBody(string $affiliationCode, int $sent, int $skipped): string
    {
        $body = 'Se enviaron '.$sent.' correo(s) con el carnet y el condicionado de la afiliación '.$affiliationCode.'.';

        if ($skipped > 0) {
            $body .= ' Se omitieron '.$skipped.' afiliado(s) sin correo o sin documentos.';
        }

        return $body;
    }

    private static function failureBody(string $affiliationCode, int $sent, int $failed, int $skipped): string
    {
        $body = 'No se pudieron enviar todos los carnets de la afiliación '.$affiliationCode.'.';
        $body .= ' Enviados: '.$sent.'. Con error: '.$failed.'.';

        if ($skipped > 0) {
            $body .= ' Omitidos: '.$skipped.'.';
        }

        $body .= ' Revise los documentos y reintente.';

        return $body;
    }

    private static function deliver(int $userId, Notification $notification): void
    {
        try {
            $user = User::query()->find($userId);

            if ($user === null) {
                return;
            }

            $user->notifyNow($notification->toDatabase());
            DatabaseNotificationsSent::dispatch($user);
            CompanyAssociateDocumentsBellAlert::markPending($userId);
        } catch (Throwable $exception) {
            Log::warning('AffiliateCarnetEmailNotifier: no se pudo notificar al analista', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
