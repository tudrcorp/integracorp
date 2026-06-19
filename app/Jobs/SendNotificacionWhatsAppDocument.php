<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\SecurityAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotificacionWhatsAppDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 20, 60, 120, 300];
    }

    public function __construct(
        public mixed $user_id,
        public string $caption,
        public string $phone,
        public string $documentUrl,
        public string $filename,
        public array $auditContext = [],
    ) {}

    public function handle(): void
    {
        $curl = curl_init();
        $response = null;
        $httpCode = null;
        $error = null;

        try {
            $params = [
                'token' => config('parameters.TOKEN'),
                'to' => $this->phone,
                'filename' => $this->filename,
                'document' => $this->documentUrl,
                'caption' => $this->caption,
            ];

            curl_setopt_array($curl, [
                CURLOPT_URL => config('parameters.CURLOPT_URL_DOCUMENT'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => http_build_query($params),
                CURLOPT_HTTPHEADER => [
                    'content-type: application/x-www-form-urlencoded',
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);

            if ($error || $httpCode >= 400) {
                throw new \Exception("CURL Error ({$httpCode}): ".($error ?: $response));
            }

            Log::info('WhatsApp documento enviado correctamente', [
                'to' => $this->phone,
                'status' => $httpCode,
                'filename' => $this->filename,
                'user_id' => $this->user_id,
                'audit_context' => $this->auditContext,
            ]);

            SecurityAudit::log('AUDIT_HELPDESK_WHATSAPP_SENT', $this->resolveAuditRoute(), [
                'where' => 'job.whatsapp.document.handle',
                'to' => $this->phone,
                'status_code' => $httpCode,
                'filename' => $this->filename,
                'response' => is_string($response) ? mb_substr($response, 0, 1200) : null,
                'media_type' => 'document',
                ...$this->auditContext,
            ]);
        } catch (Throwable $e) {
            Log::error('Fallo en Job SendNotificacionWhatsAppDocument', [
                'to' => $this->phone,
                'error' => $e->getMessage(),
                'filename' => $this->filename,
                'user_id' => $this->user_id,
                'status_code' => $httpCode,
                'curl_error' => $error,
                'response' => is_string($response) ? mb_substr($response, 0, 1200) : null,
                'audit_context' => $this->auditContext,
            ]);

            SecurityAudit::log('AUDIT_HELPDESK_WHATSAPP_SEND_FAILED', $this->resolveAuditRoute(), [
                'where' => 'job.whatsapp.document.handle',
                'to' => $this->phone,
                'status_code' => $httpCode,
                'curl_error' => $error,
                'response' => is_string($response) ? mb_substr($response, 0, 1200) : null,
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'media_type' => 'document',
                ...$this->auditContext,
            ]);

            throw $e;
        } finally {
            curl_close($curl);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('WhatsApp documento Job falló definitivamente tras varios reintentos', [
            'to' => $this->phone,
            'error' => $exception->getMessage(),
            'filename' => $this->filename,
            'audit_context' => $this->auditContext,
        ]);

        SecurityAudit::log('AUDIT_HELPDESK_WHATSAPP_SEND_FAILED_FINAL', $this->resolveAuditRoute(), [
            'where' => 'job.whatsapp.document.failed',
            'to' => $this->phone,
            'error' => $exception->getMessage(),
            'exception' => $exception::class,
            'media_type' => 'document',
            ...$this->auditContext,
        ]);
    }

    private function resolveAuditRoute(): string
    {
        $panel = (string) ($this->auditContext['panel'] ?? 'unknown');

        return $panel.'.helpdesks.notifications.whatsapp';
    }
}
