<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotificacionWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de reintentos permitidos.
     */
    public int $tries = 5;

    /**
     * Tiempo de espera entre reintentos (exponencial).
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 20, 60, 120, 300];
    }

    /**
     * Crear una nueva instancia del Job.
     */
    public function __construct(
        public mixed $user_id,
        public string $body,
        public string $phone,
        public mixed $document = null
    ) {}

    /**
     * Ejecuta la lógica del Job.
     */
    public function handle(): void
    {
        $curl = curl_init();

        try {
            $params = [
                'token'   => config('parameters.TOKEN'),
                'image'   => config('parameters.PUBLIC_URL') . '/images-whatsapp/integracorp.png',
                'to'      => $this->phone,
                'caption' => $this->body
            ];

            curl_setopt_array($curl, [
                CURLOPT_URL            => config('parameters.CURLOPT_URL_IMAGE'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => "",
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => "POST",
                CURLOPT_POSTFIELDS     => http_build_query($params),
                CURLOPT_HTTPHEADER     => [
                    "content-type: application/x-www-form-urlencoded"
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error    = curl_error($curl);

            // Validamos errores de conexión o de la API (Códigos no exitosos)
            if ($error || $httpCode >= 400) {
                throw new \Exception("CURL Error ({$httpCode}): " . ($error ?: $response));
            }

            Log::info("WhatsApp enviado correctamente", [
                'to'      => $this->phone,
                'status'  => $httpCode,
                'user_id' => $this->user_id
            ]);
        } catch (Throwable $e) {
            Log::error("Fallo en Job SendNotificacionWhatsApp", [
                'to'      => $this->phone,
                'error'   => $e->getMessage(),
                'user_id' => $this->user_id
            ]);

            // Forzamos el reintento del Job
            throw $e;
        } finally {
            curl_close($curl);
        }
    }

    /**
     * Acciones cuando el Job falla definitivamente.
     */
    public function failed(Throwable $exception): void
    {
        Log::critical("WhatsApp Job falló definitivamente tras varios reintentos", [
            'to'    => $this->phone,
            'error' => $exception->getMessage()
        ]);
    }
}
