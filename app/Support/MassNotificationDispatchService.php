<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\SendNotificationMasive;
use App\Jobs\SendNotificationMasiveEmail;
use App\Jobs\SweepMassNotificationWhatsAppFailures;
use App\Models\DataNotification;
use App\Models\MassNotification;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
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

        if ($record->is_sent) {
            return new MassNotificationDispatchResult(
                success: false,
                message: 'Esta notificación ya fue encolada para envío.',
            );
        }

        if ($record->isScheduledForFuture()) {
            return new MassNotificationDispatchResult(
                success: true,
                message: sprintf(
                    'La notificación está programada para el %s. El envío se ejecutará automáticamente en esa fecha.',
                    $record->date_programed->format('d/m/Y H:i'),
                ),
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
        $whatsappJobs = [];

        /** @var DataNotification $recipient */
        foreach ($recipients as $recipient) {
            $queuedJobs += self::queueRecipientChannels(
                $recipient,
                $channels,
                $infoNotificationArray,
                $record,
                $whatsappJobs,
            );
        }

        if ($queuedJobs === 0) {
            return new MassNotificationDispatchResult(
                success: false,
                message: 'No se encoló ningún envío. Verifica que los destinatarios tengan email o teléfono según el canal.',
            );
        }

        if ($whatsappJobs !== []) {
            $massNotificationId = $record->id;

            Bus::batch($whatsappJobs)
                ->name('mass-notification-whatsapp-'.$massNotificationId)
                ->onQueue('system')
                ->allowFailures()
                ->finally(function (Batch $batch) use ($massNotificationId): void {
                    SweepMassNotificationWhatsAppFailures::dispatch($massNotificationId)
                        ->onQueue('system');
                })
                ->dispatch();
        }

        $record->is_sent = true;
        $record->save();

        return new MassNotificationDispatchResult(
            success: true,
            message: 'Envío encolado exitosamente. Al finalizar, se reintentarán automáticamente los WhatsApp pendientes o fallidos. Integracorp te notificará cuando el proceso finalice.',
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
     * @param  list<SendNotificationMasive>  $whatsappJobs
     */
    private static function queueRecipientChannels(
        DataNotification $recipient,
        Collection $channels,
        array $infoNotificationArray,
        MassNotification $record,
        array &$whatsappJobs,
    ): int {
        $queuedJobs = 0;

        if ($channels->contains('whatsapp')) {
            if (filled($recipient->phone)) {
                MassNotificationRecipientDelivery::markWhatsappPending($recipient->id);
                $whatsappJobs[] = new SendNotificationMasive(
                    $recipient->toArray(),
                    $infoNotificationArray,
                    $recipient->id,
                );
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
