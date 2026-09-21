<?php

declare(strict_types=1);

namespace App\Services\TuDr;

use App\Exceptions\QuoteServiceUnavailableException;
use App\Exceptions\TarifaNoDisponibleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cliente del microservicio quote-pdf (Propuesta Económica de Tu Dr En Casa).
 *
 * El portal sigue siendo el maestro de tarifas y del número de control: aquí
 * solo se envía lo ya calculado y se recibe el documento. La clave viaja en
 * `X-Api-Key` y nunca se escribe en logs.
 */
class QuoteApiClient
{
    public function enabled(): bool
    {
        return (bool) config('services.tudr_quote.enabled', false)
            && $this->baseUrl() !== ''
            && $this->apiKey() !== '';
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('services.tudr_quote.url', ''), '/');
    }

    public function timeout(): int
    {
        return (int) config('services.tudr_quote.timeout', 10);
    }

    /**
     * Calcula la propuesta y devuelve el JSON con el PDF en base64.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws TarifaNoDisponibleException
     * @throws QuoteServiceUnavailableException
     */
    public function cotizar(array $payload): array
    {
        $response = $this->send('/api/cotizar', $payload + ['formato' => 'json']);

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        if (($data['ok'] ?? false) !== true) {
            throw new QuoteServiceUnavailableException(
                'El servicio de cotización respondió sin ok=true.',
                $response->status(),
            );
        }

        $this->logCall('/api/cotizar', $data);

        return $data;
    }

    /**
     * Dibuja el PDF de una propuesta ya calculada por el portal.
     *
     * @param  array<string, mixed>  $payload
     * @return string contenido binario del PDF
     *
     * @throws TarifaNoDisponibleException
     * @throws QuoteServiceUnavailableException
     */
    public function render(array $payload): string
    {
        $startedAt = microtime(true);

        $response = $this->send('/render', $payload);

        $pdf = $response->body();

        if (! str_starts_with($pdf, '%PDF')) {
            throw new QuoteServiceUnavailableException(
                'El servicio de cotización devolvió un documento que no es un PDF.',
                $response->status(),
            );
        }

        $this->logCall('/render', [
            'control' => $payload['control'] ?? null,
            'ms' => round((microtime(true) - $startedAt) * 1000, 1),
            'fuente_tarifas' => 'payload',
        ]);

        return $pdf;
    }

    /**
     * Pide directamente el binario del PDF a `/api/cotizar`.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws TarifaNoDisponibleException
     * @throws QuoteServiceUnavailableException
     */
    public function pdf(array $payload): string
    {
        $response = $this->send('/api/cotizar', $payload + ['formato' => 'pdf']);

        $pdf = $response->body();

        if (! str_starts_with($pdf, '%PDF')) {
            throw new QuoteServiceUnavailableException(
                'El servicio de cotización devolvió un documento que no es un PDF.',
                $response->status(),
            );
        }

        return $pdf;
    }

    /**
     * El health no lleva clave y nunca lanza: responde si el servicio está en pie.
     */
    public function health(): bool
    {
        if ($this->baseUrl() === '') {
            return false;
        }

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->acceptJson()
                ->timeout(min($this->timeout(), 5))
                ->get('/health');

            return $response->successful() && ($response->json('ok') === true);
        } catch (Throwable) {
            return false;
        }
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout($this->timeout())
            ->withHeaders([
                'X-Api-Key' => $this->apiKey(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws TarifaNoDisponibleException
     * @throws QuoteServiceUnavailableException
     */
    private function send(string $path, array $payload): Response
    {
        if ($this->baseUrl() === '' || $this->apiKey() === '') {
            throw new QuoteServiceUnavailableException('El servicio de cotización no está configurado.');
        }

        try {
            /**
             * Se reintenta la red y el 5xx; un 422 es una respuesta válida del
             * negocio —falta una tarifa— y repetirla solo gasta tiempo.
             */
            return $this->request()
                ->retry(2, 200, function (Throwable $exception): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    return $exception instanceof RequestException
                        && $exception->response->status() >= 500;
                })
                ->post($path, $payload);
        } catch (ConnectionException $exception) {
            throw new QuoteServiceUnavailableException(
                'No se pudo conectar con el servicio de cotización.',
                null,
                $exception,
            );
        } catch (RequestException $exception) {
            $response = $exception->response;

            if ($response->status() === 422) {
                /** @var array<string, mixed> $body */
                $body = $response->json() ?? [];

                /** @var list<array{plan?: string, motivo?: string}> $faltantes */
                $faltantes = is_array($body['faltantes'] ?? null) ? $body['faltantes'] : [];

                throw new TarifaNoDisponibleException(
                    (string) ($body['error'] ?? 'No hay tarifa disponible para la cotización.'),
                    $faltantes,
                );
            }

            throw new QuoteServiceUnavailableException(
                "El servicio de cotización respondió HTTP {$response->status()} en {$path}.",
                $response->status(),
                $exception,
            );
        }
    }

    private function apiKey(): string
    {
        return trim((string) config('services.tudr_quote.key', ''));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function logCall(string $path, array $data): void
    {
        Log::info('quote-pdf: llamada completada', [
            'path' => $path,
            'control' => $data['control'] ?? null,
            'ms' => $data['ms'] ?? null,
            'fuente_tarifas' => $data['fuente_tarifas'] ?? null,
        ]);
    }
}
