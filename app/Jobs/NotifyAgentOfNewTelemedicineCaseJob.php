<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SystemNotificationKey;
use App\Models\ObservationCase;
use App\Models\TelemedicineCase;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\SecurityAudit;
use App\Support\SystemNotificationRecipients;
use App\Support\Telemedicine\TelemedicineCaseAgentNotice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisa por WhatsApp al agente (o a la agencia) dueño de la afiliación que se
 * registró un caso nuevo para su afiliado, con copia a los números del Centro
 * de notificaciones.
 *
 * Una sola vez por caso: antes de encolar los mensajes reclama
 * `telemedicine_cases.agent_notice_sent_at` con un UPDATE atómico. Un reintento
 * o un doble disparo encuentra la marca puesta y no envía nada. Cada WhatsApp
 * es un job propio, con sus reintentos: si UltraMsg falla, no se repite el
 * aviso a los demás destinatarios.
 */
class NotifyAgentOfNewTelemedicineCaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function __construct(
        public int $telemedicineCaseId,
    ) {}

    public function handle(): void
    {
        $key = SystemNotificationKey::TelemedicineCaseAgentNotice;

        if (! SystemNotificationRecipients::isActive($key)) {
            $this->audit('AUDIT_TELEMEDICINE_CASE_AGENT_NOTICE_SKIPPED', ['reason' => 'notification_inactive']);

            return;
        }

        $case = TelemedicineCase::query()->withoutGlobalScopes()->with('telemedicinePatient')->find($this->telemedicineCaseId);

        if (! $case instanceof TelemedicineCase) {
            return;
        }

        if ($case->agent_notice_sent_at !== null) {
            return;
        }

        $payload = TelemedicineCaseAgentNotice::payload($case);

        if ($payload === null) {
            $this->audit('AUDIT_TELEMEDICINE_CASE_AGENT_NOTICE_SKIPPED', ['reason' => 'patient_without_affiliation']);

            return;
        }

        $messages = $this->messagesFor($payload, SystemNotificationRecipients::phones($key));

        if ($messages === []) {
            $this->audit('AUDIT_TELEMEDICINE_CASE_AGENT_NOTICE_SKIPPED', [
                'reason' => 'no_recipients',
                'affiliation_code' => $payload['affiliation_code'],
            ]);

            return;
        }

        if (! $this->claim()) {
            return;
        }

        $queued = 0;

        try {
            foreach ($messages as $phone => $body) {
                SendNotificacionWhatsApp::dispatch(null, $body, $phone, null, [
                    'panel' => 'telemedicina',
                    'source' => 'telemedicine.case.agent-notice',
                    'telemedicine_case_id' => $this->telemedicineCaseId,
                ]);

                $queued++;
            }
        } catch (Throwable $exception) {
            /*
             * Si no salió ningún mensaje se libera la marca y el reintento lo
             * envía. Si alguno ya salió, la marca se queda: repetir el aviso a
             * quien ya lo recibió es peor que perder una copia.
             */
            if ($queued === 0) {
                $this->releaseClaim();

                throw $exception;
            }

            Log::error('NotifyAgentOfNewTelemedicineCaseJob: aviso encolado a medias', [
                'telemedicine_case_id' => $this->telemedicineCaseId,
                'queued' => $queued,
                'expected' => count($messages),
                'message' => $exception->getMessage(),
            ]);
        }

        $owner = $payload['owner'];

        ObservationCase::query()->create([
            'telemedicine_case_id' => $this->telemedicineCaseId,
            'description' => $owner !== null
                ? 'Aviso de caso nuevo enviado por WhatsApp '.str_replace('*', '', TelemedicineCaseAgentNotice::ownerLabel($owner)).'.'
                : 'Aviso de caso nuevo: la afiliación no tiene agente ni agencia activos con WhatsApp válido; solo se enviaron las copias de control.',
            'created_by' => 'system',
        ]);

        $this->audit('AUDIT_TELEMEDICINE_CASE_AGENT_NOTICE_DISPATCHED', [
            'affiliation_code' => $payload['affiliation_code'],
            'owner_kind' => $owner['kind'] ?? null,
            'owner_code' => $owner['code'] ?? null,
            'whatsapps_queued' => $queued,
            'whatsapps_expected' => count($messages),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('NotifyAgentOfNewTelemedicineCaseJob: FAILED', [
            'telemedicine_case_id' => $this->telemedicineCaseId,
            'message' => $exception?->getMessage(),
        ]);
    }

    /**
     * Teléfono normalizado => mensaje. El dueño recibe el aviso; las copias, la
     * versión de control. Un número repetido recibe un solo mensaje.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $copyPhones
     * @return array<string, string>
     */
    private function messagesFor(array $payload, array $copyPhones): array
    {
        $messages = [];
        $owner = $payload['owner'] ?? null;

        if (is_array($owner)) {
            $messages[$owner['phone']] = TelemedicineCaseAgentNotice::ownerBody($payload);
        }

        $copyBody = TelemedicineCaseAgentNotice::copyBody($payload);

        foreach ($copyPhones as $rawPhone) {
            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);

            if ($phone !== null && ! array_key_exists($phone, $messages)) {
                $messages[$phone] = $copyBody;
            }
        }

        return $messages;
    }

    /**
     * UPDATE atómico sobre la base (sin observers ni `updated_at`): solo uno
     * de dos procesos concurrentes lo gana.
     */
    private function claim(): bool
    {
        return TelemedicineCase::query()
            ->withoutGlobalScopes()
            ->toBase()
            ->where('id', $this->telemedicineCaseId)
            ->whereNull('agent_notice_sent_at')
            ->update(['agent_notice_sent_at' => now()]) === 1;
    }

    private function releaseClaim(): void
    {
        TelemedicineCase::query()
            ->withoutGlobalScopes()
            ->toBase()
            ->where('id', $this->telemedicineCaseId)
            ->update(['agent_notice_sent_at' => null]);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function audit(string $action, array $details): void
    {
        SecurityAudit::log($action, 'telemedicine.cases.agent-notice', [
            'telemedicine_case_id' => $this->telemedicineCaseId,
            ...$details,
        ]);
    }
}
