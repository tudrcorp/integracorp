<?php

declare(strict_types=1);

namespace App\Services\IntegracorpApi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IntegracorpApiClient
{
    public function baseUrl(): string
    {
        return rtrim((string) config('services.integracorp_api.base_url', 'http://127.0.0.1:4000'), '/');
    }

    public function timeout(): int
    {
        return (int) config('services.integracorp_api.timeout', 8);
    }

    public function request(): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->timeout($this->timeout())
            ->retry(1, 150);

        $apiKey = trim((string) config('services.integracorp_api.api_key', ''));
        if ($apiKey !== '') {
            $request = $request->withHeaders([
                'X-API-Key' => $apiKey,
            ]);
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    public function getJson(string $path, array $query = []): array
    {
        $response = $this->request()->get($path, $query);

        if (! $response->successful()) {
            throw new RuntimeException(
                "integracorp-api respondió HTTP {$response->status()} en {$path}."
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        if (($payload['success'] ?? false) !== true) {
            throw new RuntimeException("integracorp-api devolvió success=false en {$path}.");
        }

        return $payload;
    }
}
