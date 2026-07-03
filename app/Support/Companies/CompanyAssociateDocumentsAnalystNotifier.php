<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Models\CompanyAssociate;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CompanyAssociateDocumentsAnalystNotifier
{
    /**
     * @param  array{preview_url: string}  $carnet
     */
    public static function notifySuccess(User $user, CompanyAssociate $associate, array $carnet): void
    {
        self::deliver(
            $user,
            Notification::make()
                ->success()
                ->title(CompanyAssociateDocumentsGeneratedNotificationMessage::title())
                ->body(CompanyAssociateDocumentsGeneratedNotificationMessage::toastBody($associate))
                ->actions([
                    Action::make('viewAssociate')
                        ->label('Ver asociado')
                        ->url(CompanyAssociatesTableContext::associateViewUrl($associate)),
                    Action::make('openCarnet')
                        ->label('Abrir carnet')
                        ->url($carnet['preview_url'])
                        ->openUrlInNewTab(),
                ]),
            'success',
            $associate->getKey(),
        );
    }

    public static function notifyFailure(User $user, CompanyAssociate $associate, string $error): void
    {
        self::deliver(
            $user,
            Notification::make()
                ->danger()
                ->title(CompanyAssociateDocumentsGeneratedNotificationMessage::failureTitle())
                ->body(CompanyAssociateDocumentsGeneratedNotificationMessage::failureBody($associate, $error))
                ->actions([
                    Action::make('viewAssociate')
                        ->label('Ver asociado')
                        ->url(CompanyAssociatesTableContext::associateViewUrl($associate)),
                ]),
            'failure',
            $associate->getKey(),
        );
    }

    private static function deliver(User $user, Notification $notification, string $context, int $associateId): void
    {
        try {
            $user->notifyNow($notification->toDatabase());
            DatabaseNotificationsSent::dispatch($user);
            CompanyAssociateDocumentsBellAlert::markPending($user->getKey());
        } catch (Throwable $exception) {
            Log::error('CompanyAssociateDocumentsAnalystNotifier: no se pudo guardar la notificación en base de datos', [
                'associate_id' => $associateId,
                'user_id' => $user->getKey(),
                'context' => $context,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            $user->notifyNow($notification->toBroadcast());
        } catch (Throwable $exception) {
            Log::warning('CompanyAssociateDocumentsAnalystNotifier: no se pudo enviar toast en tiempo real', [
                'associate_id' => $associateId,
                'user_id' => $user->getKey(),
                'context' => $context,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
