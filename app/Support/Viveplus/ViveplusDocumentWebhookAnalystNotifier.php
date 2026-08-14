<?php

declare(strict_types=1);

namespace App\Support\Viveplus;

use App\Exceptions\ViveplusDocumentWebhookPermanentException;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class ViveplusDocumentWebhookAnalystNotifier
{
    public function notifyDeliveryFailed(
        ?int $userId,
        string $affiliationCode,
        string $documentType,
        string $reason,
    ): void {
        Log::error('Viveplus document webhook: el documento no llegó a ViVEplus', [
            'affiliation_code' => $affiliationCode,
            'document_type' => $documentType,
            'reason' => $reason,
            'notified_user_id' => $userId,
        ]);

        if ($userId === null) {
            return;
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            return;
        }

        try {
            Notification::make()
                ->title('Documento no llegó a ViVEplus')
                ->body("El {$documentType} de la afiliación {$affiliationCode} no pudo entregarse a ViVEplus. {$reason}")
                ->danger()
                ->sendToDatabase($user);
        } catch (Throwable $exception) {
            Log::error('Viveplus document webhook: no se pudo notificar al analista', [
                'affiliation_code' => $affiliationCode,
                'document_type' => $documentType,
                'notified_user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function reasonFromException(Throwable $exception): string
    {
        if ($exception instanceof ViveplusDocumentWebhookPermanentException && $exception->statusCode === 401) {
            return 'Credenciales inválidas. Un administrador debe revisar la rotación del token o la firma.';
        }

        if ($exception instanceof ViveplusDocumentWebhookPermanentException && $exception->statusCode === 422) {
            $detail = is_string($exception->errors)
                ? $exception->errors
                : json_encode($exception->errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return 'ViVEplus rechazó el payload (422)'.($detail ? ": {$detail}" : '.');
        }

        return $exception->getMessage();
    }
}
