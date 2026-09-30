<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineCaseMessage;
use App\Models\User;
use App\Support\Operations\CaseFollowUpChatManager;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisa en la campana de Filament a todos los que pueden ver el caso en el chat (Operaciones y
 * Telemedicina) que INTEGRACORP publicó un resumen. Excluye al autor de la consulta.
 */
final class ConsultationChatSummaryNotifier
{
    /** Evento que abre el chat de seguimiento en el caso indicado. */
    public const OPEN_CHAT_EVENT = 'operations-case-chat-open';

    /**
     * Notifica solo si el mensaje aún no fue notificado; deja la marca en `meta.notified_at`
     * para que un reintento del trabajo no repita los avisos.
     */
    public static function notifyOnce(TelemedicineCaseMessage $message): int
    {
        $meta = is_array($message->meta) ? $message->meta : [];

        if (filled($meta['notified_at'] ?? null)) {
            return 0;
        }

        $sent = self::notify($message);

        $message->forceFill([
            'meta' => [...$meta, 'notified_at' => now()->toIso8601String(), 'notified_count' => $sent],
        ])->saveQuietly();

        return $sent;
    }

    /**
     * @return int cantidad de usuarios notificados
     */
    public static function notify(TelemedicineCaseMessage $message): int
    {
        $case = TelemedicineCase::query()->find($message->telemedicine_case_id);

        if (! $case instanceof TelemedicineCase || $case->status !== CaseFollowUpChatManager::FOLLOW_UP_STATUS) {
            return 0;
        }

        $recipients = self::recipients($case, (int) $message->user_id);

        if ($recipients->isEmpty()) {
            return 0;
        }

        $title = (string) ($message->meta['title'] ?? 'Resumen de consulta');
        $subtitle = (string) ($message->meta['subtitle'] ?? '');

        $notification = Notification::make()
            ->title('Nuevo resumen de consulta · '.($case->code ?? 'Caso #'.$case->id))
            ->body(ConsultationChatSummary::AUTHOR_LABEL.' publicó el '.mb_strtolower($title).' en el chat del caso.'.($subtitle !== '' ? ' '.$subtitle.'.' : ''))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->iconColor('info')
            ->actions([
                Action::make('openCaseChat')
                    ->label('Abrir chat')
                    ->button()
                    ->markAsRead()
                    ->dispatch(self::OPEN_CHAT_EVENT, ['caseId' => (int) $case->id]),
            ]);

        $sent = 0;

        foreach ($recipients as $recipient) {
            try {
                $recipient->notifyNow($notification->toDatabase());
                $sent++;
            } catch (Throwable $exception) {
                Log::warning('No se pudo notificar el resumen de consulta a un usuario.', [
                    'user_id' => $recipient->id,
                    'telemedicine_case_message_id' => $message->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Usuarios activos que pueden entrar a Operaciones o Telemedicina y ver este caso en el chat.
     *
     * @return Collection<int, User>
     */
    public static function recipients(TelemedicineCase $case, int $authorUserId): Collection
    {
        $operations = self::panel('operations');
        $telemedicine = self::panel('telemedicina');

        return User::query()
            ->where('status', 'ACTIVO')
            ->whereKeyNot($authorUserId)
            ->where(function (Builder $candidates) use ($case): void {
                /**
                 * Prefiltro amplio con LIKE: hay filas históricas con `departament` en JSON inválido y
                 * JSON_CONTAINS haría fallar toda la consulta. El filtro exacto lo hace canSeeCase().
                 */
                foreach (['OPERACIONES', 'TELEMEDICINA', 'SUPERADMIN'] as $department) {
                    $candidates->orWhere('departament', 'like', '%'.$department.'%');
                }

                if (filled($case->supplier_id)) {
                    $candidates->orWhere('supplier_id', (int) $case->supplier_id);
                }
            })
            ->get()
            ->filter(fn (User $user): bool => self::canSeeCase($user, $case, $operations, $telemedicine))
            ->values();
    }

    private static function canSeeCase(User $user, TelemedicineCase $case, ?Panel $operations, ?Panel $telemedicine): bool
    {
        try {
            if ($operations !== null
                && $user->canAccessPanel($operations)
                && CaseFollowUpChatManager::canAccessCase($user, $case, CaseFollowUpChatManager::CONTEXT_OPERATIONS)) {
                return true;
            }

            return $telemedicine !== null
                && $user->canAccessPanel($telemedicine)
                && CaseFollowUpChatManager::canAccessCase($user, $case, CaseFollowUpChatManager::CONTEXT_TELEMEDICINE);
        } catch (Throwable) {
            return false;
        }
    }

    private static function panel(string $id): ?Panel
    {
        try {
            return Filament::getPanel($id);
        } catch (Throwable) {
            return null;
        }
    }
}
