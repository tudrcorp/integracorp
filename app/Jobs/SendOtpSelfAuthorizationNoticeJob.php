<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SystemOtpCodeKind;
use App\Mail\OtpSelfAuthorizationNoticeMail;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Aviso posterior a los autorizadores: alguien usó su propia clave OTP.
 * Cada destinatario va por separado; uno que falla no detiene a los demás.
 */
class SendOtpSelfAuthorizationNoticeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<string>  $emails
     * @param  list<string>  $phones
     */
    public function __construct(
        public string $kind,
        public string $userName,
        public string $subject,
        public string $reason,
        public string $at,
        public array $emails,
        public array $phones,
    ) {}

    public function handle(): void
    {
        $kind = SystemOtpCodeKind::tryFrom($this->kind);
        $notice = [
            'operation' => $kind?->label() ?? $this->kind,
            'where' => $kind?->whereLabel() ?? '',
            'user' => $this->userName,
            'subject' => $this->subject,
            'reason' => $this->reason,
            'at' => date('d/m/Y H:i', (int) strtotime($this->at)),
        ];

        foreach (array_unique($this->emails) as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            try {
                Mail::to($email)->send(new OtpSelfAuthorizationNoticeMail($notice));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $text = "*INTEGRACORP · Clave OTP autoautorizada*\n\n"
            .$notice['user'].' usó su propia clave para: '.$notice['operation']."\n"
            .'Detalle: '.$notice['subject']."\n"
            .'Motivo: '.$notice['reason']."\n"
            .'Fecha: '.$notice['at']."\n\n"
            .'No necesita hacer nada. Si no corresponde, revíselo en Negocios → Configuración → Claves OTP.';

        foreach (array_unique($this->phones) as $rawPhone) {
            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($rawPhone);
            if ($phone !== null) {
                SendNotificacionWhatsApp::dispatch(null, $text, $phone, null, ['source' => 'otp.self-authorized']);
            }
        }
    }
}
