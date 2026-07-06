<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\SendNotificationMasive;
use App\Jobs\SendNotificationMasiveEmail;
use App\Models\DataNotification;
use App\Models\MassNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MassNotificationDispatchService
{
    public static function dispatch(MassNotification $record): MassNotificationDispatchResult
    {
        if (! self::isApproved($record)) {
            return new MassNotificationDispatchResult(
                success: false,
                message: 'La notificación debe estar aprobada antes de enviarse.',
            );
        }

        $recipients = DataNotification::query()
            ->where('mass_notification_id', $record->id)
            ->get();

        if ($recipients->isEmpty()) {
            return new MassNotificationDispatchResult(
                success: false,
                message: 'No hay destinatarios asociados a esta notificación.',
            );
        }

        $channels = collect($record->channels ?? []);
        if ($channels->isEmpty()) {
            return new MassNotificationDispatchResult(
                success: false,
                message: 'No hay canales de envío configurados.',
            );
        }

        $infoNotificationArray = $record->toArray();
        $queuedJobs = 0;

        /** @var DataNotification $recipient */
        foreach ($recipients as $recipient) {
            $queuedJobs += self::queueRecipientChannels($recipient, $channels, $infoNotificationArray, $record);
        }

        if ($queuedJobs === 0) {
            return new MassNotificationDispatchResult(
                success: false,
                message: 'No se encoló ningún envío. Verifica que los destinatarios tengan email o teléfono según el canal.',
            );
        }

        $record->is_sent = true;
        $record->save();

        return new MassNotificationDispatchResult(
            success: true,
            message: 'Envío encolado exitosamente. Integracorp te notificará cuando el proceso finalice.',
            queuedJobs: $queuedJobs,
        );
    }

    /**
     * @return Collection<int, MassNotification>
     */
    public static function dueScheduledNotifications(): Collection
    {
        $query = MassNotification::query()
            ->whereNotNull('date_programed')
            ->where('date_programed', '<=', now())
            ->where('is_sent', false);

        self::applyApprovedScope($query);

        return $query->get();
    }

    private static function isApproved(MassNotification $record): bool
    {
        $hasStatus = Schema::hasColumn('mass_notifications', 'status');
        $hasIsApproved = Schema::hasColumn('mass_notifications', 'is_approved');

        if ($hasIsApproved && (bool) $record->is_approved) {
            return true;
        }

        if ($hasStatus && $record->status === 'APROBADA') {
            return true;
        }

        return false;
    }

    private static function applyApprovedScope(Builder $query): void
    {
        $hasStatus = Schema::hasColumn('mass_notifications', 'status');
        $hasIsApproved = Schema::hasColumn('mass_notifications', 'is_approved');

        if ($hasStatus && $hasIsApproved) {
            $query->where(function ($inner): void {
                $inner->where('status', 'APROBADA')
                    ->orWhere('is_approved', true);
            });

            return;
        }

        if ($hasStatus) {
            $query->where('status', 'APROBADA');

            return;
        }

        if ($hasIsApproved) {
            $query->where('is_approved', true);
        }
    }

    /**
     * @param  Collection<int, string>  $channels
     */
    private static function queueRecipientChannels(
        DataNotification $recipient,
        Collection $channels,
        array $infoNotificationArray,
        MassNotification $record,
    ): int {
        $queuedJobs = 0;

        if ($channels->contains('whatsapp')) {
            if (filled($recipient->phone)) {
                MassNotificationRecipientDelivery::markWhatsappPending($recipient->id);
                SendNotificationMasive::dispatch(
                    $recipient->toArray(),
                    $infoNotificationArray,
                    $recipient->id,
                )->onQueue('system');
                $queuedJobs++;
            } else {
                MassNotificationRecipientDelivery::markWhatsappSkipped($recipient->id, 'Teléfono vacío o no disponible');
            }
        }

        if ($channels->contains('email')) {
            if (filled($recipient->email)) {
                MassNotificationRecipientDelivery::markEmailPending($recipient->id);
                SendNotificationMasiveEmail::dispatch(
                    $recipient->email,
                    $record,
                    $recipient->id,
                )->onQueue('system');
                $queuedJobs++;
            } else {
                MassNotificationRecipientDelivery::markEmailSkipped($recipient->id, 'Correo vacío o no disponible');
            }
        }

        return $queuedJobs;
    }
}
