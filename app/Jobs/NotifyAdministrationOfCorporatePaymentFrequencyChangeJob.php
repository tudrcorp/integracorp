<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\CorporatePaymentFrequencyChangeMail;
use App\Models\AffiliationCorporatePaymentFrequencyChange;
use App\Models\User;
use App\Support\AffiliationCorporates\CorporatePaymentFrequencyChangeNotificationMessage as Message;
use App\Support\AffiliationCorporates\CorporatePaymentFrequencyChangeRecipients;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Avisa a Administración de un cambio de frecuencia de pago (o de su reverso)
 * por correo, WhatsApp y notificación del panel.
 *
 * Idempotente: cada evento se anota en `notification_log` del registro y un
 * reintento no vuelve a enviar lo que ya salió. No usa ShouldBeUnique a
 * propósito: depende de un bloqueo en caché y, si la caché falla, el aviso se
 * descartaría en silencio.
 */
class NotifyAdministrationOfCorporatePaymentFrequencyChangeJob implements ShouldQueue
{
    use Queueable;

    private const AUDIT_ROUTE = 'business.affiliation-corporates.payment-frequency.notify';

    public int $tries = 3;

    /**
     * @param  list<int>  $changeIds
     */
    public function __construct(
        public array $changeIds,
        public string $event,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        /** @var Collection<int, AffiliationCorporatePaymentFrequencyChange> $changes */
        $changes = AffiliationCorporatePaymentFrequencyChange::query()
            ->whereIn('id', $this->changeIds)
            ->orderBy('id')
            ->get();

        if ($changes->isEmpty()) {
            Log::warning('NotifyAdministrationOfCorporatePaymentFrequencyChangeJob: registros no encontrados', [
                'change_ids' => $this->changeIds,
                'event' => $this->event,
            ]);

            return;
        }

        $pending = $changes->filter(fn (AffiliationCorporatePaymentFrequencyChange $change): bool => ! isset(($change->notification_log ?? [])[$this->event]['completed_at']));

        if ($pending->isEmpty()) {
            return;
        }

        $extraUserIds = $this->event === Message::EVENT_REVERSED
            ? $pending->pluck('performed_by_id')->filter()->map(fn (mixed $id): int => (int) $id)->values()->all()
            : [];

        $recipients = CorporatePaymentFrequencyChangeRecipients::resolve($extraUserIds);

        $emailsSent = $this->sendEmails($pending, $recipients['emails']);
        $whatsappsQueued = $this->sendWhatsApps($pending, $recipients['phones']);
        $databaseNotified = $this->sendDatabaseNotifications($pending, $recipients['users']);

        $entry = [
            'completed_at' => now()->toIso8601String(),
            'channels_active' => $recipients['channels_active'],
            'emails_sent' => $emailsSent,
            'emails_targeted' => $recipients['emails'],
            'whatsapps_queued' => $whatsappsQueued,
            'phones_targeted' => $recipients['phones'],
            'database_notified' => $databaseNotified,
            'users_notified' => $recipients['users']->pluck('name', 'id')->all(),
        ];

        foreach ($pending as $change) {
            $log = $change->notification_log ?? [];
            $log[$this->event] = $entry;
            $change->notification_log = $log;
            $change->saveQuietly();
        }

        SecurityAudit::log('AUDIT_CORPORATE_PAYMENT_FREQUENCY_NOTIFICATION_DISPATCHED', self::AUDIT_ROUTE, [
            'event' => $this->event,
            'change_ids' => $pending->modelKeys(),
            'channels_active' => $recipients['channels_active'],
            'emails_sent' => $emailsSent,
            'whatsapps_queued' => $whatsappsQueued,
            'database_notified' => $databaseNotified,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('NotifyAdministrationOfCorporatePaymentFrequencyChangeJob: FALLÓ', [
            'change_ids' => $this->changeIds,
            'event' => $this->event,
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * @param  Collection<int, AffiliationCorporatePaymentFrequencyChange>  $changes
     * @param  list<string>  $emails
     */
    private function sendEmails(Collection $changes, array $emails): int
    {
        if ($emails === []) {
            return 0;
        }

        $payload = Message::emailPayload($changes, $this->event);
        $subject = Message::emailSubject($changes, $this->event);
        $sent = 0;

        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new CorporatePaymentFrequencyChangeMail($payload, $email, $subject));
                $sent++;
            } catch (Throwable $exception) {
                Log::error('NotifyAdministrationOfCorporatePaymentFrequencyChangeJob: error enviando correo', [
                    'change_ids' => $changes->modelKeys(),
                    'email' => $email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * @param  Collection<int, AffiliationCorporatePaymentFrequencyChange>  $changes
     * @param  list<string>  $phones
     */
    private function sendWhatsApps(Collection $changes, array $phones): int
    {
        if ($phones === []) {
            return 0;
        }

        $body = Message::whatsappBody($changes, $this->event);
        $queued = 0;

        foreach ($phones as $phone) {
            try {
                SendNotificacionWhatsApp::dispatch(null, $body, $phone, null, [
                    'panel' => 'business',
                    'source' => self::AUDIT_ROUTE,
                    'event' => $this->event,
                    'change_ids' => $changes->modelKeys(),
                ]);
                $queued++;
            } catch (Throwable $exception) {
                Log::error('NotifyAdministrationOfCorporatePaymentFrequencyChangeJob: error encolando WhatsApp', [
                    'change_ids' => $changes->modelKeys(),
                    'phone' => $phone,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $queued;
    }

    /**
     * @param  Collection<int, AffiliationCorporatePaymentFrequencyChange>  $changes
     * @param  Collection<int, User>  $users
     */
    private function sendDatabaseNotifications(Collection $changes, Collection $users): int
    {
        if ($users->isEmpty()) {
            return 0;
        }

        $reversed = $this->event === Message::EVENT_REVERSED;

        try {
            Notification::make()
                ->title(Message::databaseTitle($changes, $this->event))
                ->body(Message::databaseBody($changes, $this->event))
                ->icon($reversed ? Heroicon::ArrowUturnLeft : Heroicon::ArrowsRightLeft)
                ->iconColor($reversed ? 'danger' : 'warning')
                ->actions([
                    Action::make('review')
                        ->label($reversed ? 'Ver reverso' : 'Revisar cambio')
                        ->button()
                        ->url(Message::reviewUrl($changes))
                        ->markAsRead(),
                ])
                ->sendToDatabase($users);
        } catch (Throwable $exception) {
            Log::error('NotifyAdministrationOfCorporatePaymentFrequencyChangeJob: error en notificación del panel', [
                'change_ids' => $changes->modelKeys(),
                'message' => $exception->getMessage(),
            ]);

            return 0;
        }

        return $users->count();
    }
}
